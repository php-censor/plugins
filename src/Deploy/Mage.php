<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\Deploy;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Exception\Exception;
use PHPCensor\Common\Plugin\Plugin;

/**
 * Integrates with Magallanes: https://github.com/andres-montanez/Magallanes
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 */
class Mage extends Plugin
{
    private $mageEnv = '';

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'mage';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);

        if (empty($this->mageEnv)) {
            $this->buildLogger->logFailure('You must specify environment.');

            return false;
        }

        $result = $this->commandExecutor->executeCommand($executable . ' deploy to:' . $this->mageEnv);

        try {
            $this->buildLogger->logNormal('########## MAGE LOG BEGIN ##########');

            $logs = $this->getMageLog();
            foreach ($logs as $log) {
                $this->buildLogger->logNormal($log);
            }

            $this->buildLogger->logNormal('########## MAGE LOG END ##########');
        } catch (\Throwable $e) {
            $this->buildLogger->logFailure($e->getMessage());
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public static function canExecute(string $stage, BuildInterface $build): bool
    {
        if (BuildInterface::STAGE_DEPLOY === $stage) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function initPluginSettings(): void
    {
        $this->mageEnv = $this->variableInterpolator->interpolate(
            (string)$this->options->get('env', $this->mageEnv)
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'mage',
            'mage.phar',
        ];
    }

    /**
     * @return array
     *
     * @throws Exception
     */
    private function getMageLog(): array
    {
        $logsDir = $this->build->getBuildPath() . '/.mage/logs';
        if (!\is_dir($logsDir)) {
            throw new Exception('Log directory not found');
        }

        $list = \scandir($logsDir);
        if (false === $list) {
            throw new Exception('Log dir read fail');
        }

        $list = \array_filter($list, function ($name) {
            return \preg_match('/^log-\d+-\d+\.log$/', $name);
        });

        if (empty($list)) {
            throw new Exception('Log dir filter fail');
        }

        $res = \sort($list);
        if (false === $res) {
            throw new Exception('Logs sort fail');
        }

        $lastLogFile = \end($list);
        if (false === $lastLogFile) {
            throw new Exception('Get last Log name fail');
        }

        $logContent = \file_get_contents($logsDir . '/' . $lastLogFile);
        if (false === $logContent) {
            throw new Exception('Get last Log content fail');
        }

        $lines = \explode("\n", $logContent);
        $lines = \array_map('trim', $lines);
        $lines = \array_filter($lines);

        return $lines;
    }
}
