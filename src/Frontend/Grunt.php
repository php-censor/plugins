<?php

declare(strict_types = 1);

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
    /**
     * @var string
     */
    private $task = '';

    /**
     * @var string
     */
    private $gruntfile = 'Gruntfile.js';

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'grunt';
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public static function canExecute(string $stage, BuildInterface $build): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function initPluginSettings(): void
    {
        $this->task      = $this->options->get('task', $this->task);
        $this->gruntfile = $this->options->get('gruntfile', $this->gruntfile);
    }

    /**
     * {@inheritdoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'grunt',
        ];
    }
}
