<?php

declare(strict_types=1);

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
    private string $webHook = '';

    private string $room = '#php-censor';

    private string $username = 'PHP Censor';

    private string $message = '<%PROJECT_LINK%|%PROJECT_TITLE%> - <%BUILD_LINK%|Build #%BUILD_ID%> has finished for commit '
        . '<%COMMIT_LINK%|%SHORT_COMMIT_ID% (%COMMITTER_EMAIL%)> on branch <%BRANCH_LINK%|%BRANCH%>';

    private string $icon = '';

    private bool $showStatus = true;

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'slack_notify';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $body    = $this->variableInterpolator->interpolate($this->message);
        $client  = new SlackClient($this->webHook);
        $message = $client->createMessage();

        $message->setChannel($this->room);
        $message->setUsername($this->username);

        if ($this->icon) {
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
        if (!$this->options->all() || !$this->options->get('webhook_url')) {
            throw new Exception("Please define the webhook_url for SlackNotify plugin!");
        }

        $this->webHook    = \trim((string)$this->options->get('webhook_url', ''));
        $this->message    = (string)$this->options->get('message', $this->message);
        $this->room       = (string)$this->options->get('room', $this->room);
        $this->username   = (string)$this->options->get('username', $this->username);
        $this->showStatus = (bool)$this->options->get('show_status', $this->showStatus);
        $this->icon       = (string)$this->options->get('icon', $this->icon);
    }
}
