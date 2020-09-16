<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\Notification;

use PHPCensor\Common\Build\BuildInterface;
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
    /**
     * @var string
     */
    private $message;

    /**
     * @var string
     */
    private $server = '';

    /**
     * @var int
     */
    private $port = 6667;

    /**
     * @var string
     */
    private $room = '';

    /**
     * @var string
     */
    private $nick = '';

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'irc_notify';
    }

    /**
     * {@inheritdoc}
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
        $buildSettings    = $this->buildSettings->get('irc', []);
        $buildSettingsBag = new Plugin\ParameterBag($buildSettings);

        $this->server = $buildSettingsBag->get('server', $this->server);
        $this->port   = $buildSettingsBag->get('port', $this->port);
        $this->room   = $buildSettingsBag->get('room', $this->room);
        $this->nick   = $buildSettingsBag->get('nick', $this->nick);

        $this->message = '[%PROJECT_TITLE%](%PROJECT_LINK%)' .
            ' - [Build #%BUILD_ID%](%BUILD_LINK%) has finished ' .
            'for commit [%SHORT_COMMIT_ID% (%COMMITTER_EMAIL%)](%COMMIT_LINK%) ' .
            'on branch [%BRANCH%](%BRANCH_LINK%)';

        $this->message = $this->options->get('message', $this->message);
    }

    /**
     * @param resource $socket
     * @param array    $commands
     *
     * @return bool
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
            if (\preg_match('/^PING \\:([A-Z0-9]+)/', $response, $matches)) {
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
