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
     * @var \Less_Parser
     */
    private $parser;

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
        AssetProcessorFilesystemManagementInterface $assetProcessorFilesystemManagement
    ){
        $this->assetRepo = $assetRepo;
        $this->filesystemManager = $assetProcessorFilesystemManagement;
        $this->parser = new \Less_Parser(
            [
                'relativeUrls' => false,
                'compress' => false
            ]
        );
        parent::__construct($design, $fileSource, $errorHandler, $assetRepo, $themeList);
    }

    /**
     * {@inheritdoc}
     */
    public function process(\Magento\Framework\View\Asset\PreProcessor\Chain $chain)
    {
        $asset = $chain->getAsset();

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

            $parsedVars = [];
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

                $varsContent = $this->filesystemManager->readVarsFromAsset($tempFile);

                gc_disable();
                $this->parser->parse($varsContent);
                $this->parser->getCss();
                $parsedVars = array_merge($parsedVars, $this->parser->getVariables());
                gc_enable();
            }

            if($parsedVars && count($parsedVars) > 0) {
                $scssText = '';
                foreach ($parsedVars as $key => $value) {
                    $scssKey = preg_replace('/^@(.*)/', '\$$1', $key);
                    // normalize values
                    $value = preg_replace('/^(false)$/','null', trim($value));
                    // clear color values
                    $value = preg_replace('/^"(\#.+)"$/','$1', $value);
                    $scssText .= "${scssKey}: ${value};\n";
                }
                $importsContent = $scssText."\n";
            }
        } catch (\LogicException $e) {
            $this->errorHandler->processException($e);
        }
        return $importsContent;
    }
}
