<?php

declare(strict_types = 1);

namespace Tests\PHPCensor\Plugins\CodeQuality;

use PHPCensor\Plugins\CodeQuality\PhpDocblockChecker;
use PHPUnit\Framework\TestCase;

class PhpDocblockCheckerTest extends TestCase
{
    public function testGetName()
    {
        $this->assertEquals('php_docblock_checker', PhpDocblockChecker::getName());
    }
}
