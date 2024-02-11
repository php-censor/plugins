<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Testing;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Plugin\Plugin;

/**
 * Atoum plugin, runs Atoum tests within a project.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Sanpi <sanpi@homecomputing.fr>
 */
class Atoum extends Plugin
{
    private string $args = '';

    private string $config = '';

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'atoum';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);
        $cmd        = $executable;

        if ($this->args) {
            $cmd .= " {$this->args}";
        }

        if ($this->config) {
            $cmd .= " -c '{$this->config}'";
        }

        $cmd .= " --directories '{$this->directory}'";

        $status = true;

        $this->commandExecutor->executeCommand($cmd);

        $output = $this->commandExecutor->getLastCommandOutput();

        if (!\str_contains($output, 'Success (')) {
            $status = false;
            $this->buildLogger->logNormal($output);
        }

        if (!$output) {
            $status = false;
            $this->buildLogger->logNormal('No tests have been performed.');
        }

        return $status;
    }

    /**
     * {@inheritDoc}
     */
    public static function canExecute(string $stage, BuildInterface $build): bool
    {
        if (BuildInterface::STAGE_TEST === $stage) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    protected function initPluginSettings(): void
    {
        $this->args   = (string)$this->options->get('args', $this->args);
        $this->config = (string)$this->options->get('config', $this->config);
    }

    /**
     * {@inheritDoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'atoum',
            'atoum.phar',
        ];
    }
}
