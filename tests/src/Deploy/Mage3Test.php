<?php

declare(strict_types = 1);

namespace Tests\PHPCensor\Plugins\Deploy;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Plugins\Deploy\Mage3;
use PHPUnit\Framework\TestCase;

class Mage3Test extends TestCase
{
    public function testGetName(): void
    {
        $this->assertEquals('mage3', Mage3::getName());
    }

    /**
     * @dataProvider canExecuteProvider
     *
     * @param string $stage
     * @param bool $expectedResult
     */
    public function testCanExecute(string $stage, bool $expectedResult): void
    {
        $this->assertEquals(
            $expectedResult,
            Mage3::canExecute(
                $stage,
                $this->createMock(BuildInterface::class)
            )
        );
    }

    public function canExecuteProvider(): array
    {
        return [
            [BuildInterface::STAGE_SETUP, false],
            [BuildInterface::STAGE_TEST, false],
            [BuildInterface::STAGE_DEPLOY, true],
            [BuildInterface::STAGE_COMPLETE, false],
            [BuildInterface::STAGE_SUCCESS, false],
            [BuildInterface::STAGE_FAILURE, false],
            [BuildInterface::STAGE_FIXED, false],
            [BuildInterface::STAGE_BROKEN, false],
        ];
    }
}
