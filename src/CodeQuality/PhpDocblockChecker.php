<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\CodeQuality;

use PHPCensor\Common\Build\BuildErrorInterface;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Build\BuildMetaInterface;
use PHPCensor\Common\Plugin\Plugin;
use PHPCensor\Common\Plugin\ZeroConfigPluginInterface;

/**
 * PHP Docblock Checker Plugin - Checks your PHP files for appropriate uses of Docblocks
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Dan Cryer <dan@block8.co.uk>
 */
class PhpDocblockChecker extends Plugin implements ZeroConfigPluginInterface
{
    /**
     * @var bool
     */
    private bool $skipClasses = false;

    /**
     * @var bool
     */
    private bool $skipMethods = false;

    /**
     * @var bool
     */
    private bool $skipSignatures = false;

    /**
     * @var int
     */
    private int $allowedWarnings = 0;

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'php_docblock_checker';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);

        $ignore = '';
        if (\is_array($this->ignores)) {
            $ignore = \sprintf(' --exclude="%s"', \implode(',', $this->ignores));
        }

        $add = '';
        if ($this->skipClasses) {
            $add .= ' --skip-classes';
        }

        if ($this->skipMethods) {
            $add .= ' --skip-methods';
        }

        if ($this->skipSignatures) {
            $add .= ' --skip-signatures';
        }

        // Build command string:
        $cmd = $executable . ' --json --directory="%s"%s%s';

        if (!$this->build->isDebug()) {
            $this->commandExecutor->disableCommandOutput();
        }
        // Run checker:
        $this->commandExecutor->executeCommand(
            $cmd,
            $this->directory,
            $ignore,
            $add
        );
        $this->commandExecutor->enableCommandOutput();

        $output = \json_decode($this->commandExecutor->getLastCommandOutput(), true);

        $errors = 0;
        if ($output && \is_array($output)) {
            $errors = \count($output);
            $this->buildLogger->logWarning("Number of error : " . $errors);

            $this->reportErrors($output);
        }
        $this->buildMetaWriter->write($this->build->getId(), self::getName(), BuildMetaInterface::KEY_WARNINGS, $errors);

        $success = true;

        if (-1 !== $this->allowedWarnings && $errors > $this->allowedWarnings) {
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
        if (isset($options['zero_config']) && $options['zero_config']) {
            $this->allowedWarnings = -1;
        }

        $this->allowedWarnings = (int)$this->options->get('allowed_warnings', $this->allowedWarnings);
        $this->skipClasses     = (bool)$this->options->get('skip_classes', $this->skipClasses);
        $this->skipMethods     = (bool)$this->options->get('skip_methods', $this->skipMethods);
        $this->skipSignatures  = (bool)$this->options->get('skip_signatures', $this->skipSignatures);
    }

    /**
     * {@inheritdoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'phpdoc-checker',
            'phpdoc-checker.phar',
        ];
    }

    /**
     * Report all of the errors we've encountered line-by-line.
     *
     * @param array $output
     */
    private function reportErrors(array $output): void
    {
        foreach ($output as $error) {
            switch ($error['type']) {
                case 'class':
                    $message  = 'Class ' . $error['class'] . ' is missing a docblock.';
                    $severity = BuildErrorInterface::SEVERITY_NORMAL;
                    break;

                case 'method':
                    $message  = 'Method ' . $error['class'] . '::' . $error['method'] . ' is missing a docblock.';
                    $severity = BuildErrorInterface::SEVERITY_NORMAL;
                    break;

                case 'param-missing':
                    $message  = $error['class'] . '::' . $error['method'] . ' @param ' . $error['param'] . ' missing.';
                    $severity = BuildErrorInterface::SEVERITY_LOW;
                    break;

                case 'param-mismatch':
                    $message = $error['class'] . '::' . $error['method'] . ' @param ' . $error['param'] .
                        '(' . $error['doc-type'] . ') does not match method signature (' . $error['param-type'] . ')';
                    $severity = BuildErrorInterface::SEVERITY_LOW;
                    break;

                case 'return-missing':
                    $message  = $error['class'] . '::' . $error['method'] . ' @return missing.';
                    $severity = BuildErrorInterface::SEVERITY_LOW;
                    break;

                case 'return-mismatch':
                    $message = $error['class'] . '::' . $error['method'] . ' @return ' . $error['doc-type'] .
                        ' does not match method signature (' . $error['return-type'] . ')';
                    $severity = BuildErrorInterface::SEVERITY_LOW;
                    break;

                default:
                    $message  = 'Class ' . $error['class'] . ' invalid/missing a docblock.';
                    $severity = BuildErrorInterface::SEVERITY_LOW;
                    break;
            }

            $this->buildErrorWriter->write(
                $this->build->getId(),
                self::getName(),
                (string)$message,
                $severity,
                (string)$error['file'],
                (int)$error['line']
            );
        }
    }
}
