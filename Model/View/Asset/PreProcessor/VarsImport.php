<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace DNAFactory\Scss\Model\View\Asset\PreProcessor;

use Magento\Framework\App\State;
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
        '#//@vars_import(?P<reference>\s+\(reference\))?\s+[\'\"](?P<path>(?![/\\\]|\w:[/\\\])[^\"\']+\.less)[\'\"]\s*?;#';
    /**
     * @var State
     */
    protected $state;

    /**
     * @var Repository
     */
    protected $assetRepo;


    /**
     * VarsImport constructor.
     * @param DesignInterface $design
     * @param CollectorInterface $fileSource
     * @param ErrorHandlerInterface $errorHandler
     * @param \Magento\Framework\View\Asset\Repository $assetRepo
     * @param \Magento\Framework\View\Design\Theme\ListInterface $themeList
     * @param State $state
     */
    public function __construct(
        DesignInterface $design,
        CollectorInterface $fileSource,
        ErrorHandlerInterface $errorHandler,
        \Magento\Framework\View\Asset\Repository $assetRepo,
        \Magento\Framework\View\Design\Theme\ListInterface $themeList,
        State $state
    ){
        $this->state = $state;
        $this->assetRepo = $assetRepo;
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
            $parser = new \Less_Parser(
                [
                    'relativeUrls' => false,
                    'compress' => $this->state->getMode() !== State::MODE_DEVELOPER
                ]
            );
            $matchedFileId = $matchedContent['path'];
            $relatedAsset = $this->assetRepo->createRelated($matchedFileId, $asset);
            $resolvedPath = $relatedAsset->getFilePath();
            $importFiles = $this->fileSource->getFiles($this->getTheme($relatedAsset), $resolvedPath);
            /** @var $importFile \Magento\Framework\View\File */
            foreach ($importFiles as $importFile) {
                $parser->parseFile($importFile->getFilename());
            }
            $parsedVars = $parser->getVariables();
            if($parsedVars && count($parsedVars) > 0) {
                $scssText = '';
                $scssVars = [];
                foreach ($parsedVars as $key => $value) {
                    $scssKey = preg_replace('/^@(.*)/', '\$$1', $key);
                    // normalize values
                    $value = preg_replace('#^(?:(?!(\"|\'|[a-zA-Z]|var|rgb|url)).)*$#','"$0"', trim($value));
                    // clear color values
                    $value = preg_replace('/^"(\#.+)"$/','$1', $value);
                    $scssVars[$scssKey] = $value;
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
