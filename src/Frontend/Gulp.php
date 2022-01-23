<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Frontend;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Plugin\Plugin;

/**
 * Gulp Plugin - Provides access to gulp functionality.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Dirk Heilig <dirk@heilig-online.com>
 */
class Gulp extends Plugin
{
    private string $task = '';

    private string $gulpfile = 'gulpfile.js';

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'gulp';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);

        // if npm does not work, we cannot use gulp, so we return false
        $cmd = 'cd "%s" && npm install';
        if (!$this->commandExecutor->executeCommand($cmd, $this->directory)) {
            return false;
        }

        $cmd = 'cd "%s" && ' . $executable;
        $cmd .= ' --no-color';
        $cmd .= ' --gulpfile %s';
        $cmd .= ' %s';

        return $this->commandExecutor->executeCommand($cmd, $this->directory, $this->gulpfile, $this->task);
    }

    /**
     * {@inheritDoc}
     */
    public static function canExecute(string $stage, BuildInterface $build): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    protected function initPluginSettings(): void
    {
        $this->task     = (string)$this->options->get('task', $this->task);
        $this->gulpfile = (string)$this->options->get('gulpfile', $this->gulpfile);
    }

    /**
     * {@inheritDoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'gulp',
        ];
    }
}
