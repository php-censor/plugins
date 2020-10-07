<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\Notification;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Plugin\Plugin;

/**
 * XMPP Notification - Send notification for successful or failure build.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Alexandre Russo <dev.github@ange7.com>
 */
class XmppNotify extends Plugin
{
    /**
     * @var string Username of sender account xmpp
     */
    private $username = '';

    /**
     * @var string Alias server of sender account xmpp
     */
    private $server = '';

    /**
     * @var string Password of sender account xmpp
     */
    private $password = '';

    /**
     * @var string Alias for sender
     */
    private $alias = '';

    /**
     * @var bool Use tls
     */
    private $tls = false;

    /**
     * @var array List of recipients xmpp accounts
     */
    private $recipients = [];

    /**
     * @var string Mask to format date
     */
    private $dateFormat = '%c';

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'xmpp_notify';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);

        if (!\is_array($this->recipients) || 0 === \count($this->recipients)) {
            return false;
        }

        $configFile = $this->build->getBuildPath() . '.sendxmpprc';
        if (!$this->findConfigFile()) {
            \file_put_contents($configFile, $this->getConfigFormat());
            \chmod($configFile, 0600);
        }

        $tls = '';
        if ($this->tls) {
            $tls = ' -t';
        }

        $messageFile = $this->build->getBuildPath() . \uniqid('xmppmessage');
        if (!$this->buildMessage($messageFile)) {
            return false;
        }

        $cmd        = $executable . "%s -f %s -m %s %s";
        $recipients = \implode(' ', $this->recipients);

        $success = $this->commandExecutor->executeCommand($cmd, $tls, $configFile, $messageFile, $recipients);

        echo $this->commandExecutor->getLastCommandOutput();

        $this->commandExecutor->executeCommand("rm -rf " . $messageFile);

        return $success;
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
        $this->recipients = $this->options->get('recipients', $this->recipients);
        if ($this->recipients && \is_string($this->recipients)) {
            $this->recipients = [(string)$this->recipients];
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'sendxmpp',
        ];
    }

    /**
     * Get config format for sendxmpp config file
     *
     * @return string
     */
    private function getConfigFormat(): string
    {
        $conf = $this->username;
        if (!empty($this->server)) {
            $conf .= ';' . $this->server;
        }

        $conf .= ' ' . $this->password;

        if (!empty($this->alias)) {
            $conf .= ' ' . $this->alias;
        }

        return $conf;
    }

    /**
     * Find config file for sendxmpp binary (default is .sendxmpprc)
     *
     * @return bool
     */
    private function findConfigFile(): bool
    {
        if (\file_exists($this->build->getBuildPath() . '.sendxmpprc')) {
            if (
                \md5(\file_get_contents($this->build->getBuildPath() . '.sendxmpprc')) !== \md5($this->getConfigFormat())
            ) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * @param string $messageFile
     *
     * @return bool
     */
    private function buildMessage(string $messageFile): bool
    {
        if ($this->build->isSuccessful()) {
            $message = "✔ [" . $this->project->getTitle() . "] Build #" . $this->build->getId() . " successful";
        } else {
            $message = "✘ [" . $this->project->getTitle() . "] Build #" . $this->build->getId() . " failure";
        }

        $message .= ' (' . \strftime($this->dateFormat) . ')';

        return (bool)\file_put_contents($messageFile, $message);
    }
}
