<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\CodeQuality;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Build\BuildMetaInterface;
use PHPCensor\Common\Plugin\Plugin;

/**
 * PHPTAL Lint Plugin - Provides access to PHPTAL lint functionality.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Stephen Ball <phpci@stephen.rebelinblue.com>
 */
class PhpTalLint extends Plugin
{
    private bool $recursive = true;

    private array $suffixes = ['zpt'];

    private int $allowedWarnings = 0;

    private int $allowedErrors = 0;

    /**
     * @var array The results of the lint scan
     */
    private array $failedPaths = [];

    private string $executable;

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'php_tal_lint';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $this->executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);

        $this->lintDirectory($this->directory);

        $errors   = 0;
        $warnings = 0;

        foreach ($this->failedPaths as $path) {
            if ($path['type'] === 'error') {
                $errors++;
            } else {
                $warnings++;
            }
        }

        $this->buildMetaWriter->write($this->build->getId(), self::getName(), BuildMetaInterface::KEY_WARNINGS, $warnings);
        $this->buildMetaWriter->write($this->build->getId(), self::getName(), BuildMetaInterface::KEY_ERRORS, $errors);
        $this->buildMetaWriter->write($this->build->getId(), self::getName(), BuildMetaInterface::KEY_DATA, $this->failedPaths);

        $success = true;

        if (-1 !== $this->allowedWarnings && $warnings > $this->allowedWarnings) {
            $success = false;
        }

        if (-1 !== $this->allowedErrors && $errors > $this->allowedErrors) {
            $success = false;
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
        $this->suffixes        = (array)$this->options->get('suffixes', $this->suffixes);
    }

    /**
     * {@inheritDoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'phptal-lint',
            'phptal-lint.phar',
        ];
    }

    /**
     * Run phptal lint against a directory of files.
     */
    private function lintDirectory(string $path): bool
    {
        $success   = true;
        $directory = new \DirectoryIterator($path);
        foreach ($directory as $item) {
            if ($item->isDot()) {
                continue;
            }

            $itemPath = $path . $item->getFilename();

            if (\in_array($itemPath, $this->ignores, true)) {
                continue;
            }

            if (!$this->lintItem($item, $itemPath)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Lint an item (file or directory) by calling the appropriate method.
     */
    private function lintItem(\SplFileInfo $item, string $itemPath): bool
    {
        $success = true;
        if ($item->isFile() && \in_array(\strtolower($item->getExtension()), $this->suffixes, true)) {
            if (!$this->lintFile($itemPath)) {
                $success = false;
            }
        } elseif ($item->isDir() && $this->recursive && !$this->lintDirectory($itemPath . '/')) {
            $success = false;
        }

        return $success;
    }

    /**
     * Run phptal lint against a specific file.
     */
    private function lintFile(string $path): bool
    {
        $success  = true;
        $suffixes = ' -e ' . \implode(',', $this->suffixes);
        $cmd      = $this->executable . ' %s "%s"';

        $this->commandExecutor->executeCommand($cmd, $suffixes, $path);

        $output = $this->commandExecutor->getLastCommandOutput();

        if (\preg_match('/Found (.+?) (error|warning)/i', $output, $matches)) {
            $rows = \explode(PHP_EOL, $output);

            unset($rows[0]);
            unset($rows[1]);
            unset($rows[2]);
            unset($rows[3]);

            foreach ($rows as $row) {
                $name    = \basename($path);
                $row     = \str_replace('(use -i to include your custom modifier functions)', '', $row);
                $message = \str_replace($name . ': ', '', $row);
                $parts   = \explode(' (line ', $message);
                $message = \trim($parts[0]);
                $line    = \str_replace(')', '', $parts[1]);

                $this->failedPaths[] = [
                    'file'    => $path,
                    'line'    => $line,
                    'type'    => $matches[2],
                    'message' => $message,
                ];
            }

            $success = false;
        }

        return $success;
    }
}
