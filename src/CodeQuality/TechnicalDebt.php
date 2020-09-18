<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\CodeQuality;

use PHPCensor\Common\Build\BuildErrorInterface;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Plugin\Plugin;
use PHPCensor\Common\Plugin\ZeroConfigPluginInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Technical Debt Plugin - Checks for existence of "TODO", "FIXME", etc.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author James Inman <james@jamesinman.co.uk>
 */
class TechnicalDebt extends Plugin implements ZeroConfigPluginInterface
{
    /**
     * @var string[]
     */
    private $suffixes = ['php'];

    /**
     * @var int
     */
    private $allowedErrors = 0;

    /**
     * @var string[] - terms to search for
     */
    private $searches = ['TODO', 'FIXME', 'TO DO', 'FIX ME'];

    /**
     * @var array - lines of . and X to visualize errors
     */
    private $errorPerFile = [];

    /**
     * @var int
     */
    private $currentLineSize = 0;

    /**
     * @var int
     */
    private $lineNumber = 0;

    /**
     * @var int
     */
    private $numberOfAnalysedFile = 0;

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'technical_debt';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $success    = true;
        $errorCount = $this->getErrorList();

        $this->buildLogger->logNormal($this->returnResult() . "Found $errorCount instances of " . \implode(', ', $this->searches));

        $this->buildMetaWriter->write(
            $this->build->getId(),
            (self::getName() . '-warnings'),
            $errorCount
        );

        if ($this->allowedErrors !== -1 && $errorCount > $this->allowedErrors) {
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
            $this->allowedErrors   = -1;
        }

        $this->suffixes      = (array)$this->options->get('suffixes', $this->suffixes);
        $this->searches      = (array)$this->options->get('searches', $this->searches);
        $this->allowedErrors = (int)$this->options->get('allowed_errors', $this->allowedErrors);
    }

    /**
     * Gets the number and list of errors returned from the search
     *
     * @return int
     */
    private function getErrorList(): int
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory));

        $this->buildLogger->logDebug("Directory: " . $this->directory);
        $this->buildLogger->logDebug("Ignored path: " . \json_encode($this->ignores, true));

        $errorCount = 0;
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            $filePath  = $file->getRealPath();
            $extension = $file->getExtension();

            $ignored = false;
            foreach ($this->suffixes as $suffix) {
                if ($suffix !== $extension) {
                    $ignored = true;

                    break;
                }
            }

            foreach ($this->ignores as $ignore) {
                $ignoreAbsolute = $this->build->getBuildPath() . $ignore;

                if ('/' === $ignoreAbsolute[0]) {
                    if (0 === \strpos($filePath, $ignoreAbsolute)) {
                        $ignored = true;

                        break;
                    }
                }
            }

            if (!$ignored) {
                $handle      = \fopen($filePath, "r");
                $lineNumber  = 1;
                $errorInFile = false;
                while (false === \feof($handle)) {
                    $line = \fgets($handle);

                    foreach ($this->searches as $search) {
                        if ($technicalDebtLine = \trim(\strstr($line, $search))) {
                            $fileName = \str_replace($this->directory, '', $filePath);

                            $this->buildErrorWriter->write(
                                $this->build->getId(),
                                self::getName(),
                                $technicalDebtLine,
                                BuildErrorInterface::SEVERITY_LOW,
                                $fileName,
                                (int)$lineNumber
                            );

                            $errorInFile = true;
                            $errorCount++;
                        }
                    }
                    $lineNumber++;
                }
                \fclose($handle);

                if ($errorInFile === true) {
                    $this->buildLogString('X');
                } else {
                    $this->buildLogString('.');
                }
            }
        }

        return (int)$errorCount;
    }

    /**
     * Create a visual representation of file with Todos
     *  ...XX... 10/300 (10 %)
     *
     * @return string The visual representation
     */
    private function returnResult(): string
    {
        $string     = '';
        $fileNumber = 0;
        foreach ($this->errorPerFile as $oneLine) {
            $fileNumber += \strlen($oneLine);
            $string     .= \str_pad($oneLine, 60, ' ', STR_PAD_RIGHT);
            $string     .= \str_pad($fileNumber, 4, ' ', STR_PAD_LEFT);
            $string     .= "/" . $this->numberOfAnalysedFile . " (" . \floor($fileNumber * 100 / $this->numberOfAnalysedFile) . " %)\n";
        }
        $string .= "Checked {$fileNumber} files\n";

        return $string;
    }

    /**
     * Store the status of the file :
     *   . : checked no errors
     *   X : checked with one or more errors
     *
     * @param string $char
     */
    private function buildLogString(string $char)
    {
        if (isset($this->errorPerFile[$this->lineNumber])) {
            $this->errorPerFile[$this->lineNumber] .= $char;
        } else {
            $this->errorPerFile[$this->lineNumber] = $char;
        }

        $this->currentLineSize++;
        $this->numberOfAnalysedFile++;

        if ($this->currentLineSize > 59) {
            $this->currentLineSize = 0;
            $this->lineNumber++;
        }
    }
}
