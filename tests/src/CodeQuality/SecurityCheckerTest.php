<?php

declare(strict_types = 1);

namespace Tests\PHPCensor\Plugins\CodeQuality;

use PHPCensor\Plugins\CodeQuality\SecurityChecker;
use PHPUnit\Framework\TestCase;

class SecurityCheckerTest extends TestCase
{
    public function testGetName()
    {
        $this->assertEquals('security_checker', SecurityChecker::getName());
    }
}
