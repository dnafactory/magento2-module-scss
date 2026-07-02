<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace DNAFactory\Scss\Model\View\Asset\PreProcessor;

use DNAFactory\Scss\Api\AssetProcessorFilesystemManagementInterface;
use Magento\Framework\Css\PreProcessor\ErrorHandlerInterface;
use Magento\Framework\View\Asset\LocalInterface;
use Magento\Framework\Css\PreProcessor\Instruction\MagentoImport;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\File\CollectorInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;

/**
 * Selection of the strategy for assets pre-processing
 *
 * @api
 * @since 100.0.2
 */
class VarsImport extends MagentoImport
{

    /**
     * PCRE pattern that matches @theme_import instruction
     */
    const REPLACE_PATTERN =
        '#//@vars_import(?P<lib>\s+\(lib\))?\s+[\'\"](?P<path>(?![/\\\]|\w:[/\\\])[^\"\']+\.less)[\'\"]\s*?;#';

    /**
     * @var Repository
     */
    protected $assetRepo;

    /**
     * @var AssetProcessorFilesystemManagementInterface
     */
    protected $filesystemManager;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * Accumulated raw LESS vars content across the @vars_import directives of the
     * file currently being processed.
     *
     * @var string
     */
    private $varsBuffer = '';

    /**
     * Neutral fallbacks injected for variables the theme references but never defines
     * (relics tolerated by less.php 3). Map of "@name" => neutral value.
     *
     * @var array
     */
    private $undefinedFallbacks = [];

    /**
     * Max evaluation retries while neutralising undefined variables.
     */
    const MAX_UNDEFINED_RETRIES = 1000;

    /**
     * VarsImport constructor.
     * @param DesignInterface $design
     * @param CollectorInterface $fileSource
     * @param ErrorHandlerInterface $errorHandler
     * @param \Magento\Framework\View\Asset\Repository $assetRepo
     * @param \Magento\Framework\View\Design\Theme\ListInterface $themeList
     * @param AssetProcessorFilesystemManagementInterface $assetProcessorFilesystemManagement
     */
    public function __construct(
        DesignInterface $design,
        CollectorInterface $fileSource,
        ErrorHandlerInterface $errorHandler,
        \Magento\Framework\View\Asset\Repository $assetRepo,
        \Magento\Framework\View\Design\Theme\ListInterface $themeList,
        AssetProcessorFilesystemManagementInterface $assetProcessorFilesystemManagement,
        Filesystem $filesystem
    ){
        $this->assetRepo = $assetRepo;
        $this->filesystemManager = $assetProcessorFilesystemManagement;
        $this->filesystem = $filesystem;
        parent::__construct($design, $fileSource, $errorHandler, $assetRepo, $themeList);
    }

    /**
     * {@inheritdoc}
     */
    public function process(\Magento\Framework\View\Asset\PreProcessor\Chain $chain)
    {
        $asset = $chain->getAsset();

        // Reset the per-file accumulation. Every @vars_import directive in this file
        // feeds one shared context (evaluated in source order) so a variable defined in
        // one imported file resolves references in another (e.g. _extend.less uses
        // @font-size__desktop defined in _variables.less).
        $this->varsBuffer = '';
        $this->undefinedFallbacks = [];

        $replaceCallback = function ($matchContent) use ($asset) {
            return $this->replace($matchContent, $asset);
        };
        $chain->setContent(preg_replace_callback(self::REPLACE_PATTERN, $replaceCallback, $chain->getContent()));
    }

    /**
     * Replace @magento_import to @import instructions
     *
     * @param array $matchedContent
     * @param LocalInterface $asset
     * @return string
     */
    protected function replace(array $matchedContent, LocalInterface $asset)
    {
        $importsContent = '';
        try {
            $matchedFileId = $matchedContent['path'];
            $relatedAsset = $this->assetRepo->createRelated($matchedFileId, $asset);
            $resolvedPath = $relatedAsset->getFilePath();
            $importFiles = $this->fileSource->getFiles($this->getTheme($relatedAsset), $resolvedPath);

            $isLib = !empty($matchedContent['lib']);

            foreach ($importFiles as $importFile) {
                if($isLib) {
                    $baseContext = $this->assetRepo->getStaticViewFileContext();
                    $tempFile = $this->assetRepo->createAsset($resolvedPath, [
                        'area' => $baseContext->getAreaCode(),
                        'locale' => $baseContext->getLocale(),
                        'theme' => $this->getTheme($relatedAsset)->getCode(),
                        'module' => ''
                    ]);
                }else{
                    $tempFile = $this->assetRepo->createRelated($importFile->getName(), $relatedAsset);
                }

                // Accumulate every imported vars file into the shared buffer.
                $this->varsBuffer .= $this->filesystemManager->readVarsFromAsset($tempFile) . "\n";
            }

            $parsedVars = $this->extractVariables($this->varsBuffer);

            if($parsedVars && count($parsedVars) > 0) {
                $scssText = '';
                foreach ($parsedVars as $key => $value) {
                    $scssKey = preg_replace('/^@(.*)/', '\$$1', $key);
                    // less.php 5 getVariables() may return a float or a (possibly nested) array of value parts
                    $value = $this->stringifyVariableValue($value);
                    // normalize values
                    $value = preg_replace('/^(false)$/','null', trim($value));
                    // clear color values
                    $value = preg_replace('/^"(\#.+)"$/','$1', $value);
                    $scssText .= "{$scssKey}: {$value};\n";
                }
                $importsContent = $scssText."\n";
            }
        } catch (\LogicException $e) {
            $this->errorHandler->processException($e);
        }
        return $importsContent;
    }

    /**
     * less.php 5 Less_Parser::getVariables() may return a string, float or a
     * (possibly nested) array of value parts. Flatten it to a single string.
     *
     * @param mixed $value
     * @return string
     */
    private function stringifyVariableValue($value): string
    {
        if (is_array($value)) {
            return implode(' ', array_map([$this, 'stringifyVariableValue'], $value));
        }

        return (string)$value;
    }

    /**
     * Evaluate the accumulated LESS vars with the full Magento lib context and return
     * the computed variables. Mirrors Magento's own Less compiler (math=always + the
     * lib seed) and, like less.php 3, tolerates variables the theme references but
     * never defines: each such variable is neutralised (transparent, then 0) and the
     * evaluation retried, so a single missing relic cannot abort the whole stylesheet.
     *
     * @param string $varsContent
     * @return array
     */
    private function extractVariables(string $varsContent): array
    {
        $libFile = $this->getMagentoLibFile();

        for ($attempt = 0; $attempt < self::MAX_UNDEFINED_RETRIES; $attempt++) {
            $parser = new \Less_Parser([
                'relativeUrls' => false,
                'compress' => false,
                'math' => 'always',
            ]);

            try {
                gc_disable();
                if ($libFile) {
                    $parser->parseFile($libFile);
                }
                if ($this->undefinedFallbacks) {
                    $parser->parse($this->buildFallbackDefinitions());
                }
                $parser->parse($varsContent);
                $parser->getCss();
                $vars = $parser->getVariables();
                gc_enable();

                return $vars;
            } catch (\Exception $e) {
                gc_enable();
                if (!$this->neutraliseUndefined($e->getMessage())) {
                    // Not an undefined-variable error we can absorb: degrade to no vars
                    // instead of aborting the whole page.
                    $this->errorHandler->processException($e);
                    return [];
                }
            }
        }

        return [];
    }

    /**
     * Turn the tracked undefined-variable fallbacks into LESS declarations, parsed
     * before the theme vars so the references resolve.
     *
     * @return string
     */
    private function buildFallbackDefinitions(): string
    {
        $defs = '';
        foreach ($this->undefinedFallbacks as $name => $value) {
            $defs .= $name . ': ' . $value . ";\n";
        }

        return $defs;
    }

    /**
     * If $message reports an undefined variable, register/escalate a neutral fallback
     * for it (transparent for a color context, then 0 for a numeric one) and return
     * true so the evaluation can be retried. Returns false for any other error.
     *
     * @param string $message
     * @return bool
     */
    private function neutraliseUndefined(string $message): bool
    {
        if (!preg_match('/variable (@[\w\-]+) is undefined/i', $message, $matches)) {
            return false;
        }

        $name = $matches[1];
        $current = $this->undefinedFallbacks[$name] ?? null;

        if ($current === null) {
            $this->undefinedFallbacks[$name] = 'transparent';
        } elseif ($current === 'transparent') {
            $this->undefinedFallbacks[$name] = '0';
        } else {
            // Neither a color nor a number resolved it: a genuine problem, stop here.
            return false;
        }

        return true;
    }

    /**
     * Absolute path to Magento's global CSS lib entry (base variables + mixins),
     * so the vars parser has the same context as Magento's Less compiler.
     *
     * @return string|null
     */
    private function getMagentoLibFile(): ?string
    {
        $libDir = $this->filesystem->getDirectoryRead(DirectoryList::LIB_WEB);
        $relative = 'css/source/lib/_lib.less';

        return $libDir->isExist($relative) ? $libDir->getAbsolutePath($relative) : null;
    }
}
