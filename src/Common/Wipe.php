<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\Common;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Plugin\Plugin;

/**
 * Wipe Plugin - Wipes a folder.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Claus Due <claus@namelesscoder.net>
 */
class Wipe extends Plugin
{
    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'wipe';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        if ($this->directory === $this->build->getBuildPath()) {
            return true;
        }

        if (\is_dir($this->directory)) {
            $cmd = 'rm -Rf "%s"';

            return $this->commandExecutor->executeCommand($cmd, $this->directory);
        }

        return true;
    }

    protected function initPluginSettings(): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public static function canExecute(string $stage, BuildInterface $build): bool
    {
        return true;
    }
}
