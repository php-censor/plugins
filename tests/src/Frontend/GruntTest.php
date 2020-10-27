<?php

declare(strict_types = 1);

namespace Tests\PHPCensor\Plugins\Frontend;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Plugins\Frontend\Grunt;
use PHPUnit\Framework\TestCase;

class GruntTest extends TestCase
{
    public function testGetName()
    {
        $this->assertEquals('grunt', Grunt::getName());
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
            Grunt::canExecute(
                $stage,
                $this->createMock(BuildInterface::class)
            )
        );
    }

    public function canExecuteProvider(): array
    {
        return [
            [BuildInterface::STAGE_SETUP, true],
            [BuildInterface::STAGE_TEST, true],
            [BuildInterface::STAGE_DEPLOY, true],
            [BuildInterface::STAGE_COMPLETE, true],
            [BuildInterface::STAGE_SUCCESS, true],
            [BuildInterface::STAGE_FAILURE, true],
            [BuildInterface::STAGE_FIXED, true],
            [BuildInterface::STAGE_BROKEN, true],
        ];
    }
}
