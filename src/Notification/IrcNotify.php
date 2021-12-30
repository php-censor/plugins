<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Notification;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\ParameterBag;
use PHPCensor\Common\Plugin\Plugin;

/**
 * IRC Plugin - Sends a notification to an IRC channel.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Dan Cryer <dan@block8.co.uk>
 */
class IrcNotify extends Plugin
{
    private string $message;

    private string $server = '';

    private int $port = 6667;

    private string $room = '';

    private string $nick = '';

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'irc_notify';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        if (empty($this->server) || empty($this->room) || empty($this->nick)) {
            $this->buildLogger->logFailure('You must configure a server, room and nick.');

            return false;
        }

        $sock = \fsockopen($this->server, $this->port);
        \stream_set_timeout($sock, 1);

        $message = $this->variableInterpolator->interpolate($this->message);

        $connectCommands = [
            'USER ' . $this->nick . ' 0 * :' . $this->nick,
            'NICK ' . $this->nick,
        ];
        $this->executeIrcCommands($sock, $connectCommands);
        $this->executeIrcCommands($sock, [('JOIN ' . $this->room)]);
        $this->executeIrcCommands($sock, [('PRIVMSG ' . $this->room . ' :' . $message)]);

        \fclose($sock);

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
        $buildSettings    = (array)$this->buildSettings->get('irc_notify', []);
        $buildSettingsBag = new ParameterBag($buildSettings);

        $this->server = (string)$buildSettingsBag->get('server', $this->server);
        $this->port   = (int)$buildSettingsBag->get('port', $this->port);
        $this->room   = (string)$buildSettingsBag->get('room', $this->room);
        $this->nick   = (string)$buildSettingsBag->get('nick', $this->nick);

        $this->message = '[%PROJECT_TITLE%](%PROJECT_LINK%)' .
            ' - [Build #%BUILD_ID%](%BUILD_LINK%) has finished ' .
            'for commit [%SHORT_COMMIT_ID% (%COMMITTER_EMAIL%)](%COMMIT_LINK%) ' .
            'on branch [%BRANCH%](%BRANCH_LINK%)';

        $this->message = (string)$this->options->get('message', $this->message);
    }

    /**
     * @param resource $socket
     */
    private function executeIrcCommands($socket, array $commands): bool
    {
        foreach ($commands as $command) {
            \fputs($socket, $command . "\n");
        }

        $pingBack = false;

        // almost all servers expect pingback!
        while ($response = \fgets($socket)) {
            $matches = [];
            if (\preg_match('/^PING \\\\:([A-Z0-9]+)/', $response, $matches)) {
                $pingBack = $matches[1];
            }
        }

        if ($pingBack) {
            $command = 'PONG :' . $pingBack . "\n";

            \fputs($socket, $command);
        }

        return true;
    }
}
