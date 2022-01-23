<?php

declare(strict_types=1);

namespace Tests\PHPCensor\Plugins\Testing;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Plugins\Testing\Codeception;
use PHPUnit\Framework\TestCase;

class CodeceptionTest extends TestCase
{
    public function testGetName(): void
    {
        $this->assertEquals('codeception', Codeception::getName());
    }

    /**
     * @dataProvider canExecuteProvider
     */
    public function testCanExecute(string $stage, bool $expectedResult): void
    {
        $this->assertEquals(
            $expectedResult,
            Codeception::canExecute(
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
