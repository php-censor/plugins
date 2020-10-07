<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\Notification;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Exception\Exception;
use PHPCensor\Common\Plugin\Plugin;

/**
 * WebhookNotify Plugin
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Lee Willis (Ademti Software): https://www.ademti-software.co.uk
 */
class WebhookNotify extends Plugin
{
    /**
     * @var string The URL to send the webhook to.
     */
    private $url;

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'webhook_notify';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $payload = [
            'project_id'      => $this->project->getId(),
            'project_title'   => $this->project->getTitle(),
            'build_id'        => $this->build->getId(),
            'commit_id'       => $this->build->getCommitId(),
            'short_commit_id' => \substr($this->build->getCommitId(), 0, 7),
            'branch'          => $this->build->getBranch(),
            'branch_link'     => $this->build->getBranchLink(),
            'committer_email' => $this->build->getCommitterEmail(),
            'commit_message'  => $this->build->getCommitMessage(),
            'commit_link'     => $this->build->getCommitLink(),
            'build_link'      => $this->variableInterpolator->interpolate('%BUILD_LINK%'),
            'project_link'    => $this->variableInterpolator->interpolate('%PROJECT_LINK%'),
            'status_code'     => $this->build->getStatus(),
            'readable_status' => $this->getReadableStatus(),
        ];


        try {
            $version   = $this->variableInterpolator->interpolate('%APP_VERSION%');
            $userAgent = 'PHP Censor/' . $version;
            $client    = new HttpClient([
                'headers' => [
                    'User-Agent' => $userAgent,
                ],
            ]);
            $client->request(
                'POST',
                $this->url,
                ['json' => $payload]
            );
        } catch (GuzzleException $e) {
            return false;
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
        if (!$this->options->all()) {
            throw new Exception("Please configure the options for the WebhookNotify plugin!");
        }

        if (!$this->options->get('url')) {
            throw new Exception("Please define the url for WebhookNotify plugin!");
        }

        $this->url = \trim((string)$this->options->get('url', ''));
    }

    /**
     * @return string
     */
    private function getReadableStatus(): string
    {
        switch ($this->build->getStatus()) {
            case BuildInterface::STATUS_PENDING:
                return 'Pending';
            case BuildInterface::STATUS_RUNNING:
                return 'Running';
            case BuildInterface::STATUS_SUCCESS:
                return 'Successful';
            case BuildInterface::STATUS_FAILED:
                return 'Failed';
        }

        return \sprintf('Unknown (%d)', $this->build->getStatus());
    }
}
