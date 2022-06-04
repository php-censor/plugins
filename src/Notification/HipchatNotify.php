<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Notification;

use HipChat\HipChat;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Exception\Exception;
use PHPCensor\Common\Plugin\Plugin;

/**
 * HipchatNotify Plugin
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author James Inman <james@jamesinman.co.uk>
 */
class HipchatNotify extends Plugin
{
    private string $authToken;

    private string $color = 'yellow';

    private bool $notify = false;

    private string $message = '%PROJECT_TITLE% built at %BUILD_LINK%';

    /**
     * @var string|string[]
     */
    private $room;

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'hipchat_notify';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $hipChat = new HipChat($this->authToken);
        $message = $this->variableInterpolator->interpolate($this->message);

        $result = true;
        if (\is_array($this->room)) {
            foreach ($this->room as $room) {
                if (!$hipChat->message_room($room, 'PHP Censor', $message, $this->notify, $this->color)) {
                    $result = false;
                }
            }
        } else {
            if (!$hipChat->message_room($this->room, 'PHP Censor', $message, $this->notify, $this->color)) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public static function canExecute(string $stage, BuildInterface $build): bool
    {
        if (\in_array($stage, [
            BuildInterface::STAGE_BROKEN,
            BuildInterface::STAGE_COMPLETE,
            BuildInterface::STAGE_FAILURE,
            BuildInterface::STAGE_FIXED,
            BuildInterface::STAGE_SUCCESS,
        ], true)) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    protected function initPluginSettings(): void
    {
        if (!$this->options->all() || !$this->options->get('auth_token') || !$this->options->get('room')) {
            throw new Exception("Please define 'room' and 'auth_token' for HipchatNotify plugin!");
        }

        $this->authToken = $this->variableInterpolator->interpolate((string)$this->options->get('auth_token'));
        $this->room      = (string)$this->options->get('room');
        $this->message   = (string)$this->options->get('message', $this->message);
        $this->color     = (string)$this->options->get('color', $this->color);
        $this->notify    = (bool)$this->options->get('notify', $this->notify);
    }
}
