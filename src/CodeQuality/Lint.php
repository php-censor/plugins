<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\CodeQuality;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Plugin\Plugin;

/**
 * PHP Lint Plugin - Provides access to PHP lint functionality.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Dan Cryer <dan@block8.co.uk>
 */
class Lint extends Plugin
{
    /**
     * @var array
     */
    private $directories = [];

    /**
     * @var bool
     */
    private $recursive = true;

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'lint';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $success = true;
        foreach ($this->directories as $dir) {
            if (!$this->lintDirectory($dir)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public static function canExecute(string $stage, BuildInterface $build): bool
    {
        if (BuildInterface::STAGE_TEST === $stage) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function initPluginSettings(): void
    {
        $this->directories = (array)$this->options->get('directories', $this->directories);
        foreach ($this->directories as $index => $directory) {
            $this->directories[$index] = $this->pathResolver->resolvePath($directory);
        }
        $this->directories[] = $this->directory;

        $this->recursive = (bool)$this->options->get('recursive', $this->recursive);
    }

    /**
     * Run php -l against a directory of files.
     *
     * @param $path
     *
     * @return bool
     */
    private function lintDirectory($path)
    {
        $success   = true;
        $directory = new \DirectoryIterator($path);
        foreach ($directory as $item) {
            if ($item->isDot()) {
                continue;
            }

            $itemPath = $path . $item->getFilename();

            if (\in_array($itemPath, $this->ignores)) {
                continue;
            }

            if (!$this->lintItem($item, $itemPath)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Lint an item (file or directory) by calling the appropriate method.
     *
     * @param \SplFileInfo $item
     * @param string       $itemPath
     *
     * @return bool
     */
    private function lintItem(\SplFileInfo $item, $itemPath)
    {
        $success = true;
        if ($item->isFile() && $item->getExtension() == 'php' && !$this->lintFile($itemPath)) {
            $success = false;
        } elseif (
            $item->isDir() &&
            $this->recursive &&
            !$this->lintDirectory($itemPath . '/')
        ) {
            $success = false;
        }

        return $success;
    }

    /**
     * Run php -l against a specific file.
     *
     * @param $path
     *
     * @return bool
     */
    private function lintFile($path)
    {
        $success = true;
        if (!$this->commandExecutor->executeCommand('php -l "%s"', $path)) {
            $this->buildLogger->logFailure($path);
            $success = false;
        }

        return $success;
    }
}
