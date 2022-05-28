<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Notification;

use GuzzleHttp\Client as HttpClient;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Exception\Exception;
use PHPCensor\Common\Plugin\Plugin;

/**
 * TelegramNotify Plugin.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author LEXASOFT <lexasoft83@gmail.com>
 */
class TelegramNotify extends Plugin
{
    private string $authToken = '';

    private string $message;

    private string $buildMsg;

    private array $recipients = [];

    private bool $sendLog = false;

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'telegram_notify';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $message = $this->buildMessage();
        $client  = new HttpClient();
        $url     = '/bot'. $this->authToken . '/sendMessage';

        foreach ($this->recipients as $chatId) {
            $params = [
                'chat_id'    => $chatId,
                'text'       => $message,
                'parse_mode' => 'Markdown',
            ];
            $client->post(('https://api.telegram.org' . $url), [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $params,
            ]);

            if ($this->sendLog) {
                $params = [
                    'chat_id'    => $chatId,
                    'text'       => $this->buildMsg,
                    'parse_mode' => 'Markdown',
                ];
                $client->post(('https://api.telegram.org' . $url), [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $params,
                ]);
            }
        }

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
        if (!$this->options->get('auth_token')) {
            throw new Exception("Not setting telegram 'auth_token'");
        }

        if (!$this->options->get('recipients')) {
            throw new Exception("Not setting telegram 'recipients'");
        }

        $this->authToken = (string)$this->options->get('auth_token', $this->authToken);
        $this->message   = '[%ICON_BUILD%] [%PROJECT_TITLE%](%PROJECT_LINK%)' .
            ' - [Build #%BUILD_ID%](%BUILD_LINK%) has finished ' .
            'for commit [%SHORT_COMMIT_ID% (%COMMITTER_EMAIL%)](%COMMIT_LINK%) ' .
            'on branch [%BRANCH%](%BRANCH_LINK%)';

        $this->message = (string)$this->options->get('message', $this->message);
        $this->sendLog = (bool)$this->options->get('send_log', $this->sendLog);

        $recipients = $this->options->get('recipients', []);
        if ($recipients) {
            if (\is_string($recipients)) {
                $this->recipients = [$recipients];
            } elseif (\is_array($recipients)) {
                $this->recipients = $recipients;
            }
        }
    }

    /**
     * Build message.
     */
    private function buildMessage(): string
    {
        $this->buildMsg = '';
        $buildIcon      = $this->build->isSuccessful() ? '✅' : '❌';
        $buildLog       = $this->build->getLog();
        $buildLog       = \str_replace(['[0;32m', '[0;31m', '[0m', '/[0m'], '', $buildLog);
        $buildMessages  = \explode('RUNNING PLUGIN: ', $buildLog);

        foreach ($buildMessages as $bm) {
            $pos      = (int)\mb_strpos($bm, "\n");
            $firstRow = \mb_substr($bm, 0, $pos);

            //skip long outputs
            if (\in_array($firstRow, ['slack_notify', 'php_loc', 'telegram_notify'], true)) {
                continue;
            }

            $this->buildMsg .= '*RUNNING PLUGIN: ' . $firstRow . "*\n";
            $this->buildMsg .= $firstRow === 'composer' ? '' : ('```' . \mb_substr($bm, $pos) . '```');
        }

        return $this->variableInterpolator->interpolate(
            \str_replace(['%ICON_BUILD%'], [$buildIcon], $this->message)
        );
    }
}
