<?php

declare(strict_types = 1);

namespace Tests\PHPCensor\Plugins\CodeQuality;

use PHPCensor\Plugins\CodeQuality\PhpParallelLint;
use PHPUnit\Framework\TestCase;

class PhpParallelLintTest extends TestCase
{
    public function testGetName()
    {
        $this->assertEquals('php_parallel_lint', PhpParallelLint::getName());
    }
}
