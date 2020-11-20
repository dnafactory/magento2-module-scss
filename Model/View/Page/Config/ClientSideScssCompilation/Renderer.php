<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace DNAFactory\Scss\Model\View\Page\Config\ClientSideScssCompilation;

use Magento\Framework\View\Page\Config;

/**
 * Page config Renderer model
 */
class Renderer extends \Magento\Developer\Model\View\Page\Config\ClientSideLessCompilation\Renderer
{
    /**
     * @var array
     */
    private static $processingTypes = ['css', 'less', 'scss'];

    /**
     * @param string $contentType
     * @param string $attributes
     * @return string
     */
    protected function addDefaultAttributes($contentType, $attributes)
    {
        $rel = ($contentType == 'scss')? 'stylesheet/scss' : '';

        if ($rel) {
            return ' rel="' . $rel . '" type="text/css" ' . ($attributes ?: ' media="all"');
        }
        return parent::addDefaultAttributes($contentType, $attributes);
    }

    /**
     * Get asset content type
     *
     * @param \Magento\Framework\View\Asset\AssetInterface|\Magento\Framework\View\Asset\File $asset
     * @return string
     */
    protected function getAssetContentType(\Magento\Framework\View\Asset\AssetInterface $asset)
    {
        if (!in_array($asset->getContentType(), self::$processingTypes)) {
            return parent::getAssetContentType($asset);
        }
        return $asset->getSourceContentType();
    }
}