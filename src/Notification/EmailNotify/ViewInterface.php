<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\Notification\EmailNotify;

/**
 * EmailNotify Plugin
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 */
interface ViewInterface
{
    /**
     * @param string $key
     *
     * @return bool
     */
    public function hasVariable(string $key): bool;

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function getVariable(string $key);

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return bool
     */
    public function setVariable(string $key, $value): bool;

    /**
     * @param array $value
     *
     * @return bool
     */
    public function setVariables(array $value): bool;

    /**
     * @return string
     */
    public function render(): string;
}
