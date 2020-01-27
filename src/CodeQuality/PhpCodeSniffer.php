<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\CodeQuality;

use PHPCensor\Common\Build\BuildErrorInterface;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Exception\Exception;
use PHPCensor\Common\Plugin\Plugin;
use PHPCensor\Common\Plugin\ZeroConfigPluginInterface;

/**
 * PHP Code Sniffer Plugin - Allows PHP Code Sniffer testing.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Dan Cryer <dan@block8.co.uk>
 */
class PhpCodeSniffer extends Plugin implements ZeroConfigPluginInterface
{
    /**
     * @var array
     */
    private $suffixes = ['php'];

    /**
     * @var string
     */
    private $standard = 'PSR2';

    /**
     * @var string
     */
    private $tabWidth = '';

    /**
     * @var string
     */
    private $encoding = '';

    /**
     * @var int
     */
    private $allowedErrors = 0;

    /**
     * @var int
     */
    private $allowedWarnings = 0;

    /**
     * @var int
     */
    private $severity = null;

    /**
     * @var null|int
     */
    private $errorSeverity = null;

    /**
     * @var null|int
     */
    private $warningSeverity = null;

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'php_code_sniffer';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        list($ignores, $standard, $suffixes, $severity, $errorSeverity, $warningSeverity) = $this->getFlags();

        $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);

        if (!$this->build->isDebug()) {
            $this->commandExecutor->disableCommandOutput();
        }

        $cmd = 'cd "%s" && ' . $executable . ' --report=json %s %s %s %s %s "%s" %s %s %s';
        $this->commandExecutor->executeCommand(
            $cmd,
            $this->build->getBuildPath(),
            $standard,
            $suffixes,
            $ignores,
            $this->tabWidth,
            $this->encoding,
            $this->directory,
            $severity,
            $errorSeverity,
            $warningSeverity
        );

        $output                  = $this->commandExecutor->getLastCommandOutput();
        list($errors, $warnings) = $this->processReport($output);

        $this->commandExecutor->enableCommandOutput();

        $success = true;

        $this->buildMetaWriter->write(
            $this->build->getId(),
            (self::getName() . '-warnings'),
            $warnings
        );

        $this->buildMetaWriter->write(
            $this->build->getId(),
            (self::getName() . '-errors'),
            $errors
        );

        if (-1 !== $this->allowedWarnings && $warnings > $this->allowedWarnings) {
            $success = false;
        }

        if (-1 !== $this->allowedErrors && $errors > $this->allowedErrors) {
            $success = false;
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
        if ($this->options->has('zero_config') && $this->options->get('zero_config', false)) {
            $this->allowedWarnings = -1;
            $this->allowedErrors   = -1;
        }

        $this->allowedErrors   = (int)$this->options->get('allowed_errors', $this->allowedErrors);
        $this->allowedWarnings = (int)$this->options->get('allowed_warnings', $this->allowedWarnings);
        $this->suffixes        = (array)$this->options->get('suffixes', $this->suffixes);
        $this->standard        = $this->options->get('standard', $this->standard);
        $this->severity        = $this->options->get('severity', $this->severity);
        $this->errorSeverity   = $this->options->get('error_severity', $this->errorSeverity);
        $this->warningSeverity = $this->options->get('warning_severity', $this->warningSeverity);

        if ($tabWidth = $this->options->get('tab_width')) {
            $this->tabWidth = ' --tab-width=' . $tabWidth;
        }

        if ($encoding = $this->options->get('encoding')) {
            $this->tabWidth = ' --encoding=' . $encoding;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'phpcs',
            'phpcs.phar',
        ];
    }

    protected function getFlags(): array
    {
        $ignoreString = '';
        if ($this->ignores) {
            $ignoreString = \sprintf(' --ignore="%s"', \implode(',', $this->ignores));
        }

        $standardPath = $this->pathResolver->resolvePath($this->standard, true);
        if (\file_exists($standardPath)) {
            $standard = ' --standard=' . $standardPath;
        } else {
            $standard = ' --standard=' . $this->standard;
        }

        $suffixes = '';
        if ($this->suffixes) {
            $suffixes = ' --extensions=' . \implode(',', $this->suffixes);
        }

        $severity = '';
        if (null !== $this->severity) {
            $severity = ' --severity=' . $this->severity;
        }

        $errorSeverity = '';
        if (null !== $this->errorSeverity) {
            $errorSeverity = ' --error-severity=' . $this->errorSeverity;
        }

        $warningSeverity = '';
        if (null !== $this->warningSeverity) {
            $warningSeverity = ' --warning-severity=' . $this->warningSeverity;
        }

        return [$ignoreString, $standard, $suffixes, $severity, $errorSeverity, $warningSeverity];
    }

    /**
     * @param string $output
     *
     * @return array
     *
     * @throws Exception
     */
    protected function processReport(string $output): array
    {
        $data = \json_decode(\trim($output), true);

        if (!\is_array($data)) {
            throw new Exception(
                'Could not process the report generated by PHP Code Sniffer. Report content: ' . $output
            );
        }

        $errors   = $data['totals']['errors'];
        $warnings = $data['totals']['warnings'];

        foreach ($data['files'] as $fileName => $file) {
            $fileName = \str_replace($this->build->getBuildPath(), '', $fileName);

            foreach ($file['messages'] as $message) {
                $this->buildErrorWriter->write(
                    $this->build->getId(),
                    self::getName(),
                    ('PHPCS: ' . $message['message']),
                    ('ERROR' === $message['type']
                        ? BuildErrorInterface::SEVERITY_HIGH
                        : BuildErrorInterface::SEVERITY_LOW
                    ),
                    $fileName,
                    $message['line']
                );
            }
        }

        return [$errors, $warnings];
    }
}
