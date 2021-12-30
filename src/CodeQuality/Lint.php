<?php

declare(strict_types=1);

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
    private array $directories = [];

    private bool $recursive = true;

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'lint';
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public static function canExecute(string $stage, BuildInterface $build): bool
    {
        if (BuildInterface::STAGE_TEST === $stage) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritDoc}
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
     */
    private function lintDirectory($path): bool
    {
        $success   = true;
        $directory = new \DirectoryIterator($path);
        foreach ($directory as $item) {
            if ($item->isDot()) {
                continue;
            }

            $itemPath = $path . $item->getFilename();

            if (\in_array($itemPath, $this->ignores, true)) {
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
     */
    private function lintItem(\SplFileInfo $item, string $itemPath): bool
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
     */
    private function lintFile($path): bool
    {
        $success = true;
        if (!$this->commandExecutor->executeCommand('php -l "%s"', $path)) {
            $this->buildLogger->logFailure($path);
            $success = false;
        }

        return $success;
    }
}
