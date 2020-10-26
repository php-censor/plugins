<?php

declare(strict_types = 1);

namespace Tests\PHPCensor\Plugins\CodeQuality;

use PHPCensor\Plugins\CodeQuality\TechnicalDebt;
use PHPUnit\Framework\TestCase;

class TechnicalDebtTest extends TestCase
{
    public function testGetName()
    {
        $this->assertEquals('technical_debt', TechnicalDebt::getName());
    }
}
