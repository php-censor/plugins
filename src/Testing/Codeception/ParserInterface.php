<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Testing\Codeception;

/**
 * Codeception Plugin
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Adam Cooper <adam@networkpie.co.uk>
 */
interface ParserInterface
{
    /**
     * @return array An array of key/value pairs for storage in the plugins result metadata
     */
    public function parse(): array;

    public function getTotalTests(): int;

    public function getTotalTimeTaken(): float;

    public function getTotalFailures(): int;
}
