<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Deploy;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Exception\Exception;
use PHPCensor\Common\Plugin\Plugin;

/**
 * Phing Plugin - Provides access to Phing functionality.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Pavel Pavlov <ppavlov@alera.ru>
 */
class Phing extends Plugin
{
    private string $buildFile    = 'build.xml';
    private array $targets      = ['build'];
    private array $properties   = [];
    private string $propertyFile = '';

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'phing';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);

        $cmd   = [];
        $cmd[] = $executable . ' -f ' . $this->getBuildFilePath();

        if ($this->getPropertyFile()) {
            $cmd[] = '-propertyfile ' . $this->getPropertyFile();
        }

        $cmd[] = $this->propertiesToString();
        $cmd[] = '-logger phing.listener.DefaultLogger';
        $cmd[] = $this->targetsToString();
        $cmd[] = '2>&1';

        return $this->commandExecutor->executeCommand(\implode(' ', $cmd), $this->directory, $this->targets);
    }

    /**
     * {@inheritDoc}
     */
    public static function canExecute(string $stage, BuildInterface $build): bool
    {
        if (BuildInterface::STAGE_DEPLOY === $stage) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    protected function initPluginSettings(): void
    {
        $buildFile = (string)$this->options->get('build_file', $this->buildFile);
        $this->setBuildFile($buildFile);

        $targets = $this->options->get('targets', $this->targets);
        if (!\is_array($targets)) {
            $targets = [(string)$targets];
        }

        $this->setTargets($targets);

        $properties = $this->options->get('properties', $this->properties);
        if (!\is_array($properties)) {
            $properties = [(string)$properties];
        }
        $this->setProperties($properties);

        $propertyFile = (string)$this->options->get('property_file', $this->propertyFile);
        $this->setPropertyFile($propertyFile);
    }

    /**
     * {@inheritDoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'phing',
            'phing.phar',
        ];
    }

    /**
     * Converts an array of targets into a string.
     */
    private function targetsToString(): string
    {
        return \implode(' ', $this->targets);
    }

    /**
     * @param array|string $targets
     */
    private function setTargets($targets): void
    {
        if (\is_string($targets)) {
            $targets = [$targets];
        }

        $this->targets = $targets;
    }

    /**
     * @throws Exception
     */
    private function setBuildFile(string $buildFile): void
    {
        if (!\file_exists($this->directory . $buildFile)) {
            throw new Exception('Specified build file does not exist.');
        }

        $this->buildFile = $buildFile;
    }

    /**
     * Get phing build file path.
     */
    private function getBuildFilePath(): string
    {
        return $this->directory . $this->buildFile;
    }

    public function propertiesToString(): string
    {
        /** Fix the problem when execute phing out of the build dir */
        if (!isset($this->properties['project.basedir'])) {
            $this->properties['project.basedir'] = $this->directory;
        }

        $propertiesString = [];

        foreach ($this->properties as $name => $value) {
            $propertiesString[] = '-D' . $name . '="' . $value . '"';
        }

        return \implode(' ', $propertiesString);
    }

    /**
     * @param array|string $properties
     */
    private function setProperties($properties): void
    {
        if (\is_string($properties)) {
            $properties = [$properties];
        }

        $this->properties = $properties;
    }

    private function getPropertyFile(): string
    {
        return $this->propertyFile;
    }

    /**
     * @throws Exception
     */
    private function setPropertyFile(string $propertyFile): void
    {
        if (!\file_exists($this->directory . $propertyFile)) {
            throw new Exception('Specified property file does not exist.');
        }

        $this->propertyFile = $propertyFile;
    }
}
