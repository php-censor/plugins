<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\CodeQuality;

use PHPCensor\Common\Build\BuildErrorInterface;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Exception\Exception;
use PHPCensor\Common\Plugin\Plugin;
use PHPCensor\Common\Plugin\ZeroConfigPluginInterface;

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
    private int $allowedWarnings = 0;

    private string $binaryType = 'symfony';

    /**
     * @var string[]
     */
    private array $allowedBinaryTypes = [
        'symfony',
        'local-php-security-checker',
    ];

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'security_checker';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $success = true;

        $composerLockFile = $this->build->getBuildPath() . 'composer.lock';
        if (!\is_file($composerLockFile)) {
            throw new Exception('Lock file (composer.lock) does not exist.');
        }

        if ('symfony' === $this->binaryType) {
            $cmd        = '%s check:security --format=json --dir=%s';
            $executable = $this->commandExecutor->findBinary(['symfony']);
        } else {
            $cmd        = '%s --format=json --path="%s"';
            $executable = $this->commandExecutor->findBinary(['local-php-security-checker']);
        }

        if (!$this->build->isDebug()) {
            $this->commandExecutor->disableCommandOutput();
        }

        // works with dir, composer.lock, composer.json
        $this->commandExecutor->executeCommand($cmd, $executable, $composerLockFile);

        $this->commandExecutor->enableCommandOutput();

        $result = $this->commandExecutor->getLastCommandOutput();

        $warnings = \json_decode($result, true);

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

            if ($this->allowedWarnings !== -1 && (\count($warnings) > $this->allowedWarnings)) {
                $success = false;
            }
        } elseif (null === $warnings && $result) {
            throw new Exception('invalid json: '.$result);
        }

        return $success;
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    protected function initPluginSettings(): void
    {
        if ($this->options->has('zero_config') && $this->options->get('zero_config', false)) {
            $this->allowedWarnings = -1;
        }

        if (
            $this->options->has('binary_type') &&
            \in_array((string)$this->options->get('binary_type'), $this->allowedBinaryTypes, true)
        ) {
            $this->binaryType = (string)$this->options->get('binary_type');
        }

        $this->allowedWarnings = (int)$this->options->get('allowed_warnings', $this->allowedWarnings);
    }
}
