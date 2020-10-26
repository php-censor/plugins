<?php

declare(strict_types = 1);

namespace Tests\PHPCensor\Plugins\CodeQuality;

use PHPCensor\Plugins\CodeQuality\Psalm;
use PHPUnit\Framework\TestCase;

class PsalmTest extends TestCase
{
    public function testGetName()
    {
        $this->assertEquals('psalm', Psalm::getName());
    }
}
