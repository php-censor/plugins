<?php

declare(strict_types=1);

namespace Tests\PHPCensor\Plugins\Notification;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Plugins\Notification\XmppNotify;
use PHPUnit\Framework\TestCase;

class XmppNotifyTest extends TestCase
{
    public function testGetName(): void
    {
        $this->assertEquals('xmpp_notify', XmppNotify::getName());
    }

    /**
     * @dataProvider canExecuteProvider
     */
    public function testCanExecute(string $stage, bool $expectedResult): void
    {
        $this->assertEquals(
            $expectedResult,
            XmppNotify::canExecute(
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
            [BuildInterface::STAGE_DEPLOY, false],
            [BuildInterface::STAGE_COMPLETE, true],
            [BuildInterface::STAGE_SUCCESS, true],
            [BuildInterface::STAGE_FAILURE, true],
            [BuildInterface::STAGE_FIXED, true],
            [BuildInterface::STAGE_BROKEN, true],
        ];
    }
}
