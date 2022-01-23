<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Common;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Plugin\Plugin;

/**
 * Clean build removes Composer related files and allows users to clean up their build directory.
 * Useful as a precursor to copy_build.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Dan Cryer <dan@block8.co.uk>
 */
class CleanBuild extends Plugin
{
    private array $removeFiles = [];

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'clean_build';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $cmd     = 'rm -Rf "%s"';
        $success = true;

        foreach ($this->removeFiles as $file) {
            $ok = $this->commandExecutor->executeCommand($cmd, $this->build->getBuildPath() . $file);

            if (!$ok) {
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
        $this->removeFiles = (array)$this->options->get('remove', $this->removeFiles);
    }
}
