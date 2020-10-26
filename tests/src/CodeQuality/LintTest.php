<?php

declare(strict_types = 1);

namespace Tests\PHPCensor\Plugins\CodeQuality;

use PHPCensor\Plugins\CodeQuality\Lint;
use PHPUnit\Framework\TestCase;

class LintTest extends TestCase
{
    public function testGetName()
    {
        $this->assertEquals('lint', Lint::getName());
    }
}
