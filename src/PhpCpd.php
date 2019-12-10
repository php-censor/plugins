<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins;

use PHPCensor\Common\Build\BuildErrorInterface;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Exception\Exception;
use PHPCensor\Common\Plugin\Plugin;
use PHPCensor\Common\Plugin\ZeroConfigPluginInterface;

/**
 * PHP Copy / Paste Detector - Allows PHP Copy / Paste Detector testing.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Dan Cryer <dan@block8.co.uk>
 */
class PhpCpd extends Plugin implements ZeroConfigPluginInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'php_cpd';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $ignoresString = '';
        if (\is_array($this->ignores)) {
            foreach ($this->ignores as $ignore) {
                $ignore = \rtrim($ignore, '/');
                if (\is_file($this->build->getBuildPath() . $ignore)) {
                    $ignoredFile     = \explode('/', $ignore);
                    $filesToIgnore[] = \array_pop($ignoredFile);
                } else {
                    $ignoresString .= \sprintf(' --exclude="%s"', $ignore);
                }
            }
        }

        if (isset($filesToIgnore)) {
            $filesToIgnore = \sprintf(' --names-exclude="%s"', \implode(',', $filesToIgnore));
            $ignoresString = $ignoresString . $filesToIgnore;
        }

        $executable  = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);
        $tmpFileName = \tempnam(\sys_get_temp_dir(), (self::getName() . '_'));

        $cmd     = 'cd "%s" && ' . $executable . ' --log-pmd "%s" %s "%s"';
        $success = $this->commandExecutor->executeCommand(
            $cmd,
            $this->build->getBuildPath(),
            $tmpFileName,
            $ignoresString,
            $this->directory
        );

        $errorCount = $this->processReport(\file_get_contents($tmpFileName));

        $this->buildMetaWriter->write(
            $this->build->getId(),
            (self::getName() . '-warnings'),
            (string)$errorCount
        );

        \unlink($tmpFileName);

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public static function canExecuteOnStage(string $stage, BuildInterface $build): bool
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
    }

    /**
     * {@inheritdoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'phpcpd',
            'phpcpd.phar',
        ];
    }

    /**
     * Process the PHPCPD XML report.
     *
     * @param $xmlString
     *
     * @return int
     *
     * @throws Exception
     */
    protected function processReport(string $xmlString): int
    {
        $xml = \simplexml_load_string($xmlString);

        if (false === $xml) {
            throw new Exception('Could not process the report generated by PHPCpd. XML content: ' . $xmlString);
        }

        $warnings = 0;
        foreach ($xml->duplication as $duplication) {
            foreach ($duplication->file as $file) {
                $fileName = (string)$file['path'];
                $fileName = \str_replace($this->build->getBuildPath(), '', $fileName);

                $message = <<<CPD
Copy and paste detected:

```
{$duplication->codefragment}
```
CPD;

                $this->buildErrorWriter->write(
                    $this->build->getId(),
                    self::getName(),
                    $message,
                    BuildErrorInterface::SEVERITY_NORMAL,
                    $fileName,
                    (int)$file['line'],
                    (int)($file['line'] + $duplication['lines'])
                );
            }
            $warnings++;
        }

        return $warnings;
    }
}
