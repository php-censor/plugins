<?php

declare(strict_types=1);

namespace Tests\PHPCensor\Plugins\Database;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Plugins\Database\Pgsql;
use PHPUnit\Framework\TestCase;

class PgsqlTest extends TestCase
{
    public function testGetName(): void
    {
        $this->assertEquals('pgsql', Pgsql::getName());
    }

    /**
     * @dataProvider canExecuteProvider
     */
    public function testCanExecute(string $stage, bool $expectedResult): void
    {
        $this->assertEquals(
            $expectedResult,
            Pgsql::canExecute(
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
