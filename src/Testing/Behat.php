<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\Testing;

use PHPCensor\Common\Build\BuildErrorInterface;
use PHPCensor\Common\Plugin\Plugin;

/**
 * Behat BDD Plugin
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Dan Cryer <dan@block8.co.uk>
 */
class Behat extends Plugin
{
    /**
     * @var string
     */
    protected $features = '';

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'behat';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);
        $success    = $this->commandExecutor->executeCommand(($executable . ' %s'), $this->features);

        list($errorCount, $data) = $this->parseBehatOutput();

        $this->buildMetaWriter->write(
            $this->build->getId(),
            (self::getName() . '-warnings'),
            $errorCount
        );

        $this->buildMetaWriter->write(
            $this->build->getId(),
            (self::getName() . '-data'),
            $data
        );

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    protected function initPluginSettings(): void
    {
        $this->features = $this->options->get('features', $this->features);
    }

    /**
     * {@inheritdoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'behat',
            'behat.phar',
        ];
    }

    /**
     * Parse the behat output and return details on failures
     *
     * @return array
     */
    public function parseBehatOutput(): array
    {
        $output = $this->commandExecutor->getLastCommandOutput();
        $parts  = \explode('---', $output);

        if (\count($parts) <= 1) {
            return [0, []];
        }

        $lines = \explode(PHP_EOL, $parts[1]);

        $storeFailures = false;
        $data          = [];

        foreach ($lines as $line) {
            $line = \trim($line);
            if ('Failed scenarios:' === $line) {
                $storeFailures = true;

                continue;
            }

            if (false === \strpos($line, ':')) {
                $storeFailures = false;
            }

            if ($storeFailures) {
                $lineParts = \explode(':', $line);
                $data[]    = [
                    'file' => $lineParts[0],
                    'line' => $lineParts[1],
                ];

                $this->buildErrorWriter->write(
                    $this->build->getId(),
                    self::getName(),
                    'Behat scenario failed.',
                    BuildErrorInterface::SEVERITY_HIGH,
                    (string)$lineParts[0],
                    (int)$lineParts[1]
                );
            }
        }

        $errorCount = \count($data);

        return [$errorCount, $data];
    }
}
