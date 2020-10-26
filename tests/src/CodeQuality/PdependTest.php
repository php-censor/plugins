<?php

declare(strict_types = 1);

namespace Tests\PHPCensor\Plugins\CodeQuality;

use PHPCensor\Plugins\CodeQuality\Pdepend;
use PHPUnit\Framework\TestCase;

class PdependTest extends TestCase
{
    public function testGetName()
    {
        $this->assertEquals('pdepend', Pdepend::getName());
    }
}
