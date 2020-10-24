<?php

declare(strict_types = 1);

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
    /**
     * @var string
     */
    private $authToken;

    /**
     * @var string
     */
    private $color = 'yellow';

    /**
     * @var bool
     */
    private $notify = false;

    /**
     * @var string
     */
    private $message = '%PROJECT_TITLE% built at %BUILD_LINK%';

    /**
     * @var string|string[]
     */
    private $room;

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'hipchat_notify';
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    protected function initPluginSettings(): void
    {
        if (!$this->options->all() || !$this->options->get('authToken') || !$this->options->get('room')) {
            throw new Exception("Please define 'room' and 'authToken' for HipchatNotify plugin!");
        }

        $this->authToken = $this->options->get('authToken');
        $this->room      = $this->options->get('room');
        $this->message   = $this->options->get('message', $this->message);
        $this->color     = $this->options->get('color', $this->color);
        $this->notify    = (bool)$this->options->get('notify', $this->notify);
    }
}
