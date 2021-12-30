<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Notification;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\ParameterBag;
use PHPCensor\Common\Plugin\Plugin;

/**
 * CampfireNotify Plugin - Allows Campfire API actions. Strongly based on icecube (http://labs.mimmin.com/icecube)
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author André Cianfarani <acianfa@gmail.com>
 */
class CampfireNotify extends Plugin
{
    private string $url = '';

    private string $authToken = '';

    private string $userAgent;

    private string $cookie;

    private bool $verbose = false;

    private string $roomId = '';

    private string $message = '';

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'campfire_notify';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $this->joinRoom($this->roomId);
        $status = (bool)$this->speak($this->variableInterpolator->interpolate($this->message), $this->roomId);
        $this->leaveRoom($this->roomId);

        return $status;
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
        $buildSettings    = (array)$this->buildSettings->get('campfire', []);
        $buildSettingsBag = new ParameterBag($buildSettings);

        $this->url       = (string)$buildSettingsBag->get('url', $this->url);
        $this->authToken = (string)$buildSettingsBag->get('auth_token', $this->authToken);
        $this->roomId    = (string)$buildSettingsBag->get('room_id', $this->roomId);

        $this->message = (string)$this->options->get('message', $this->message);
        $this->verbose = (bool)$this->options->get('verbose', $this->verbose);

        $this->userAgent = 'PHP Censor/' . $this->variableInterpolator->interpolate('%SYSTEM_VERSION%');
        $this->cookie    = "php-censor-cookie";
    }

    /**
     * Join a Campfire room.
     */
    private function joinRoom(string $roomId)
    {
        $this->getPageByPost('/room/' . $roomId . '/join.json');
    }

    /**
     * Leave a Campfire room.
     */
    public function leaveRoom(string $roomId)
    {
        $this->getPageByPost('/room/' . $roomId . '/leave.json');
    }

    /**
     * Send a message to a campfire room.
     */
    public function speak(string $message, string $roomId, bool $isPaste = false): string
    {
        $page = '/room/' . $roomId . '/speak.json';

        if ($isPaste) {
            $type = 'PasteMessage';
        } else {
            $type = 'TextMessage';
        }

        return $this->getPageByPost($page, ['message' => ['type' => $type, 'body' => $message]]);
    }

    /**
     * Make a request to Campfire.
     */
    private function getPageByPost(string $page, array $data = []): string
    {
        $url = $this->url . $page;
        // The new API allows JSON, so we can pass
        // PHP data structures instead of old school POST
        $json = \json_encode($data);

        // cURL init & config
        $handle = \curl_init();
        \curl_setopt($handle, CURLOPT_URL, $url);
        \curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($handle, CURLOPT_POST, 1);
        \curl_setopt($handle, CURLOPT_USERAGENT, $this->userAgent);
        \curl_setopt($handle, CURLOPT_VERBOSE, $this->verbose);
        \curl_setopt($handle, CURLOPT_FOLLOWLOCATION, 1);
        \curl_setopt($handle, CURLOPT_USERPWD, $this->authToken . ':x');
        \curl_setopt($handle, CURLOPT_HTTPHEADER, ["Content-type: application/json"]);
        \curl_setopt($handle, CURLOPT_COOKIEFILE, $this->cookie);

        \curl_setopt($handle, CURLOPT_POSTFIELDS, $json);
        $output = \curl_exec($handle);

        \curl_close($handle);

        // We tend to get one space with an otherwise blank response
        $output = \trim($output);

        if (\strlen($output)) {
            /* Responses are JSON. Decode it to a data structure */
            return \json_decode($output);
        }

        // Simple 200 OK response (such as for joining a room)
        return '';
    }
}
