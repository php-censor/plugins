<?php

declare(strict_types = 1);

namespace Tests\PHPCensor\Plugins\Testing;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Plugins\Testing\PhpUnit;
use PHPUnit\Framework\TestCase;

class PhpUnitTest extends TestCase
{
    public function testGetName()
    {
        $this->assertEquals('php_unit', PhpUnit::getName());
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
            PhpUnit::canExecute(
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
