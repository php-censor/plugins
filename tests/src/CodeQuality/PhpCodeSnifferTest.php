<?php

declare(strict_types = 1);

namespace Tests\PHPCensor\Plugins\CodeQuality;

use PHPCensor\Plugins\CodeQuality\PhpCodeSniffer;
use PHPUnit\Framework\TestCase;

class PhpCodeSnifferTest extends TestCase
{
    public function testGetName()
    {
        $this->assertEquals('php_code_sniffer', PhpCodeSniffer::getName());
    }
}
