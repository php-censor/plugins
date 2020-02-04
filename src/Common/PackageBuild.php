<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\Common;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Plugin\Plugin;

/**
 * Create a ZIP or TAR.GZ archive of the entire build.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Dan Cryer <dan@block8.co.uk>
 */
class PackageBuild extends Plugin
{
    /**
     * @var string
     */
    private $filename = 'build';

    /**
     * @var string
     */
    private $format = 'zip';

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'package_build';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $path  = $this->build->getBuildPath();
        $build = $this->build;

        if ($this->directory === $path) {
            return false;
        }

        $filename = \str_replace('%build.commit%', $build->getCommitId(), $this->filename);
        $filename = \str_replace('%build.id%', $build->getId(), $filename);
        $filename = \str_replace('%build.branch%', $build->getBranch(), $filename);
        $filename = \str_replace('%project.title%', $build->getProject()->getTitle(), $filename);
        $filename = \str_replace('%date%', date('Y-m-d'), $filename);
        $filename = \str_replace('%time%', date('Hi'), $filename);
        $filename = \preg_replace('/([^a-zA-Z0-9_-]+)/', '', $filename);

        if (!\is_array($this->format)) {
            $this->format = [$this->format];
        }

        $success = true;
        foreach ($this->format as $format) {
            switch ($format) {
                case 'tar':
                    $cmd = 'tar cfz "%s/%s.tar.gz" ./*';

                    break;
                default:
                case 'zip':
                    $cmd = 'zip -rq "%s/%s.zip" ./*';

                    break;
            }

            $success = $this->commandExecutor->executeCommand($cmd, $this->directory, $filename);
        }

        return $success;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    protected function initPluginSettings(): void
    {
        $this->filename = $this->options->get('filename', $this->filename);
        $this->format   = $this->options->get('format', $this->format);
    }
}
