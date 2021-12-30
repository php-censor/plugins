<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\CodeQuality;

use PHPCensor\Common\Build\BuildErrorInterface;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Build\BuildMetaInterface;
use PHPCensor\Common\Exception\Exception;
use PHPCensor\Common\Plugin\Plugin;

/**
 * Sensiolabs Insight Plugin - Allows Sensiolabs Insight testing.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Eugen Ganshorn <eugen@ganshorn.eu>
 */
class SensiolabsInsight extends Plugin
{
    private string $userUuid = '';

    private string $authToken = '';

    private string $projectUuid = '';

    private int $allowedWarnings = 0;

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'sensiolabs_insight';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);

        $this->executeSensiolabsInsight($executable);

        $errorCount = $this->processReport(\trim($this->commandExecutor->getLastCommandOutput()));
        $this->buildMetaWriter->write($this->build->getId(), self::getName(), BuildMetaInterface::KEY_WARNINGS, $errorCount);

        return $this->wasLastExecSuccessful($errorCount);
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
        $this->allowedWarnings = (int)$this->options->get('allowed_warnings', $this->allowedWarnings);
        $this->userUuid        = (string)$this->options->get('user_uuid', $this->userUuid);
        $this->authToken       = (string)$this->options->get('auth_token', $this->authToken);
        $this->projectUuid     = (string)$this->options->get('project_uuid', $this->projectUuid);
    }

    /**
     * {@inheritDoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'insight',
        ];
    }

    /**
     * Process PHPMD's XML output report.
     *
     * @throws Exception
     */
    private function processReport(string $xmlString): int
    {
        $xml = \simplexml_load_string($xmlString);

        if ($xml === false) {
            $this->buildLogger->logNormal($xmlString);

            throw new Exception('Could not process PHPMD report XML.');
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
                    (string)$fileName,
                    (int)$violation['beginline'],
                    (int)$violation['endline']
                );
            }
        }

        return $warnings;
    }

    /**
     * Execute Sensiolabs Insight.
     */
    private function executeSensiolabsInsight(string $executable): void
    {
        $cmd = $executable . ' -n analyze --reference %s %s --api-token %s --user-uuid %s';

        $this->commandExecutor->executeCommand(
            $cmd,
            $this->build->getBranch(),
            $this->projectUuid,
            $this->authToken,
            $this->userUuid
        );

        $cmd = $executable . ' -n analysis --format pmd %s --api-token %s --user-uuid %s';

        $this->commandExecutor->executeCommand(
            $cmd,
            $this->projectUuid,
            $this->authToken,
            $this->userUuid
        );
    }

    /**
     * Returns a bool indicating if the error count can be considered a success.
     */
    private function wasLastExecSuccessful(int $errorCount): bool
    {
        if ($this->allowedWarnings !== -1 && $errorCount > $this->allowedWarnings) {
            return false;
        }

        return true;
    }
}
