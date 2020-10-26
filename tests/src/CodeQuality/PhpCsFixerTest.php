<?php

declare(strict_types = 1);

namespace Tests\PHPCensor\Plugins\CodeQuality;

use PHPCensor\Plugins\CodeQuality\PhpCsFixer;
use PHPUnit\Framework\TestCase;

class PhpCsFixerTest extends TestCase
{
    public function testGetName()
    {
        $this->assertEquals('php_cs_fixer', PhpCsFixer::getName());
    }
}
