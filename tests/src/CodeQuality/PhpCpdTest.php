<?php

declare(strict_types = 1);

namespace Tests\PHPCensor\Plugins\CodeQuality;

use PHPCensor\Plugins\CodeQuality\PhpCpd;
use PHPUnit\Framework\TestCase;

class PhpCpdTest extends TestCase
{
    public function testGetName()
    {
        $this->assertEquals('php_cpd', PhpCpd::getName());
    }
}
