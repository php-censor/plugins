<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Deploy;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Exception\Exception;
use PHPCensor\Common\Plugin\Plugin;

/**
 * Integrates PHPCensor with Magallanes v3: https://github.com/andres-montanez/Magallanes
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Stepan Strelets <s.strelec@nikitaonline.ru>
 */
class Mage3 extends Plugin
{
    private string $mageEnv = '';
    private string $mageLogDir = '';

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'mage3';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);

        if (empty($this->mageEnv)) {
            $this->buildLogger->logFailure('You must specify environment.');

            return false;
        }

        $result = $this->commandExecutor->executeCommand($executable . ' -n deploy ' . $this->mageEnv);

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
        $this->mageEnv = $this->variableInterpolator->interpolate(
            (string)$this->options->get('env', $this->mageEnv)
        );

        $this->mageLogDir = $this->variableInterpolator->interpolate(
            (string)$this->options->get('log_dir', $this->mageLogDir)
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'mage',
            'mage.phar',
        ];
    }

    /**
     * @throws Exception
     */
    private function getMageLog(): array
    {
        $logsDir = $this->build->getBuildPath() . (!empty($this->mageLogDir) ? '/' . $this->mageLogDir : '');
        if (!\is_dir($logsDir)) {
            throw new Exception('Log directory not found');
        }

        $list = \scandir($logsDir);
        if (false === $list) {
            throw new Exception('Log dir read fail');
        }

        $list = \array_filter($list, fn($name) => \preg_match('/^\d+_\d+\.log$/', (string)$name));

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
