<?php

declare(strict_types = 1);

namespace Tests\PHPCensor\Plugins\CodeQuality;

use PHPCensor\Plugins\CodeQuality\Phlint;
use PHPUnit\Framework\TestCase;

class PhlintTest extends TestCase
{
    public function testGetName()
    {
        $this->assertEquals('phlint', Phlint::getName());
    }
}
