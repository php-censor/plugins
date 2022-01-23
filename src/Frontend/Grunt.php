<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Frontend;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Plugin\Plugin;

/**
 * Grunt Plugin - Provides access to grunt functionality.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Tobias Tom <t.tom@succont.de>
 */
class Grunt extends Plugin
{
    private string $task = '';

    private string $gruntfile = 'Gruntfile.js';

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'grunt';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);

        // if npm does not work, we cannot use grunt, so we return false
        $cmd = 'cd "%s" && npm install';
        if (!$this->commandExecutor->executeCommand($cmd, $this->directory)) {
            return false;
        }

        $cmd = 'cd "%s" && ' . $executable;
        $cmd .= ' --no-color';
        $cmd .= ' --gruntfile %s';
        $cmd .= ' %s';

        return $this->commandExecutor->executeCommand($cmd, $this->directory, $this->gruntfile, $this->task);
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
        $this->task      = (string)$this->options->get('task', $this->task);
        $this->gruntfile = (string)$this->options->get('gruntfile', $this->gruntfile);
    }

    /**
     * {@inheritDoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'grunt',
        ];
    }
}
