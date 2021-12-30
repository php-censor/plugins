<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\CodeQuality;

use PHPCensor\Common\Build\BuildErrorInterface;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Build\BuildMetaInterface;
use PHPCensor\Common\Exception\Exception;
use PHPCensor\Common\Plugin\Plugin;
use SebastianBergmann\Diff\Line;
use SebastianBergmann\Diff\Parser;

/**
 * PhpCsFixer - Works with the PHP Coding Standards Fixer for testing coding standards.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Gabriel Baker <gabriel@autonomicpilot.co.uk>
 */
class PhpCsFixer extends Plugin
{
    private string $args = '';

    private bool $config = false;

    private bool $errors = false;

    private bool $reportErrors = false;

    private int $allowedWarnings = 0;

    private bool $supportsUdiff = false;

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'php_cs_fixer';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);

        // Determine the version of PHP CS Fixer
        $cmd = $executable . ' --version';
        $this->commandExecutor->executeCommand($cmd);
        $output  = $this->commandExecutor->getLastCommandOutput();
        $matches = [];
        if (!\preg_match('/(\d+\.\d+\.\d+)/', $output, $matches)) {
            throw new Exception('Unable to determine the version of the PHP Coding Standards Fixer.');
        }

        $version = $matches[1];
        // Appeared in PHP CS Fixer 2.8.0 and used by default since 3.0.0
        // https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.19/CHANGELOG.md#changelog-for-v280
        $this->supportsUdiff = \version_compare($version, '2.8.0', '>=')
            && \version_compare($version, '3.0.0', '<');

        $directory = '';
        if (!empty($this->directory)) {
            $directory = $this->directory;
        }

        $config = [];
        if (!$this->config) {
            if (\version_compare($version, '3.0.0', '>=')) {
                $configs = ['.php-cs-fixer.php', '.php-cs-fixer.dist.php'];
            } else {
                $configs = ['.php_cs', '.php_cs.dist'];
            }

            foreach ($configs as $config) {
                if (\file_exists($this->build->getBuildPath() . $config)) {
                    $this->config = true;
                    $this->args .= ' --config=./' . $config;
                    break;
                }
            }
        }

        if (!$this->config && !$directory) {
            $directory = '.';
        }

        if ($this->errors) {
            $this->args .= ' --verbose --format json --diff';
            if ($this->supportsUdiff) {
                $this->args .= ' --diff-format udiff';
            }

            if (!$this->build->isDebug()) {
                $this->commandExecutor->disableCommandOutput(); // do not show json output
            }
        }

        $cmd     = $executable . ' fix ' . $directory . ' %s';
        $success = $this->commandExecutor->executeCommand($cmd, $this->args);
        $this->commandExecutor->enableCommandOutput();
        $output  = $this->commandExecutor->getLastCommandOutput();

        if ($this->errors) {
            $warningCount = $this->processReport($output);

            $this->buildMetaWriter->write(
                $this->build->getId(),
                self::getName(),
                BuildMetaInterface::KEY_WARNINGS,
                $warningCount
            );

            if (-1 !== $this->allowedWarnings && ($warningCount > $this->allowedWarnings)) {
                $success = false;
            }
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
        $this->args = (string)$this->options->get('args', $this->args);

        $verbose = (bool)$this->options->get('verbose', false);
        if ($verbose) {
            $this->args .= ' --verbose';
        }

        $diff = (bool)$this->options->get('diff', false);
        if ($diff) {
            $this->args .= ' --diff';
        }

        $rules = (string)$this->options->get('rules', '');
        if ($rules) {
            $this->args .= ' --rules=' . $rules;
        }

        $config = (string)$this->options->get('config', '');
        if ($config) {
            $this->config = true;
            $this->args   .= ' --config=' . $this->variableInterpolator->interpolate($config);
        }

        $this->errors = (bool)$this->options->get('errors', $this->errors);
        if ($this->errors) {
            $this->args .= ' --dry-run';

            $this->reportErrors    = (bool)$this->options->get('report_errors', $this->reportErrors);
            $this->allowedWarnings = (int)$this->options->get('allowed_warnings', $this->allowedWarnings);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'php-cs-fixer',
            'php-cs-fixer.phar',
        ];
    }

    /**
     * Process the PHP CS Fixer report.
     *
     * @throws Exception
     */
    private function processReport(string $output): int
    {
        $data = \json_decode(\trim($output), true);

        if (!\is_array($data)) {
            $this->buildLogger->logNormal($output);

            throw new Exception('Could not process the report generated by PHP CS Fixer.');
        }

        $warnings = 0;
        foreach ($data['files'] as $item) {
            $filename      = $item['name'];
            $appliedFixers = isset($item['appliedFixers']) ? $item['appliedFixers'] : [];

            $parser = new Parser();
            $parsed = $parser->parse($item['diff']);

            $diffItem = $parsed[0];
            foreach ($diffItem->getChunks() as $chunk) {
                $firstModifiedLine = $chunk->getStart();
                $foundChanges      = false;
                if (0 === $firstModifiedLine) {
                    $firstModifiedLine = null;
                    $foundChanges      = true;
                }
                $chunkDiff = [];
                foreach ($chunk->getLines() as $line) {
                    switch ($line->getType()) {
                        case Line::ADDED:
                            $symbol = '+';
                            break;
                        case Line::REMOVED:
                            $symbol = '-';
                            break;
                        default:
                            $symbol = ' ';
                            break;
                    }
                    $chunkDiff[] = $symbol . $line->getContent();
                    if ($foundChanges) {
                        continue;
                    }
                    if (Line::UNCHANGED === $line->getType()) {
                        ++$firstModifiedLine;

                        continue;
                    }

                    $foundChanges = true;
                }

                $warnings++;

                if ($this->reportErrors) {
                    $this->buildErrorWriter->write(
                        $this->build->getId(),
                        self::getName(),
                        "PHP CS Fixer suggestion:\r\n```diff\r\n" . \implode("\r\n", $chunkDiff) . "\r\n```",
                        BuildErrorInterface::SEVERITY_LOW,
                        $filename,
                        (int)$firstModifiedLine
                    );
                }
            }

            if ($this->reportErrors && !empty($appliedFixers)) {
                $this->buildErrorWriter->write(
                    $this->build->getId(),
                    self::getName(),
                    'PHP CS Fixer failed fixers: ' . \implode(', ', $appliedFixers),
                    BuildErrorInterface::SEVERITY_LOW,
                    $filename
                );
            }
        }

        return $warnings;
    }
}
