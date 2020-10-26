<?php

declare(strict_types = 1);

namespace Tests\PHPCensor\Plugins\CodeQuality;

use PHPCensor\Plugins\CodeQuality\PhpTalLint;
use PHPUnit\Framework\TestCase;

class PhpTalLintTest extends TestCase
{
    public function testGetName()
    {
        $this->assertEquals('php_tal_lint', PhpTalLint::getName());
    }
}
