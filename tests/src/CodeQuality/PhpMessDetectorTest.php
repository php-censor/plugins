<?php

declare(strict_types = 1);

namespace Tests\PHPCensor\Plugins\CodeQuality;

use PHPCensor\Plugins\CodeQuality\PhpMessDetector;
use PHPUnit\Framework\TestCase;

class PhpMessDetectorTest extends TestCase
{
    public function testGetName()
    {
        $this->assertEquals('php_mess_detector', PhpMessDetector::getName());
    }
}
