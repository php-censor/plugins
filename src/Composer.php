<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Plugin\Plugin;
use PHPCensor\Common\Plugin\ZeroConfigPluginInterface;

/**
 * Composer Plugin provides access to Composer functionality.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Dan Cryer <dan@block8.co.uk>
 */
class Composer extends Plugin implements ZeroConfigPluginInterface
{
    protected $action;
    protected $preferDist;
    protected $noDev;
    protected $ignorePlatformReqs;
    protected $preferSource;

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'composer';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);
        $cmd = $executable . ' --no-ansi --no-interaction ';

        if ($this->preferDist) {
            $cmd .= ' --prefer-dist';
        }

        if ($this->preferSource) {
            $cmd .= ' --prefer-source';
        }

        if ($this->noDev) {
            $cmd .= ' --no-dev';
        }

        if ($this->ignorePlatformReqs) {
            $cmd .= ' --ignore-platform-reqs';
        }

        $cmd .= ' --working-dir="%s" %s';

        return $this->commandExecutor->executeCommand($cmd, $this->directory, $this->action);
    }

    /**
     * {@inheritdoc}
     */
    public static function canExecuteOnStage(string $stage, BuildInterface $build): bool
    {
        $path = $build->getBuildPath() . 'composer.json';

        if (\file_exists($path) && BuildInterface::STAGE_SETUP === $stage) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function initPluginSettings(): void
    {
        $this->action             = $this->options->get('action', 'install');
        $this->preferDist         = (bool)$this->options->get('prefer_dist', false);
        $this->noDev              = (bool)$this->options->get('no_dev', false);
        $this->ignorePlatformReqs = (bool)$this->options->get('ignore_platform_reqs', false);

        if ($this->options->has('prefer_source')) {
            $this->preferDist   = false;
            $this->preferSource = (bool)$this->options->get('prefer_source', false);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'composer',
            'composer.phar',
        ];
    }
}
