<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\CodeQuality;

use PHPCensor\Common\Build\BuildErrorInterface;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Plugin\Plugin;

/**
 * PHPStan focuses on finding errors in your code without actually running it. It catches whole classes of bugs even
 * before you write tests for the code. It moves PHP closer to compiled languages in the sense that the correctness of
 * each line of the code can be checked before you run the actual line.
 * https://github.com/phpstan/phpstan
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Panagiotis Kosmidis <panoskosmidis87@gmail.com>
 */
class PhpStan extends Plugin
{
    /**
     * @var string[]
     */
    protected array $directories = [];

    private int $allowedErrors = 0;

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'php_stan';
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

        $this->commandExecutor->executeCommand(
            'cd "%s" && ' . $executable . ' analyze --error-format=json %s',
            $this->build->getBuildPath(),
            \implode(' ', $this->directories)
        );
        $this->commandExecutor->enableCommandOutput();

        $success = true;
        [$total_errors, $files] = $this->processReport($this->commandExecutor->getLastCommandOutput());

        if (0 < $total_errors) {
            if (-1 !== $this->allowedErrors && $total_errors > $this->allowedErrors) {
                $success = false;
            }

            foreach ($files as $file => $payload) {
                if (0 < $payload['errors']) {
                    $file = \str_replace($this->build->getBuildPath(), '', (string) $file);
                    $len  = \strlen($file);
                    $out  = '';
                    $filename = (\str_contains($file, ' (')) ? \strstr($file, ' (', true) : $file;

                    foreach ($payload['messages'] as $message) {
                        if (\strlen((string)$message['message']) > $len) {
                            $len = \strlen((string)$message['message']);
                        }
                        $out .= \vsprintf(' %d%s %s' . PHP_EOL, [
                            $message['line'],
                            \str_repeat(' ', 6 - \strlen((string)$message['line'])),
                            $message['message'],
                        ]);

                        $this->buildErrorWriter->write(
                            $this->build->getId(),
                            self::getName(),
                            $message['message'],
                            BuildErrorInterface::SEVERITY_NORMAL,
                            (string)$filename,
                            (int)$message['line']
                        );
                    }
                    $separator = \str_repeat('-', 6) . ' ' . \str_repeat('-', $len + 2) . PHP_EOL;

                    $this->buildLogger->logFailure(\vsprintf('%s Line   %s' . PHP_EOL . '%s', [
                        $separator,
                        $file,
                        $separator . $out . $separator,
                    ]));
                }
            }
        }

        if ($success) {
            $this->buildLogger->logSuccess('[OK] No errors');
        } else {
            $this->buildLogger->logNormal(\sprintf('[ERROR] Found %d errors', $total_errors));
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
        $this->allowedErrors = (int)$this->options->get('allowed_errors', $this->allowedErrors);
        $this->directories   = (array)$this->options->get('directories', $this->directories);
    }

    /**
     * {@inheritDoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'phpstan',
            'phpstan.phar',
        ];
    }

    private function processReport(string $output): array
    {
        $data = \json_decode(\trim($output), true);

        $totalErrors = 0;
        $files       = [];

        if (!empty($data) && \is_array($data) && (0 < $data['totals']['file_errors'])) {
            $totalErrors = $data['totals']['file_errors'];
            $files       = $data['files'];
        }

        return [$totalErrors, $files];
    }
}
