<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\CodeQuality;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Exception\Exception;
use PHPCensor\Common\Plugin\Plugin;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Pdepend Plugin - Allows Pdepend report
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Johan van der Heide <info@japaveh.nl>
 */
class Pdepend extends Plugin
{
    /**
     * @var string
     */
    private $buildDirectory;

    /**
     * @var string
     */
    private $buildBranchDirectory;

    /**
     * @var string File where the summary.xml is stored
     */
    private $summary = 'summary.xml';

    /**
     * @var string File where the chart.svg is stored
     */
    private $chart = 'chart.svg';

    /**
     * @var string File where the pyramid.svg is stored
     */
    private $pyramid = 'pyramid.svg';

    /**
     * @var string
     */
    private $buildLocation;

    /**
     * @var string
     */
    private $buildBranchLocation;

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'pdepend';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);

        $allowPublicArtifacts = false;
        $applicationConfig    = $this->application->getConfig();
        if (
            isset($applicationConfig['php-censor']['build']['allow_public_artifacts']) &&
            $applicationConfig['php-censor']['build']['allow_public_artifacts']
        ) {
            $allowPublicArtifacts = true;
        }

        $fileSystem = new Filesystem();

        if (!$fileSystem->exists($this->buildLocation)) {
            $fileSystem->mkdir($this->buildLocation, (0777 & ~\umask()));
        }

        if (!\is_writable($this->buildLocation)) {
            throw new Exception(\sprintf(
                'The location %s is not writable or does not exist.',
                $this->buildLocation
            ));
        }

        $cmd = 'cd "%s" && ' . $executable . ' --summary-xml="%s" --jdepend-chart="%s" --overview-pyramid="%s" %s "%s"';

        $ignores = '';
        if (\count($this->ignores)) {
            $ignores = \sprintf(' --ignore="%s"', \implode(',', $this->ignores));
        }

        $success = $this->commandExecutor->executeCommand(
            $cmd,
            $this->build->getBuildPath(),
            $this->buildLocation . '/' . $this->summary,
            $this->buildLocation . '/' . $this->chart,
            $this->buildLocation . '/' . $this->pyramid,
            $ignores,
            $this->directory
        );

        if (!$allowPublicArtifacts) {
            $fileSystem->remove($this->buildLocation);
        }
        if ($allowPublicArtifacts && \file_exists($this->buildLocation)) {
            $fileSystem->remove($this->buildBranchLocation);
            $fileSystem->mirror($this->buildLocation, $this->buildBranchLocation);
        }

        if ($allowPublicArtifacts && $success) {
            $this->buildLogger->logSuccess(
                \sprintf(
                    "\nPdepend successful build report.\nYou can use report for this build for inclusion in the readme.md file:\n%s,\n![Chart](%s \"Pdepend Chart\") and\n![Pyramid](%s \"Pdepend Pyramid\")\n\nOr report for last build in the branch:\n%s,\n![Chart](%s \"Pdepend Chart\") and\n![Pyramid](%s \"Pdepend Pyramid\")\n",
                    $this->application->getArtifactsLink() . 'pdepend/' . $this->buildDirectory . '/' . $this->summary,
                    $this->application->getArtifactsLink() . 'pdepend/' . $this->buildDirectory . '/' . $this->chart,
                    $this->application->getArtifactsLink() . 'pdepend/' . $this->buildDirectory . '/' . $this->pyramid,
                    $this->application->getArtifactsLink() . 'pdepend/' . $this->buildBranchDirectory . '/' . $this->summary,
                    $this->application->getArtifactsLink() . 'pdepend/' . $this->buildBranchDirectory . '/' . $this->chart,
                    $this->application->getArtifactsLink() . 'pdepend/' . $this->buildBranchDirectory . '/' . $this->pyramid
                )
            );
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public static function canExecute(string $stage, BuildInterface $build): bool
    {
        if (BuildInterface::STAGE_TEST === $stage) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function initPluginSettings(): void
    {
        $this->buildDirectory       = $this->build->getBuildDirectory();
        $this->buildBranchDirectory = $this->build->getBuildBranchDirectory();

        $this->buildLocation       = $this->application->getArtifactsPath() . 'pdepend/' . $this->buildDirectory;
        $this->buildBranchLocation = $this->application->getArtifactsPath() . 'pdepend/' . $this->buildBranchDirectory;
    }

    /**
     * {@inheritdoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'pdepend',
            'pdepend.phar',
        ];
    }
}