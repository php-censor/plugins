<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\Notification;

use PHPCensor\Common\Build\BuildInterface;
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
    /**
     * @var string
     */
    private $url = '';

    /**
     * @var string
     */
    private $authToken = '';

    /**
     * @var string
     */
    private $userAgent;

    /**
     * @var string
     */
    private $cookie;

    /**
     * @var bool
     */
    private $verbose = false;

    /**
     * @var string
     */
    private $roomId = '';

    /**
     * @var string
     */
    private $message = '';

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'campfire_notify';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $this->joinRoom($this->roomId);
        $status = (bool)$this->speak($this->variableInterpolator->interpolate($this->message), $this->roomId);
        $this->leaveRoom($this->roomId);

        return $status;
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
        $buildSettings    = (array)$this->buildSettings->get('campfire', []);
        $buildSettingsBag = new Plugin\ParameterBag($buildSettings);

        $this->url       = $buildSettingsBag->get('url', $this->url);
        $this->authToken = $buildSettingsBag->get('authToken', $this->authToken);
        $this->roomId    = $buildSettingsBag->get('roomId', $this->roomId);

        $this->message = (string)$this->options->get('message', $this->message);
        $this->verbose = (bool)$this->options->get('verbose', $this->verbose);

        $this->userAgent = 'PHP Censor/' . $this->variableInterpolator->interpolate('%SYSTEM_VERSION%');
        $this->cookie    = "php-censor-cookie";
    }

    /**
     * Join a Campfire room.
     *
     * @param string $roomId
     */
    private function joinRoom(string $roomId)
    {
        $this->getPageByPost('/room/' . $roomId . '/join.json');
    }

    /**
     * Leave a Campfire room.
     *
     * @param string $roomId
     */
    public function leaveRoom(string $roomId)
    {
        $this->getPageByPost('/room/' . $roomId . '/leave.json');
    }

    /**
     * Send a message to a campfire room.
     *
     * @param string $message
     * @param string $roomId
     * @param bool   $isPaste
     *
     * @return mixed
     */
    public function speak(string $message, string $roomId, bool $isPaste = false)
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
     *
     * @param string $page
     * @param array  $data
     *
     * @return mixed
     */
    private function getPageByPost(string $page, $data = [])
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
        return true;
    }
}
