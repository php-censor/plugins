<?php

declare(strict_types=1);

namespace Tests\PHPCensor\Plugins\Deploy;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Plugins\Deploy\Mage;
use PHPUnit\Framework\TestCase;

class MageTest extends TestCase
{
    public function testGetName(): void
    {
        $this->assertEquals('mage', Mage::getName());
    }

    /**
     * @dataProvider canExecuteProvider
     */
    public function testCanExecute(string $stage, bool $expectedResult): void
    {
        $this->assertEquals(
            $expectedResult,
            Mage::canExecute(
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
