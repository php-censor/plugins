<?php

declare(strict_types = 1);

namespace Tests\PHPCensor\Plugins\CodeQuality;

use PHPCensor\Plugins\CodeQuality\PhpLoc;
use PHPUnit\Framework\TestCase;

class PhpLocTest extends TestCase
{
    public function testGetName()
    {
        $this->assertEquals('php_loc', PhpLoc::getName());
    }
}
