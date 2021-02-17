<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\Notification;

use FlowdockClient\Api\Push\Push;
use FlowdockClient\Api\Push\TeamInboxMessage;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Exception\Exception;
use PHPCensor\Common\Plugin\Plugin;

/**
 * FlowdockNotify Plugin
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Petr Cervenka <petr@nanosolutions.io>
 */
class FlowdockNotify extends Plugin
{
    /**
     * @var string
     */
    private string $authToken;

    /**
     * @var string
     */
    private string $email = 'PHP Censor';

    /**
     * @var string
     */
    private string $message = 'Build %BUILD_ID% has finished for commit <a href="%COMMIT_LINK%">%SHORT_COMMIT_ID%</a>
(%COMMITTER_EMAIL%)> on branch <a href="%BRANCH_LINK%">%BRANCH%</a>';

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'flowdock_notify';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $message         = $this->variableInterpolator->interpolate($this->message);
        $successfulBuild = $this->build->isSuccessful() ? 'Success' : 'Failed';
        $push            = new Push($this->authToken);
        $flowMessage     = TeamInboxMessage::create()
            ->setSource("PHPCensor")
            ->setFromAddress($this->email)
            ->setFromName($this->project->getTitle())
            ->setSubject($successfulBuild)
            ->setTags(['#ci'])
            ->setLink($this->build->getBranchLink())
            ->setContent($message);

        if (!$push->sendTeamInboxMessage($flowMessage, ['connect_timeout' => 5000, 'timeout' => 5000])) {
            throw new Exception(
                \sprintf('Flowdock Failed: %s', $flowMessage->getResponseErrors())
            );
        }

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
        if (!$this->options->all() || !$this->options->get('auth_token')) {
            throw new Exception("Please define the 'auth_token' for FlowdockNotify plugin!");
        }

        $this->authToken = \trim($this->options->get('auth_token'));
        $this->message   = (string)$this->options->get('message', $this->message);
        $this->email     = (string)$this->options->get('email', $this->email);
    }
}
