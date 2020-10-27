<?php

declare(strict_types = 1);

namespace Tests\PHPCensor\Plugins\CodeQuality;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Plugins\CodeQuality\PhpCpd;
use PHPUnit\Framework\TestCase;

class PhpCpdTest extends TestCase
{
    public function testGetName()
    {
        $this->assertEquals('php_cpd', PhpCpd::getName());
    }

    /**
     * @dataProvider canExecuteProvider
     *
     * @param string $stage
     * @param bool $expectedResult
     */
    public function testCanExecute(string $stage, bool $expectedResult)
    {
        $this->assertEquals(
            $expectedResult,
            PhpCpd::canExecute(
                $stage,
                $this->createMock(BuildInterface::class)
            )
        );
    }

    public function canExecuteProvider(): array
    {
        return [
            [BuildInterface::STAGE_SETUP, false],
            [BuildInterface::STAGE_TEST, true],
            [BuildInterface::STAGE_DEPLOY, false],
            [BuildInterface::STAGE_COMPLETE, false],
            [BuildInterface::STAGE_SUCCESS, false],
            [BuildInterface::STAGE_FAILURE, false],
            [BuildInterface::STAGE_FIXED, false],
            [BuildInterface::STAGE_BROKEN, false],
        ];
    }
}
