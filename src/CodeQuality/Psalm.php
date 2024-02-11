<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\CodeQuality;

use PHPCensor\Common\Build\BuildErrorInterface;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Plugin\Plugin;

/**
 * A static analysis tool for finding errors in PHP applications https://getpsalm.org
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Panagiotis Kosmidis <panoskosmidis87@gmail.com>
 */
class Psalm extends Plugin
{
    private int $allowedErrors = 0;

    private int $allowedWarnings = 0;

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'psalm';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);

        if (!$this->build->isDebug()) {
            $this->commandExecutor->disableCommandOutput();
        }

        $this->commandExecutor->executeCommand('cd "%s" && ' . $executable . ' --output-format=json', $this->build->getBuildPath());
        $this->commandExecutor->enableCommandOutput();

        $success = true;
        [$errors, $infos] = $this->processReport($this->commandExecutor->getLastCommandOutput());

        if (0 < \count($errors)) {
            if (-1 !== $this->allowedErrors && \count($errors) > $this->allowedErrors) {
                $success = false;
            }

            foreach ($errors as $error) {
                $this->buildLogger->logFailure('ERROR: ' . $error['full_message'] . PHP_EOL);

                $this->buildErrorWriter->write(
                    $this->build->getId(),
                    self::getName(),
                    $error['message'],
                    BuildErrorInterface::SEVERITY_HIGH,
                    $error['file'],
                    (int)$error['line_from'],
                    (int)$error['line_to']
                );
            }
        }

        if (0 < \count($infos)) {
            if (-1 !== $this->allowedWarnings && \count($infos) > $this->allowedWarnings) {
                $success = false;
            }

            foreach ($infos as $info) {
                $this->buildLogger->logFailure('INFO: ' . $info['full_message'] . PHP_EOL);

                $this->buildErrorWriter->write(
                    $this->build->getId(),
                    self::getName(),
                    $info['message'],
                    BuildErrorInterface::SEVERITY_LOW,
                    $info['file'],
                    (int)$info['line_from'],
                    (int)$info['line_to']
                );
            }
        }

        if ($success) {
            $this->buildLogger->logSuccess('No errors found!');
        }

        return $success;
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
        $this->allowedErrors   = (int)$this->options->get('allowed_errors', $this->allowedErrors);
        $this->allowedWarnings = (int)$this->options->get('allowed_warnings', $this->allowedWarnings);
    }

    /**
     * {@inheritDoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'psalm',
            'psalm.phar',
        ];
    }

    private function processReport(string $output): array
    {
        $data = \json_decode(\trim($output), true);

        $errors = [];
        $infos  = [];

        if (!empty($data) && \is_array($data)) {
            foreach ($data as $value) {
                if (!\in_array($value['severity'], ['error','info'], true)) {
                    continue;
                }

                ${$value['severity'].'s'}[] = [
                    'full_message' => \vsprintf('%s - %s:%d:%d - %s' . PHP_EOL . '%s', [
                        $value['type'],
                        $value['file_name'],
                        $value['line_from'],
                        $value['column_from'],
                        $value['message'],
                        $value['snippet'],
                    ]),
                    'message'   => $value['message'],
                    'file'      => $value['file_name'],
                    'line_from' => $value['line_from'],
                    'line_to'   => $value['line_to'],
                ];
            }
        }

        return [$errors, $infos];
    }
}
