<?php

declare(strict_types = 1);

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
    /**
     * @var string|null
     */
    private $args = null;

    /**
     * @var string|null
     */
    private $config = null;

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'atoum';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);
        $cmd        = $executable;

        if (null !== $this->args) {
            $cmd .= " {$this->args}";
        }

        if (null !== $this->config) {
            $cmd .= " -c '{$this->config}'";
        }

        $cmd .= " --directories '{$this->directory}'";

        $status = true;

        $this->commandExecutor->executeCommand($cmd);

        $output = $this->commandExecutor->getLastCommandOutput();

        if (false === \strpos($output, 'Success (')) {
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
     * {@inheritdoc}
     */
    public static function canExecute(string $stage, BuildInterface $build): bool
    {
        if (BuildInterface::STAGE_TEST === $stage) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function initPluginSettings(): void
    {
        $this->args   = $this->options->get('args', $this->args);
        $this->config = $this->options->get('config', $this->config);
    }

    /**
     * {@inheritdoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'atoum',
            'atoum.phar',
        ];
    }
}
