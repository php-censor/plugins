<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins;

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
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'env';
    }

    /**
     * {@inheritdoc}
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
}
