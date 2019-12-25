<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\CodeQuality;

use PHPCensor\Common\Build\BuildErrorInterface;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Exception\Exception;
use PHPCensor\Common\Plugin\Plugin;
use PHPCensor\Common\Plugin\ZeroConfigPluginInterface;

/**
 * PHP Mess Detector Plugin - Allows PHP Mess Detector testing.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Dan Cryer <dan@block8.co.uk>
 */
class PhpMessDetector extends Plugin implements ZeroConfigPluginInterface
{
    /**
     * @var array
     */
    protected $suffixes = ['php'];

    /**
     * Array of PHPMD rules. Can be one of the builtins (codesize, unusedcode, naming, design, controversial)
     * or a filename (detected by checking for a / in it), either absolute or relative to the project root.
     *
     * @var array
     */
    protected $rules = ['codesize', 'unusedcode', 'naming'];

    protected $allowedWarnings = 0;

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'php_mess_detector';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $this->processRules();

        $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);

        $this->executePhpMd($executable);

        $errorCount = $this->processReport(
            \trim($this->commandExecutor->getLastCommandOutput())
        );

        $this->buildMetaWriter->write(
            $this->build->getId(),
            (self::getName() . '-warnings'),
            $errorCount
        );

        if (-1 !== $this->allowedWarnings && $errorCount > $this->allowedWarnings) {
            return false;
        }

        return true;
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
        }

        $this->allowedWarnings = (int)$this->options->get('allowed_warnings', $this->allowedWarnings);
        $this->rules           = (array)$this->options->get('rules', $this->rules);
        $this->suffixes        = (array)$this->options->get('suffixes', $this->suffixes);
    }

    /**
     * {@inheritdoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'phpmd',
            'phpmd.phar',
        ];
    }

    protected function processRules(): void
    {
        foreach ($this->rules as &$rule) {
            if (false !== \strpos($rule, '/')) {
                $rule = $this->build->getBuildPath() . $rule;
            }
        }
    }

    /**
     * Process PHPMD's XML output report.
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
            throw new Exception('Could not process the report generated by PHPMD. XML content: ' . $xmlString);
        }

        $warnings = 0;
        foreach ($xml->file as $file) {
            $fileName = (string)$file['name'];
            $fileName = \str_replace($this->build->getBuildPath(), '', $fileName);

            foreach ($file->violation as $violation) {
                $warnings++;

                $this->buildErrorWriter->write(
                    $this->build->getId(),
                    self::getName(),
                    (string)$violation,
                    BuildErrorInterface::SEVERITY_HIGH,
                    $fileName,
                    (int)$violation['beginline'],
                    (int)$violation['endline']
                );
            }
        }

        return $warnings;
    }

    /**
     * Execute PHP Mess Detector.
     * @param $executable
     */
    protected function executePhpMd($executable)
    {
        $cmd = 'cd "%s" && ' . $executable . ' "%s" xml %s %s %s';

        $ignoreString = '';
        if ($this->ignores) {
            $ignoreString = \sprintf(' --exclude "%s"', \implode(',', $this->ignores));
        }

        $suffixes = '';
        if ($this->suffixes) {
            $suffixes = ' --suffixes ' . \implode(',', $this->suffixes);
        }

        if (!$this->build->isDebug()) {
            $this->commandExecutor->disableCommandOutput();
        }

        $this->commandExecutor->executeCommand(
            $cmd,
            $this->build->getBuildPath(),
            $this->directory,
            \implode(',', $this->rules),
            $ignoreString,
            $suffixes
        );

        $this->commandExecutor->enableCommandOutput();
    }
}