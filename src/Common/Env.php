<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Common;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Plugin\Plugin;

/**
 * Environment variable plugin
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Steve Kamerman <stevekamerman@gmail.com>
 */
class Env extends Plugin
{
    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'env';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $success = true;
        foreach ($this->options->all() as $key => $value) {
            if (\is_numeric($key)) {
                // This allows the developer to specify env vars like " - FOO=bar" or " - FOO: bar"
                $envVar = \is_array($value)
                    ? (\key($value) . '=' . \current($value))
                    : $value;
            } else {
                // This allows the standard syntax: "FOO: bar"
                $envVar = "$key=$value";
            }

            if (!\putenv($this->variableInterpolator->interpolate($envVar))) {
                $success = false;
                $this->buildLogger->logFailure('Unable to set environment variable');
            }
        }

        return $success;
    }

    /**
     * {@inheritDoc}
     */
    public static function canExecute(string $stage, BuildInterface $build): bool
    {
        if (BuildInterface::STAGE_SETUP === $stage) {
            return true;
        }

        return false;
    }
}
