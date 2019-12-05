<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins;

use PHPCensor\Common\Plugin\Plugin;

/**
 * Shell Plugin allows execute shell commands.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Kinn Coelho Julião <kinncj@gmail.com>
 */
class Shell extends Plugin
{
    /**
     * @var string[] $commands The commands to be executed
     */
    protected $commands = [];

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'shell';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        foreach ($this->commands as $command) {
            $command = $this->variableInterpolator->interpolate($command);

            if (!$this->commandExecutor->executeCommand($command)) {
                return false;
            }
        }

        return true;
    }

    protected function initPluginSettings(): void
    {
        if ($this->options->has('commands')) {
            $commands = $this->options->get('commands');
            if (is_array($commands)) {
                $this->commands = $commands;
            }
        }
    }
}
