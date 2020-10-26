<?php

declare(strict_types = 1);

namespace Tests\PHPCensor\Plugins\CodeQuality;

use PHPCensor\Plugins\CodeQuality\SensiolabsInsight;
use PHPUnit\Framework\TestCase;

class SensiolabsInsightTest extends TestCase
{
    public function testGetName()
    {
        $this->assertEquals('sensiolabs_insight', SensiolabsInsight::getName());
    }
}
