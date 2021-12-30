<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Deploy;

use GuzzleHttp\Client as HttpClient;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Plugin\Plugin;

/**
 * Integration with Deployer: https://github.com/rebelinblue/deployer
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Dan Cryer <dan@block8.co.uk>
 */
class Deployer extends Plugin
{
    private string $webhookUrl = '';
    private string $reason = '';
    private bool $updateOnly = true;

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'deployer';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        if (empty($this->webhookUrl)) {
            $this->buildLogger->logFailure('You must specify a webhook URL.');

            return false;
        }

        $client   = new HttpClient();
        $response = $client->post(
            $this->webhookUrl,
            [
                'form_params' => [
                    'reason'      => $this->variableInterpolator->interpolate($this->reason),
                    'source'      => 'PHP Censor',
                    'url'         => $this->variableInterpolator->interpolate('%BUILD_LINK%'),
                    'branch'      => $this->variableInterpolator->interpolate('%BRANCH%'),
                    'commit'      => $this->variableInterpolator->interpolate('%COMMIT_ID%'),
                    'update_only' => $this->updateOnly,
                ],
            ]
        );

        $status = (int)$response->getStatusCode();

        return ($status >= 200 && $status < 300);
    }

    /**
     * {@inheritDoc}
     */
    public static function canExecute(string $stage, BuildInterface $build): bool
    {
        if (BuildInterface::STAGE_DEPLOY === $stage) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    protected function initPluginSettings(): void
    {
        $this->reason = 'PHP Censor Build #%BUILD_ID% - %COMMIT_MESSAGE%';

        $this->webhookUrl = (string)$this->options->get('webhook_url', $this->webhookUrl);
        $this->reason     = (string)$this->options->get('reason', $this->reason);
        $this->updateOnly = (bool)$this->options->get('update_only', $this->updateOnly);
    }
}
