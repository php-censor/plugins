<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Testing;

use PHPCensor\Common\Build\BuildErrorInterface;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Build\BuildMetaInterface;
use PHPCensor\Common\Exception\Exception;
use PHPCensor\Common\Plugin\Plugin;
use PHPCensor\Common\Plugin\ZeroConfigPluginInterface;
use PHPCensor\Plugins\Testing\PhpUnit\JsonResult;
use PHPCensor\Plugins\Testing\PhpUnit\JunitResult;
use PHPCensor\Plugins\Testing\PhpUnit\Options as PhpUnitOptions;
use Symfony\Component\Filesystem\Filesystem;

/**
 * PHP Unit Plugin - A rewrite of the original PHP Unit plugin.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Dan Cryer <dan@block8.co.uk>
 * @author Pablo Tejada <pablo@ptejada.com>
 */
class PhpUnit extends Plugin implements ZeroConfigPluginInterface
{
    private PhpUnitOptions $phpUnitOptions;

    private string $executable;

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'php_unit';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $this->executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);
        $xmlConfigFiles   = $this->phpUnitOptions->getConfigFiles($this->build->getBuildPath());
        $directories      = $this->phpUnitOptions->getDirectories();
        if (empty($xmlConfigFiles) && empty($directories)) {
            $this->buildLogger->logFailure('Neither a configuration file nor a test directory found.');

            return false;
        }

        $cmd      = $this->executable;
        $lastLine = \exec($cmd . ' --log-json . --version');
        if (\str_contains($lastLine, '--log-json')) {
            $logFormat = 'junit'; // --log-json is not supported
        } else {
            $logFormat = 'json';
        }

        $success = [];

        // Run any directories
        if (!empty($directories)) {
            foreach ($directories as $directory) {
                $success[] = $this->runConfig($directory, $logFormat);
            }
        } else {
            // Run any config files
            if ($xmlConfigFiles) {
                foreach ($xmlConfigFiles as $configFile) {
                    $success[] = $this->runConfig((string)$this->phpUnitOptions->getTestsPath(), $logFormat, $configFile);
                }
            }
        }

        return !\in_array(false, $success, true);
    }

    /**
     * {@inheritDoc}
     */
    public static function canExecute(string $stage, BuildInterface $build): bool
    {
        if (
            (BuildInterface::STAGE_TEST === $stage) &&
            !\is_null(PhpUnitOptions::findConfigFile((string)$build->getBuildPath()))
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
        $allowPublicArtifacts = $this->application->isPublicArtifactsAllowed();

        $this->phpUnitOptions = new PhpUnitOptions(
            $this->options,
            $this->artifactsPluginPath,
            $allowPublicArtifacts
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'phpunit',
            'phpunit.phar',
        ];
    }

    /**
     * Run the tests defined in a PHPUnit config file or in a specific directory.
     *
     * @return bool|mixed
     *
     * @throws Exception
     */
    private function runConfig(string $directory, string $logFormat, ?string $configFile = null): bool
    {
        $allowPublicArtifacts = $this->application->isPublicArtifactsAllowed();
        $fileSystem           = new Filesystem();

        $phpUnitOptions = clone $this->phpUnitOptions;
        $buildPath = $this->build->getBuildPath();

        // Save the results into a log file
        $logFile = \tempnam(\sys_get_temp_dir(), 'jlog_');
        $phpUnitOptions->addArgument('log-' . $logFormat, $logFile);

        // Removes any current configurations files
        $phpUnitOptions->removeArgument('configuration');
        if (null !== $configFile) {
            // Only the add the configuration file been passed
            $phpUnitOptions->addArgument('configuration', $buildPath . $configFile);
        }

        if ($phpUnitOptions->getOption('coverage') && $allowPublicArtifacts) {
            if (!$fileSystem->exists($this->artifactsPluginPath)) {
                $fileSystem->mkdir($this->artifactsPluginPath, (0777 & ~\umask()));
            }

            if (!\is_writable($this->artifactsPluginPath)) {
                throw new Exception(\sprintf(
                    'The location %s is not writable or does not exist.',
                    $this->artifactsPluginPath
                ));
            }
        }

        $arguments = $this->variableInterpolator->interpolate($phpUnitOptions->buildArgumentString());
        $cmd       = $this->executable . ' %s %s';
        $success   = $this->commandExecutor->executeCommand($cmd, $arguments, $directory);
        $output    = $this->commandExecutor->getLastCommandOutput();
        $covHtmlOk = false;

        if ($fileSystem->exists($this->artifactsPluginPath . 'index.html') &&
            $phpUnitOptions->getOption('coverage') &&
            $allowPublicArtifacts) {
            $covHtmlOk = true;
            $fileSystem->remove($this->artifactsPluginBranchPath);
            $fileSystem->mirror($this->artifactsPluginPath, $this->artifactsPluginBranchPath);
        }

        $this->processResults($logFile, $logFormat);

        $coverageSuccess = true;
        if ($phpUnitOptions->getOption('coverage')) {
            $currentCoverage = $this->extractCoverage($output);
            $this->buildMetaWriter->write(
                $this->build->getId(),
                self::getName(),
                BuildMetaInterface::KEY_COVERAGE,
                $currentCoverage
            );

            if ($covHtmlOk) {
                $this->buildLogger->logSuccess(
                    \sprintf(
                        "\nPHPUnit successful build coverage report.\nYou can use coverage report for this build: %s\nOr coverage report for last build in the branch: %s",
                        $this->getArtifactLink('index.html'),
                        $this->getArtifactLinkForBranch('index.html')
                    )
                );
            } elseif ($allowPublicArtifacts) {
                $this->buildLogger->logFailure(
                    \sprintf(
                        "\nPHPUnit could not build coverage report.\nmissing: %s\nlast of this branch: %s",
                        $this->getArtifactLink('index.html'),
                        $this->getArtifactLinkForBranch('index.html')
                    )
                );
            }

            $coverageSuccess = $this->checkRequiredCoverage($currentCoverage);
        }

        return $success && $coverageSuccess;
    }

    /**
     * Extracts coverage from output
     */
    private function extractCoverage(string $output): array
    {
        \preg_match(
            '#Classes:[\s]*(.*?)%[^M]*?Methods:[\s]*(.*?)%[^L]*?Lines:[\s]*(.*?)%#s',
            $output,
            $matches
        );

        return [
            'classes' => !empty($matches[1]) ? $matches[1] : '0.00',
            'methods' => !empty($matches[2]) ? $matches[2] : '0.00',
            'lines'   => !empty($matches[3]) ? $matches[3] : '0.00',
        ];
    }

    /**
     * Checks required test coverage
     */
    private function checkRequiredCoverage(array $coverage): bool
    {
        foreach ($coverage as $key => $currentValue) {
            if ($requiredValue = $this->phpUnitOptions->getOption(\implode('_', ['required', $key, 'coverage']))) {
                if (1 === \bccomp($requiredValue, (string)$currentValue)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Saves the test results
     *
     * @throws Exception If failed to parse the log file
     */
    private function processResults(string $logFile, string $logFormat): void
    {
        if (\file_exists($logFile)) {
            if ('json' === $logFormat) {
                $parser = new JsonResult($logFile, $this->build->getBuildPath());
            } else {
                $parser = new JunitResult($logFile, $this->build->getBuildPath());
            }

            $this->buildMetaWriter->write(
                $this->build->getId(),
                self::getName(),
                BuildMetaInterface::KEY_DATA,
                $parser->parse()->getResults()
            );
            $this->buildMetaWriter->write(
                $this->build->getId(),
                self::getName(),
                BuildMetaInterface::KEY_ERRORS,
                $parser->getFailures()
            );

            foreach ($parser->getErrors() as $error) {
                $severity = ($error['severity'] === $parser::SEVERITY_ERROR)
                    ? BuildErrorInterface::SEVERITY_CRITICAL
                    : BuildErrorInterface::SEVERITY_HIGH;

                $this->buildErrorWriter->write(
                    $this->build->getId(),
                    self::getName(),
                    (string)$error['message'],
                    $severity,
                    (string)$error['file'],
                    (int)$error['line']
                );
            }
            \unlink($logFile);
        } else {
            throw new Exception('log output file does not exist: ' . $logFile);
        }
    }
}
