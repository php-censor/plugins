<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\CodeQuality;

use PHPCensor\Common\Build\BuildErrorInterface;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Plugin\Plugin;

/**
 * A pair programming partner for writing better PHP.
 * https://github.com/wata727/pahout
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Panagiotis Kosmidis <panoskosmidis87@gmail.com>
 */
class Pahout extends Plugin
{
    public const TAB = "\t";

    /**
     */
    private int $allowedWarnings = -1;

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'pahout';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);

        if (!$this->build->isDebug()) {
            $this->commandExecutor->disableCommandOutput();
        }

        $this->commandExecutor->executeCommand(
            'cd "%s" && ' . $executable . ' %s --format=json',
            $this->build->getBuildPath(),
            $this->directory
        );
        $this->commandExecutor->enableCommandOutput();

        $success = true;

        list($files, $hints) = $this->processReport($this->commandExecutor->getLastCommandOutput());

        if (0 < \count($hints)) {
            if (-1 !== $this->allowedWarnings && \count($hints) > $this->allowedWarnings) {
                $success = false;
            }

            foreach ($hints as $hint) {
                $this->buildLogger->logFailure('HINT: ' . $hint['full_message'] . PHP_EOL);

                $this->buildErrorWriter->write(
                    $this->build->getId(),
                    self::getName(),
                    $hint['message'],
                    BuildErrorInterface::SEVERITY_LOW,
                    $hint['file'],
                    (int)$hint['line_from']
                );
            }
        }

        if ($success) {
            $this->buildLogger->logSuccess('Awesome! There is nothing from me to teach you!');
        }

        $this->buildLogger->logNormal(\sprintf('%d files checked, %d hints detected.', \count($files), \count($hints)));

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
        $this->allowedWarnings = (int)$this->options->get('allowed_warnings', $this->allowedWarnings);
    }

    /**
     * {@inheritDoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'pahout',
            'pahout.phar',
        ];
    }

    private function processReport(string $output): array
    {
        $data  = \json_decode(\trim($output), true);
        $hints = [];
        $files = [];
        if (!empty($data) && \is_array($data) && isset($data['hints'])) {
            $files = $data['files'];

            foreach ($data['hints'] as $hint) {
                $hints[] = [
                    'full_message' => \vsprintf('%s:%d' . PHP_EOL . self::TAB . '%s: %s [%s]', [
                        $hint['filename'],
                        $hint['lineno'],
                        $hint['type'],
                        $hint['message'],
                        $hint['link'],
                    ]),
                    'message'   => $hint['message'],
                    'file'      => $hint['filename'],
                    'line_from' => $hint['lineno'],
                ];
            }
        }

        return [$files, $hints];
    }
}
