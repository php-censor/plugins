<?php

declare(strict_types = 1);

namespace Tests\PHPCensor\Plugins\CodeQuality;

use PHPCensor\Plugins\CodeQuality\PhpStan;
use PHPUnit\Framework\TestCase;

class PhpStanTest extends TestCase
{
    public function testGetName()
    {
        $this->assertEquals('php_stan', PhpStan::getName());
    }
}
