<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\Notification;

use Maknz\Slack\Attachment;
use Maknz\Slack\AttachmentField;
use Maknz\Slack\Client as SlackClient;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Exception\Exception;
use PHPCensor\Common\Plugin\Plugin;

/**
 * SlackNotify Plugin
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Stephen Ball <phpci@stephen.rebelinblue.com>
 */
class SlackNotify extends Plugin
{
    /**
     * @var string
     */
    private $webHook;

    /**
     * @var string
     */
    private $room = '#php-censor';

    /**
     * @var string
     */
    private $username = 'PHP Censor';

    /**
     * @var string
     */
    private $message = '<%PROJECT_LINK%|%PROJECT_TITLE%> - <%BUILD_LINK%|Build #%BUILD_ID%> has finished for commit '
        . '<%COMMIT_LINK%|%SHORT_COMMIT_ID% (%COMMITTER_EMAIL%)> on branch <%BRANCH_LINK%|%BRANCH%>';

    /**
     * @var string|null
     */
    private $icon = null;

    /**
     * @var bool
     */
    private $showStatus = true;

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'slack_notify';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $body    = $this->variableInterpolator->interpolate($this->message);
        $client  = new SlackClient($this->webHook);
        $message = $client->createMessage();

        $message->setChannel($this->room);
        $message->setUsername($this->username);

        if (!empty($this->icon)) {
            $message->setIcon($this->icon);
        }

        // Include an attachment which shows the status and hide the message
        if ($this->showStatus) {
            $successfulBuild = $this->build->isSuccessful();

            if ($successfulBuild) {
                $status = 'Success';
                $color  = 'good';
            } else {
                $status = 'Failed';
                $color  = 'danger';
            }

            // Build up the attachment data
            $attachment = new Attachment([
                'fallback' => $body,
                'pretext'  => $body,
                'color'    => $color,
                'fields'   => [
                    new AttachmentField([
                        'title' => 'Status',
                        'value' => $status,
                        'short' => false,
                    ]),
                ],
            ]);

            $message->attach($attachment);

            $body = '';
        }

        $message->send($body);

        return true;
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
        if (!\is_array($this->options->all()) || !$this->options->get('webhook_url')) {
            throw new Exception("Please define the webhook_url for SlackNotify plugin!");
        }

        $this->webHook    = \trim($this->options->get('webhook_url'));
        $this->message    = $this->options->get('message', $this->message);
        $this->room       = $this->options->get('room', $this->room);
        $this->username   = $this->options->get('username', $this->username);
        $this->showStatus = $this->options->get('show_status', $this->showStatus);
        $this->icon       = $this->options->get('icon', $this->icon);
    }
}
