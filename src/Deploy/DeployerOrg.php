<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Deploy;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Plugin\Plugin;

/**
 * Deployer plugin for PHP Censor: http://deployer.org
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Alexey Boyko <ket4yiit@gmail.com>
 */
class DeployerOrg extends Plugin
{
    private string $branch;

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'deployer_org';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);

        if (null !== ($validationResult = $this->validateConfig())) {
            $this->buildLogger->logNormal($validationResult['message']);

            return $validationResult['successful'];
        }

        $branchConfig = (array)$this->options->get($this->branch, []);
        $options      = $this->getOptions($branchConfig);
        $deployerCmd  = "$executable $options";

        return $this->commandExecutor->executeCommand($deployerCmd);
    }

    /**
     * {@inheritDoc}
     */
    public static function canExecute(string $stage, BuildInterface $build): bool
    {
        if (BuildInterface::STAGE_DEPLOY === $stage) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    protected function initPluginSettings(): void
    {
        $this->branch = $this->build->getBranch();
    }

    /**
     * {@inheritDoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'dep',
            'dep.phar',
        ];
    }

    /**
     * Validate config.
     *
     * $validationRes['message'] Message to log
     * $validationRes['successful'] Plugin status that is connected with error
     *
     *  @return array validation result
     */
    private function validateConfig(): ?array
    {
        if (!$this->options->all()) {
            return [
                'message'    => 'Can\'t find configuration for plugin!',
                'successful' => false,
            ];
        }

        if (!$this->options->get($this->branch)) {
            return [
                'message'    => 'There is no specified config for this branch.',
                'successful' => true,
            ];
        }

        $branchConf = $this->options->get($this->branch);
        if (empty($branchConf['stage'])) {
            return [
                'message'    => 'There is no stage for this branch',
                'successful' => false,
            ];
        }

        return null;
    }

    /**
     * Get verbosity flag.
     *
     * @param string $verbosity User defined verbosity level
     *
     * @return string Verbosity flag
     */
    private function getVerbosityOption(string $verbosity): string
    {
        $logLevelList = [
            'verbose'      => 'v',
            'very verbose' => 'vv',
            'debug'        => 'vvv',
            'quiet'        => 'q',
        ];

        $verbosity = \strtolower(\trim($verbosity));
        if ('normal' !== $verbosity) {
            return '-' . $logLevelList[$verbosity];
        }

        return '';
    }

    /**
     * Make deployer options from config
     *
     * @param array $config Deployer configuration array
     *
     * @return string Deployer options
     */
    private function getOptions(array $config): string
    {
        $options = [];
        if (!empty($config['task'])) {
            $options[] = $config['task'];
        } else {
            $options[] = 'deploy';
        }

        if (!empty($config['stage'])) {
            $options[] = $config['stage'];
        }

        if (!empty($config['verbosity'])) {
            $options[] = $this->getVerbosityOption($config['verbosity']);
        }

        if (!empty($config['file'])) {
            $options[] = '--file=' . $config['file'];
        }

        return \implode(' ', $options);
    }
}
