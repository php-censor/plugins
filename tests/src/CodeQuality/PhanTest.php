<?php

declare(strict_types = 1);

namespace Tests\PHPCensor\Plugins\CodeQuality;

use PHPCensor\Plugins\CodeQuality\Phan;
use PHPUnit\Framework\TestCase;

class PhanTest extends TestCase
{
    public function testGetName()
    {
        $this->assertEquals('phan', Phan::getName());
    }
}
