<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Testing;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Build\BuildMetaInterface;
use PHPCensor\Common\Exception\Exception;
use PHPCensor\Common\Plugin\Plugin;
use PHPCensor\Common\Plugin\ZeroConfigPluginInterface;
use PHPCensor\Plugins\Testing\Codeception\Parser as CodeceptionParser;
use Symfony\Component\Yaml\Parser as YamlParser;

/**
 * Codeception Plugin - Enables full acceptance, unit, and functional testing.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Don Gilbert <don@dongilbert.net>
 * @author Igor Timoshenko <contact@igortimoshenko.com>
 * @author Adam Cooper <adam@networkpie.co.uk>
 */
class Codeception extends Plugin implements ZeroConfigPluginInterface
{
    private string $args = '';

    /**
     * @var string $ymlConfigFile The path of a yml config for Parser
     */
    private string $ymlConfigFile = '';

    /**
     * default sub-path for report.xml file
     *
     * @var array $path The path to the report.xml file
     */
    private array $outputPath = [
        'tests/_output',
        'tests/_log',
    ];

    private string $executable;

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'codeception';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $this->executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);
        if (empty($this->ymlConfigFile)) {
            throw new Exception("No configuration file found");
        }

        // Run any config files first. This can be either a single value or an array.
        return $this->runConfigFile();
    }

    /**
     * {@inheritDoc}
     */
    public static function canExecute(string $stage, BuildInterface $build): bool
    {
        if (
            (BuildInterface::STAGE_TEST === $stage) &&
            !\is_null(self::findConfigFile((string)$build->getBuildPath()))
        ) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    protected function initPluginSettings(): void
    {
        $config = $this->options->get('config');
        if (empty($config)) {
            $this->ymlConfigFile = self::findConfigFile($this->directory);
        } else {
            $this->ymlConfigFile = $this->directory . $config;
        }

        $this->args = (string)$this->options->get('args', $this->args);

        $outputPath = $this->options->get('output_path');
        if ($outputPath) {
            \array_unshift($this->outputPath, $outputPath);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'codecept',
            'codecept.phar',
        ];
    }

    /**
     * Try and find the codeception YML config file.
     */
    public static function findConfigFile(string $buildPath): string
    {
        if (\file_exists($buildPath . 'codeception.yml')) {
            return $buildPath . 'codeception.yml';
        }

        if (\file_exists($buildPath . 'codeception.dist.yml')) {
            return $buildPath . 'codeception.dist.yml';
        }

        return '';
    }

    /**
     * Run tests from a Parser config file.
     */
    private function runConfigFile(): bool
    {
        if (!$this->executable) {
            $this->buildLogger->logFailure(\sprintf('Could not find "%s" binary', 'codecept'));

            return false;
        }

        $cmd = 'cd "%s" && ' . $this->executable . ' run -c "%s" ' . $this->args . ' --xml';

        $success = $this->commandExecutor->executeCommand($cmd, $this->directory, $this->ymlConfigFile);
        if (!$success) {
            return false;
        }

        $parser = new YamlParser();
        $yaml   = \file_get_contents($this->ymlConfigFile);
        $config = (array)$parser->parse($yaml);

        $trueReportXmlPath = null;
        if ($config && isset($config['paths']['log'])) {
            $trueReportXmlPath = \rtrim((string)$config['paths']['log'], '/\\') . '/';
        }

        if (!\file_exists($trueReportXmlPath . 'report.xml')) {
            foreach ($this->outputPath as $outputPath) {
                $trueReportXmlPath = \rtrim((string)$outputPath, '/\\') . '/';
                if (\file_exists($trueReportXmlPath . 'report.xml')) {
                    break;
                }
            }
        }

        if (!\file_exists($trueReportXmlPath . 'report.xml')) {
            $this->buildLogger->logFailure('"report.xml" file can not be found in configured "output_path!"');

            return false;
        }

        $parser = new CodeceptionParser($this->build->getBuildPath(), ($trueReportXmlPath . 'report.xml'));
        $output = $parser->parse();

        $meta = [
            'tests'     => $parser->getTotalTests(),
            'timetaken' => $parser->getTotalTimeTaken(),
            'failures'  => $parser->getTotalFailures(),
        ];

        // NOTE: Parser does not use stderr, so failure can only be detected
        // through tests
        $success = (($meta['failures']) < 1);

        $this->buildMetaWriter->write(
            $this->build->getId(),
            self::getName(),
            BuildMetaInterface::KEY_META,
            $meta
        );

        $this->buildMetaWriter->write(
            $this->build->getId(),
            self::getName(),
            BuildMetaInterface::KEY_DATA,
            $output
        );

        $this->buildMetaWriter->write(
            $this->build->getId(),
            self::getName(),
            BuildMetaInterface::KEY_ERRORS,
            $parser->getTotalFailures()
        );

        return $success;
    }
}
