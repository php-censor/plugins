<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Testing\PhpUnit;

use PHPCensor\Common\ParameterBag;

/**
 * Class Options validates and parse the option for the PhpUnitV2 plugin
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Pablo Tejada <pablo@ptejada.com>
 */
class Options
{
    private array $arguments = [];

    public function __construct(
        private readonly ParameterBag $options,
        private readonly string $location,
        private readonly bool $allowPublicArtifacts
    ) {
    }

    /**
     * Remove a command argument
     *
     * @return $this
     */
    public function removeArgument(string $argumentName): self
    {
        unset($this->arguments[$argumentName]);

        return $this;
    }

    /**
     * Combine all the argument into a string for the phpunit command
     */
    public function buildArgumentString(): string
    {
        $argumentString = '';
        foreach ($this->getCommandArguments() as $argumentName => $argumentValues) {
            $prefix = $argumentName[0] === '-'
                ? ''
                : '--';

            if (!\is_array($argumentValues)) {
                $argumentValues = [$argumentValues];
            }

            foreach ($argumentValues as $argValue) {
                $postfix = ' ';
                if (!empty($argValue)) {
                    $postfix = ' "' . $argValue . '" ';
                }
                $argumentString .= $prefix . $argumentName . $postfix;
            }
        }

        return $argumentString;
    }

    /**
     * Get all the command arguments
     *
     * @return string[]
     */
    public function getCommandArguments(): array
    {
        return $this->parseArguments()->arguments;
    }

    /**
     * Parse the arguments from the config options
     *
     * @return $this
     */
    private function parseArguments(): self
    {
        if (empty($this->arguments)) {
            /*
             * Parse the arguments from the YML options file
             */
            if ($this->options->has('args')) {
                $rawArgs = $this->options->get('args');
                if (\is_array($rawArgs)) {
                    $this->arguments = $rawArgs;
                } else {
                    /*
                     * Try to parse old arguments in a single string
                     */
                    \preg_match_all('@--([a-z\-]+)([\s=]+)?[\'"]?((?!--)[-\w/.,\\\]+)?[\'"]?@', (string)$rawArgs, $argsMatch);

                    if (!empty($argsMatch) && \sizeof($argsMatch) > 2) {
                        foreach ($argsMatch[1] as $index => $argName) {
                            $this->addArgument($argName, $argsMatch[3][$index]);
                        }
                    }
                }
            }

            /*
             * Handles command aliases outside of the args option
             */
            if ((bool)$this->options->get('coverage', false)) {
                if ($this->allowPublicArtifacts) {
                    $this->addArgument('coverage-html', $this->location);
                }
                $this->addArgument('coverage-text');
            }

            /*
             * Handles command aliases outside of the args option
             */
            if ($this->options->has('config')) {
                $this->addArgument(
                    'configuration',
                    $this->options->get('config')
                );
            }
        }

        return $this;
    }

    /**
     * Add an argument to the collection
     * Note: adding argument before parsing the options will prevent the other options from been parsed.
     */
    public function addArgument(string $argumentName, ?string $argumentValue = null): void
    {
        if (isset($this->arguments[$argumentName])) {
            if (!\is_array($this->arguments[$argumentName])) {
                // Convert existing argument values into an array
                $this->arguments[$argumentName] = [$this->arguments[$argumentName]];
            }

            // Appends the new argument to the list
            $this->arguments[$argumentName][] = $argumentValue;
        } else {
            // Adds new argument
            $this->arguments[$argumentName] = $argumentValue;
        }
    }

    /**
     * Get the list of directory to run phpunit in
     *
     * @return string[] List of directories
     */
    public function getDirectories(): array
    {
        $directories = $this->getOption('directories');

        if (\is_string($directories)) {
            $directories = [$directories];
        } elseif (\is_null($directories)) {
            $directories = [];
        }

        return $directories;
    }

    /**
     * Get an option if defined
     *
     * @return string|string[]|null
     */
    public function getOption(string $optionName)
    {
        if ($this->options->has($optionName)) {
            return $this->options->get($optionName);
        }

        return null;
    }

    /**
     * Get the directory to execute the command from
     *
     * @return mixed|null
     */
    public function getRunFrom()
    {
        return $this->getOption('run_from');
    }

    /**
     * Ge the directory name where tests file reside
     */
    public function getTestsPath(): ?string
    {
        return $this->getOption('path');
    }

    /**
     * Get the PHPUnit configuration from the options, or the optional path
     *
     * @return string[] path of files
     */
    public function getConfigFiles(?string $altPath = null): array
    {
        $configFiles = $this->getArgument('configuration');
        if (empty($configFiles) && $altPath) {
            $configFile = self::findConfigFile($altPath);
            if ($configFile) {
                $configFiles[] = $configFile;
            }
        }

        return $configFiles;
    }

    /**
     * Get options for a given argument
     *
     * @return string[] All the options for given argument
     */
    public function getArgument(string $argumentName): array
    {
        $this->parseArguments();

        if (isset($this->arguments[$argumentName])) {
            return \is_array(
                $this->arguments[$argumentName]
            ) ? $this->arguments[$argumentName] : [$this->arguments[$argumentName]];
        }

        return [];
    }

    /**
     * Find a PHPUnit configuration file in a directory
     *
     * @param string $buildPath The path to configuration file
     */
    public static function findConfigFile(string $buildPath): ?string
    {
        $files = [
            'phpunit.xml',
            'phpunit.mysql.xml',
            'phpunit.pgsql.xml',
            'phpunit.xml.dist',
            'tests/phpunit.xml',
            'tests/phpunit.xml.dist',
        ];

        foreach ($files as $file) {
            if (\file_exists($buildPath . $file)) {
                return $file;
            }
        }

        return null;
    }
}
