<?php


namespace DNAFactory\Scss\Helper;


use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Css\PreProcessor\Config;
use Magento\Framework\Css\PreProcessor\File\Temporary;
use Magento\Framework\Filesystem;
use Magento\Framework\View\Asset\LocalInterface;

class AssetProcessorFilesystemManagement implements \DNAFactory\Scss\Api\AssetProcessorFilesystemManagementInterface
{
    /**
     * Pattern of 'import' instruction
     */
    const REPLACE_PATTERN =
        '#@import[\s]*'
        .'(?P<start>[\(\),\w\s]*?[\'\"][\s]*)'
        .'(?P<path>[^\)\'\"]*?)'
        .'(?P<end>[\s]*[\'\"][\s\w]*[\)]?)[\s]*;#';
    /**
     * @var Config
     */
    protected $config;
    /**
     * @var Filesystem
     */
    protected $filesystem;
    /**
     * @var Temporary
     */
    protected $temporaryFile;


    /**
     * AssetProcessorFilesystemManagement constructor.
     * @param Config $config
     * @param Filesystem $filesystem
     * @param Temporary $temporaryFile
     */
    public function __construct(
        Config $config,
        Filesystem $filesystem,
        Temporary $temporaryFile
    )
    {
        $this->config = $config;
        $this->filesystem = $filesystem;
        $this->temporaryFile = $temporaryFile;
    }


    /**
     * @inheritDoc
     */
    public function getAssetsMaterializationAbsolutePath(string $path = null)
    {
        return $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR)
            ->getAbsolutePath($this->getAssetsMaterializationRelativePath($path));
    }

    /**
     * @inheritDoc
     */
    public function getAssetsMaterializationRelativePath(string $path = null){
        $end = (!empty($path))? DS.$path : '';
        return $this->config->getMaterializationRelativePath().$end;
    }

    /**
     * @inheritDoc
     */
    public function readVarsFromAsset(LocalInterface $asset)
    {
        $path = $asset->getPath();
        $absolutePath = $this->getAssetsMaterializationAbsolutePath($path);
        $directory = $this->filesystem->getDirectoryReadByPath(dirname($absolutePath));
        $sourceFile = basename($path);
        $destinationFile = $this->generateDestinationFilename($sourceFile);
        $destinationPath = $this->generateDestinationFilename($path);

        if($directory->isExist($destinationFile) && $directory->isReadable($destinationFile)){
            return $directory->readFile($destinationFile);
        }

        $content = $asset->getContent();
        $parsedContent = $this->replaceImports($content, $directory->getAbsolutePath());
        $this->temporaryFile->createFile($destinationPath, $parsedContent);

        return $parsedContent;
    }

    /**
     * @param string $filename
     * @return string|string[]|null
     */
    private function generateDestinationFilename(string $filename){
        return preg_replace('/(.*)(\.less)+$/','$1.vars$2',$filename);
    }

    /**
     * @param $fileContent
     * @param $basePath
     * @return string|string[]|null
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    private function replaceImports($fileContent, $basePath){
        $replaceCallback = function ($matchedContent) use ($basePath){
            $filePath = $matchedContent['path'];
            $directory = $this->filesystem->getDirectoryReadByPath($basePath);
            $fileContent = $directory->readFile($filePath);
            $importFilePath = $directory->getAbsolutePath($filePath);
            return $this->replaceImports($fileContent, dirname($importFilePath));
        };
        return preg_replace_callback(self::REPLACE_PATTERN, $replaceCallback, $fileContent);
    }
}
