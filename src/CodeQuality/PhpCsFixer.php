<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\CodeQuality;

use PHPCensor\Common\Build\BuildErrorInterface;
use PHPCensor\Common\Build\BuildInterface;
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
    /**
     * @var string
     */
    private $args = '';

    /**
     * @var bool
     */
    private $config = false;

    /**
     * @var string[]
     */
    private $configs = [
        '.php_cs',
        '.php_cs.dist',
    ];

    /**
     * @var bool
     */
    private $errors = false;

    /**
     * @var bool
     */
    private $reportErrors = false;

    /**
     * @var int
     */
    private $allowedWarnings = 0;

    /**
     * @var bool
     */
    private $supportsUdiff = false;

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'php_cs_fixer';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $directory = '';
        if (!empty($this->directory)) {
            $directory = $this->directory;
        }

        if (!$this->config) {
            foreach ($this->configs as $config) {
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

        $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);

        // Determine the version of PHP CS Fixer
        $cmd     = $executable . ' --version';
        $success = $this->commandExecutor->executeCommand($cmd);
        $output  = $this->commandExecutor->getLastCommandOutput();
        $matches = [];
        if (\preg_match('/(\d+\.\d+\.\d+)/', $output, $matches)) {
            $version = $matches[1];
            // Appeared in PHP CS Fixer 2.8.0
            // https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.12/CHANGELOG.md#changelog-for-v280
            $this->supportsUdiff = \version_compare($version, '2.8.0', '>=');
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
                (self::getName() . '-warnings'),
                $warningCount
            );

            if (-1 !== $this->allowedWarnings && ($warningCount > $this->allowedWarnings)) {
                $success = false;
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
     * {@inheritdoc}
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
     * @param string $output
     *
     * @return int
     *
     * @throws Exception
     */
    private function processReport($output)
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
