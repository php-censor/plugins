<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\CodeQuality;

use PHPCensor\Common\Build\BuildErrorInterface;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Plugin\Plugin;

/**
 * Phlint is a tool with an aim to help maintain quality of php code by analyzing code and pointing out potential code
 * issues. It focuses on how the code works rather than how the code looks. Phlint is designed from the start to do
 * deep semantic analysis rather than doing only shallow or stylistic analysis.
 * https://gitlab.com/phlint/phlint
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Panagiotis Kosmidis <panoskosmidis87@gmail.com>
 */
class Phlint extends Plugin
{
    /**
     * @var int
     */
    private $allowedErrors = 0;

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'phlint';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);

        $this->commandExecutor->executeCommand(
            'cd "%s" && %s analyze --no-interaction --no-ansi',
            $this->build->getBuildPath(),
            $executable
        );

        $success = true;
        $errors  = $this->processReport($this->commandExecutor->getLastCommandOutput());

        if (0 < \count($errors)) {
            if (-1 !== $this->allowedErrors && \count($errors) > $this->allowedErrors) {
                $success = false;
            }

            foreach ($errors as $error) {
                $this->buildErrorWriter->write(
                    $this->build->getId(),
                    self::getName(),
                    $error['message'],
                    BuildErrorInterface::SEVERITY_HIGH,
                    $error['file'],
                    (int)$error['line_from']
                );
            }
        }

        return $success;
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
        $this->allowedErrors = (int)$this->options->get('allowed_errors', $this->allowedErrors);
    }

    /**
     * {@inheritdoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'phlint',
            'phlint.phar',
        ];
    }

    /**
     * @param string $output
     *
     * @return array
     */
    private function processReport(string $output): array
    {
        $data = \explode(\chr(226), \preg_replace('#\\x1b[[][^A-Za-z\n]*[A-Za-z]#', '', \trim($output)));
        \array_pop($data);
        \array_shift($data);

        $errors = [];
        if (0 < \count($data)) {
            foreach ($data as $error) {
                $error   = \explode(PHP_EOL, $error);
                $header  = (string)\substr(\trim(\array_shift($error)), 3);
                $file    = (string)\strstr((string)\substr((string)\strstr($header, 'in '), 3), ':', true);
                $line    = (int)\substr((string)\strrchr($header, ':'), 1);
                $message = \ltrim($error[0]) . PHP_EOL . \ltrim($error[1]);

                $errors[] = [
                    'message'   => $message,
                    'file'      => $file,
                    'line_from' => $line,
                ];
            }
        }

        return $errors;
    }
}
