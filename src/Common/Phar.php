<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Common;

use Phar as BasePhar;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Plugin\Plugin;

/**
 * Phar Plugin.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Wanderson Camargo <wandersonwhcr@gmail.com>
 */
class Phar extends Plugin
{
    /**
     * Phar Filename
     */
    private string $filename = 'build.phar';

    /**
     * Regular Expression Filename Capture
     */
    private string $regexp = '/\.php$/';

    /**
     * Stub Filename
     */
    private string $stub = '';

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'phar';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $success = false;
        try {
            $phar = new BasePhar($this->directory . $this->filename, 0, $this->filename);
            $phar->buildFromDirectory($this->build->getBuildPath(), $this->regexp);

            $stub = $this->getStubContent();
            if ($stub) {
                $phar->setStub($stub);
            }

            $success = true;
        } catch (\Throwable $e) {
            $this->buildLogger->logFailure('Phar Plugin Internal Error. Exception: ' . $e->getMessage());
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
        $this->filename = (string)$this->options->get('filename', $this->filename);
        $this->regexp   = (string)$this->options->get('regexp', $this->regexp);
        $this->stub     = (string)$this->options->get('stub', $this->stub);
    }

    /**
     * Get stub content for the Phar file.
     */
    private function getStubContent(): string
    {
        $content = '';
        if ($this->stub) {
            $content = \file_get_contents($this->build->getBuildPath() . $this->stub);
        }

        return $content;
    }
}
