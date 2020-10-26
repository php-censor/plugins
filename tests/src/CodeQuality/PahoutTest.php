<?php

declare(strict_types = 1);

namespace Tests\PHPCensor\Plugins\CodeQuality;

use PHPCensor\Plugins\CodeQuality\Pahout;
use PHPUnit\Framework\TestCase;

class PahoutTest extends TestCase
{
    public function testGetName()
    {
        $this->assertEquals('pahout', Pahout::getName());
    }
}
