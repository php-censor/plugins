<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Common;

use PHPCensor\Common\Build\BuildInterface;
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
    private array $commands = [];

    private bool $executeAll = false;

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'shell';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $result = true;
        foreach ($this->commands as $command) {
            $command = $this->variableInterpolator->interpolate($command);

            if (!$this->commandExecutor->executeCommand($command)) {
                $result = false;

                if (!$this->executeAll) {
                    return $result;
                }
            }
        }

        return $result;
    }

    protected function initPluginSettings(): void
    {
        $this->executeAll = (bool)$this->options->get('execute_all', $this->executeAll);
        $this->commands   = (array)$this->options->get('commands', $this->commands);
    }

    /**
     * {@inheritDoc}
     */
    public static function canExecute(string $stage, BuildInterface $build): bool
    {
        return true;
    }
}
