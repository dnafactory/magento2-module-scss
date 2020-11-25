<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace DNAFactory\Scss\PreProcessor\Adapter\Scss;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\State;
use Magento\Framework\Css\PreProcessor\Config;
use Magento\Framework\Css\PreProcessor\File\Temporary;
use Magento\Framework\Filesystem;
use Psr\Log\LoggerInterface;
use Magento\Framework\View\Asset\File;
use Magento\Framework\View\Asset\Source;
use Magento\Framework\View\Asset\ContentProcessorInterface;
use ScssPhp\ScssPhp\OutputStyle;

/**
 * Class Processor
 */
class Processor implements ContentProcessorInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var State
     */
    private $appState;

    /**
     * @var Source
     */
    private $assetSource;

    /**
     * @var Temporary
     */
    private $temporaryFile;

    /**
     * @var \ScssPhp\ScssPhp\Compiler
     */
    private $compiler;
    /**
     * Constructor
     *
     * @param State $appState
     * @param Source $assetSource
     * @param LoggerInterface $logger
     * @param \ScssPhp\ScssPhp\Compiler $compiler
     * @param Temporary $temporaryFile
     * @param Config $config
     * @param Filesystem
     */
    public function __construct(
        State $appState,
        Source $assetSource,
        LoggerInterface $logger,
        \ScssPhp\ScssPhp\Compiler $compiler,
        Temporary $temporaryFile,
        Config $config,
        Filesystem $filesystem
    )
    {
        $this->assetSource = $assetSource;
        $this->logger = $logger;
        $this->compiler = $compiler;
        $this->temporaryFile = $temporaryFile;
        $this->appState = $appState;
        $includePath = $filesystem->getDirectoryRead(DirectoryList::VAR_DIR)
            ->getAbsolutePath($config->getMaterializationRelativePath());
        $this->compiler->addImportPath($includePath);
    }

    /**
     * Process file content
     *
     * @param File $asset
     * @return string
     */
    public function processContent(File $asset)
    {
        $path = $asset->getPath();
        try {
            $content = $this->assetSource->getContent($asset);

            if (trim($content) === '') {
                return '';
            }

            $this->compiler->setOutputStyle(($this->appState->getMode() !== State::MODE_DEVELOPER)?
                OutputStyle::COMPRESSED : OutputStyle::EXPANDED);

            $tmpFilePath = $this->temporaryFile->createFile($path, $content);

            $this->compiler->addImportPath(dirname($asset->getSourceFile()));

            gc_disable();
            $content = $this->compiler->compile($content, $tmpFilePath);
            gc_enable();

            return $content;
        } catch (\Exception $e) {
            $errorMessage = PHP_EOL . self::ERROR_MESSAGE_PREFIX . PHP_EOL . $path . PHP_EOL . $e->getMessage();
            $this->logger->critical($errorMessage);

            return $errorMessage;
        }
    }
}