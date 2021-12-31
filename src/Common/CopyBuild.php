<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Common;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Exception\Exception;
use PHPCensor\Common\Plugin\Plugin;

/**
 * Copy Build Plugin - Copies the entire build to another directory.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Dan Cryer <dan@block8.co.uk>
 */
class CopyBuild extends Plugin
{
    private bool $respectIgnore = false;

    private bool $wipe = false;

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'copy_build';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $buildPath = $this->build->getBuildPath();

        if ($this->directory === $buildPath) {
            return false;
        }

        $this->wipeExistingDirectory();

        if (\is_dir($this->directory)) {
            throw new Exception(
                \sprintf(
                    'Directory "%s" already exists! Use "wipe" option if you want to delete directory before copy.',
                    $this->directory
                )
            );
        }

        $cmd     = 'cd "%s" && mkdir -p "%s" && cp -R %s/. "%s"';
        $success = $this->commandExecutor->executeCommand(
            $cmd,
            $buildPath,
            $this->directory,
            \rtrim($buildPath, '/'),
            $this->directory
        );

        $this->deleteIgnoredFiles();

        return $success;
    }

    /**
     * {@inheritDoc}
     */
    public static function canExecute(string $stage, BuildInterface $build): bool
    {
        if (\in_array($stage, [
            BuildInterface::STAGE_BROKEN,
            BuildInterface::STAGE_COMPLETE,
            BuildInterface::STAGE_FAILURE,
            BuildInterface::STAGE_FIXED,
            BuildInterface::STAGE_SUCCESS,
            BuildInterface::STAGE_DEPLOY,
        ], true)) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    protected function initPluginSettings(): void
    {
        $this->wipe          = (bool)$this->options->get('wipe', $this->wipe);
        $this->respectIgnore = (bool)$this->options->get('respect_ignore', $this->respectIgnore);
    }

    /**
     * Wipe the destination directory if it already exists.
     *
     * @throws Exception
     */
    private function wipeExistingDirectory(): void
    {
        if ($this->wipe === true && $this->directory !== '/' && \is_dir($this->directory)) {
            $cmd     = 'cd "%s" && rm -Rf "%s"';
            $success = $this->commandExecutor->executeCommand($cmd, $this->build->getBuildPath(), $this->directory);

            if (!$success) {
                throw new Exception(
                    \sprintf('Failed to wipe existing directory "%s" before copy!', $this->directory)
                );
            }

            \clearstatcache();
        }
    }

    /**
     * Delete any ignored files from the build prior to copying.
     */
    private function deleteIgnoredFiles(): void
    {
        if ($this->respectIgnore) {
            foreach ($this->ignores as $file) {
                $cmd = 'cd "%s" && rm -Rf "%s/%s"';
                $this->commandExecutor->executeCommand($cmd, $this->build->getBuildPath(), $this->directory, $file);
            }
        }
    }
}
