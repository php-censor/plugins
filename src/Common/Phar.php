<?php

declare(strict_types = 1);

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
 */
class Phar extends Plugin
{
    /**
     * Phar Filename
     *
     * @var string
     */
    private $filename = 'build.phar';

    /**
     * Regular Expression Filename Capture
     *
     * @var string
     */
    private $regexp = '/\.php$/';

    /**
     * Stub Filename
     *
     * @var string
     */
    private $stub = '';

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'phar';
    }

    /**
     * {@inheritdoc}
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
        $this->regexp   = $this->options->get('regexp', $this->regexp);
        $this->stub     = $this->options->get('stub', $this->stub);
    }

    /**
     * Get stub content for the Phar file.
     *
     * @return string
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
