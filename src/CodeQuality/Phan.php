<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\CodeQuality;

use PHPCensor\Common\Build\BuildErrorInterface;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Build\BuildMetaInterface;
use PHPCensor\Common\Exception\Exception;
use PHPCensor\Common\Plugin\Plugin;

/**
 * Launch Phan.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Fred Delrieu <caouecs@caouecs.net>
 */
class Phan extends Plugin
{
    /**
     * @var string Location on the server where the files are stored. Preferably in the webroot for inclusion
     *             in the readme.md of the repository
     */
    private string $location;

    /**
     */
    private int $allowedWarnings = 0;

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'phan';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);

        if (!\file_exists($this->location)) {
            \mkdir($this->location, (0777 & ~\umask()), true);
        }

        if (!\is_writable($this->location)) {
            throw new Exception(\sprintf('The location %s is not writable or does not exist.', $this->location));
        }

        // Find PHP files in a file
        $cmd = 'find -L %s -type f -name "**.php"';
        foreach ($this->ignores as $ignore) {
            $cmd .= ' | grep -v '. $ignore;
        }

        $cmd .= ' > %s';

        $this->commandExecutor->executeCommand($cmd, $this->directory, $this->location . '/phan.in');

        // Launch Phan on PHP files with json output
        $cmd = $executable .' -f %s -i -m json -o %s';

        $this->commandExecutor->executeCommand($cmd, $this->location . '/phan.in', $this->location . '/phan.out');

        $warningCount = $this->processReport(\file_get_contents($this->location . '/phan.out'));
        $this->buildMetaWriter->write(
            $this->build->getId(),
            self::getName(),
            BuildMetaInterface::KEY_WARNINGS,
            $warningCount
        );

        $success = true;

        if ($this->allowedWarnings !== -1 && $warningCount > $this->allowedWarnings) {
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
        $this->location        = $this->build->getBuildPath() .'phan_tmp';
        $this->allowedWarnings = (int)$this->options->get('allowed_warnings', $this->allowedWarnings);
    }

    /**
     * {@inheritDoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'phan',
            'phan.phar',
        ];
    }

    /**
     * Process the Phan Json report.
     *
     * @throws Exception
     */
    private function processReport(string $jsonString): int
    {
        $json = \json_decode($jsonString, true);

        if ($json === false || !\is_array($json)) {
            $this->buildLogger->logNormal($jsonString);

            throw new Exception('Could not process the report generated by Phan.');
        }

        $warnings = 0;

        foreach ($json as $data) {
            $this->buildErrorWriter->write(
                $this->build->getId(),
                self::getName(),
                $data['check_name']."\n\n".$data['description'],
                $this->severity((int)$data['severity']),
                $data['location']['path'] ?? '??',
                isset($data['location']['lines']['begin']) ? (int)$data['location']['lines']['begin'] : 0,
                isset($data['location']['lines']['end']) ? (int)$data['location']['lines']['end'] : 0
            );

            $warnings++;
        }

        return $warnings;
    }

    /**
     * Transform severity from Phan to PHP-Censor.
     */
    private function severity(int $severity): int
    {
        if ($severity === 10) {
            return BuildErrorInterface::SEVERITY_CRITICAL;
        }

        if ($severity === 5) {
            return BuildErrorInterface::SEVERITY_NORMAL;
        }

        return BuildErrorInterface::SEVERITY_LOW;
    }
}
