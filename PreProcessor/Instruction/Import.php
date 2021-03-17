<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace DNAFactory\Scss\PreProcessor\Instruction;

use Magento\Framework\View\Asset\LocalInterface;
use Magento\Framework\View\Asset\NotationResolver;
use Magento\Framework\Css\PreProcessor\FileGenerator\RelatedGenerator;
use \Magento\Framework\Css\PreProcessor\Instruction\Import as BaseClass;
use Magento\Framework\View\Asset\Repository;

/**
 * 'import' instruction preprocessor
 */
class Import extends BaseClass
{
    /**
     * @var \Magento\Framework\View\Asset\NotationResolver\Module
     */
    private $notationResolver;

    /**
     * @var Repository
     */
    private $assetRepository;

    /**
     * Constructor
     *
     * @param NotationResolver\Module $notationResolver
     * @param RelatedGenerator $relatedFileGenerator
     * @param Repository $assetRepository
     */
    public function __construct(
        NotationResolver\Module $notationResolver,
        RelatedGenerator $relatedFileGenerator,
        Repository $assetRepository
    ) {
        $this->notationResolver = $notationResolver;
        $this->assetRepository = $assetRepository;
        parent::__construct($notationResolver, $relatedFileGenerator);
    }

    /**
     * Return replacement of an original @import directive
     *
     * @param array $matchedContent
     * @param LocalInterface $asset
     * @param string $contentType
     * @return string
     */
    protected function replace(array $matchedContent, LocalInterface $asset, $contentType)
    {
        $matchedFileId = $this->fixFileExtension($matchedContent['path'], $contentType);

        $start = $matchedContent['start'];
        $end = $matchedContent['end'];

        $resolvedPath = $this->notationResolver->convertModuleNotationToPath($asset, $matchedFileId);
        $temp = $this->assetRepository->createSimilar(
            dirname($asset->getFilePath()).DIRECTORY_SEPARATOR.$this->fixFileExtension($resolvedPath, $contentType),
            $asset
        );
        try{
            $temp->getContent();
        }catch(\Exception $e){
            $matchedFileId = preg_replace('/(.+\/)?_?(.+)/', '$1_$2',$matchedFileId);
            $resolvedPath = $this->notationResolver->convertModuleNotationToPath($asset, $matchedFileId);
        }

        if (strpos(trim($start), 'url') !== 0) {
            $this->recordRelatedFile($matchedFileId, $asset);
        }

        return "@import {$start}{$resolvedPath}{$end};";
    }
}
