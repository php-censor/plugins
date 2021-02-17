<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\CodeQuality;

use PHPCensor\Common\Build\BuildErrorInterface;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Exception\Exception;
use PHPCensor\Common\Plugin\Plugin;
use PHPCensor\Common\Plugin\ZeroConfigPluginInterface;
use SensioLabs\Security\SecurityChecker as SensiolabsSecurityChecker;

/**
 * SensioLabs Security Checker Plugin
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 */
class SecurityChecker extends Plugin implements ZeroConfigPluginInterface
{
    /**
     * @var int
     */
    private int $allowedWarnings = 0;

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'security_checker';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $success = true;
        if (\in_array('symfony', $this->binaryNames, true)) {
            $lockFile = $this->build->getBuildPath() . 'composer.lock';
            if (!\is_file($lockFile)) {
                throw new Exception('Lock file (composer.lock) does not exist.');
            }

            $cmd        = '%s check:security --format=json --dir=%s';
            $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);
            if (!$this->build->isDebug()) {
                $this->commandExecutor->disableCommandOutput();
            }

            // works with dir, composer.lock, composer.json
            $this->commandExecutor->executeCommand($cmd, $executable, $lockFile);

            $this->commandExecutor->enableCommandOutput();

            $result = $this->commandExecutor->getLastCommandOutput();
        } else {
            $checker = new SensiolabsSecurityChecker();
            $result  = $checker->check($this->build->getBuildPath() . 'composer.lock');
        }

        $warnings = \json_decode((string)$result, true);

        if ($warnings) {
            foreach ($warnings as $library => $warning) {
                foreach ($warning['advisories'] as $data) {
                    $this->buildErrorWriter->write(
                        $this->build->getId(),
                        self::getName(),
                        ($library . ' (' . $warning['version'] . ")\n" . $data['cve'] . ': ' . $data['title'] . "\n" . $data['link']),
                        BuildErrorInterface::SEVERITY_CRITICAL
                    );
                }
            }

            if ($this->allowedWarnings !== -1 && ($result->count() > $this->allowedWarnings)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public static function canExecute(string $stage, BuildInterface $build): bool
    {
        $path = $build->getBuildPath() . 'composer.lock';

        if (\file_exists($path) && $stage === BuildInterface::STAGE_TEST) {
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
    }
}
