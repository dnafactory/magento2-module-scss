<?php


namespace DNAFactory\Scss\Api;


use Magento\Framework\View\Asset\LocalInterface;

interface AssetProcessorFilesystemManagementInterface
{
    /**
     * @param string|null $path
     * @return string
     */
    public function getAssetsMaterializationAbsolutePath(string $path = null);

    /**
     * @param string|null $path
     * @return string
     */
    public function getAssetsMaterializationRelativePath(string $path = null);

    /**
     * @param LocalInterface $asset
     * @return string|bool
     */
    public function readVarsFromAsset(LocalInterface $asset);
}
