<?php

declare(strict_types = 1);

namespace Tests\PHPCensor\Plugins\Common;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Plugins\Common\Composer;
use PHPUnit\Framework\TestCase;

class ComposerTest extends TestCase
{
    public function testGetName()
    {
        $this->assertEquals('composer', Composer::getName());
    }

    /**
     * @dataProvider canExecuteProvider
     *
     * @param string $stage
     * @param bool $expectedResult
     */
    public function testCanExecuteWithNotExistsPath(string $stage, bool $expectedResult)
    {
        $build = $this->createMock(BuildInterface::class);
        $build
            ->method('getBuildPath')
            ->willReturn('/var/www/php-censor.local/runtime/builds/1/');

        $this->assertEquals(
            false,
            Composer::canExecute(
                $stage,
                $build
            )
        );
    }

    /**
     * @dataProvider canExecuteProvider
     *
     * @param string $stage
     * @param bool $expectedResult
     */
    public function testCanExecuteWithExistsPath(string $stage, bool $expectedResult)
    {
        $build = $this->createMock(BuildInterface::class);
        $build
            ->method('getBuildPath')
            ->willReturn(\rtrim(\dirname(\dirname(\dirname(__DIR__))), "/\\") . '/');

        $this->assertEquals(
            $expectedResult,
            Composer::canExecute(
                $stage,
                $build
            )
        );
    }

    public function canExecuteProvider(): array
    {
        return [
            [BuildInterface::STAGE_SETUP, true],
            [BuildInterface::STAGE_TEST, false],
            [BuildInterface::STAGE_DEPLOY, false],
            [BuildInterface::STAGE_COMPLETE, false],
            [BuildInterface::STAGE_SUCCESS, false],
            [BuildInterface::STAGE_FAILURE, false],
            [BuildInterface::STAGE_FIXED, false],
            [BuildInterface::STAGE_BROKEN, false],
        ];
    }
}
