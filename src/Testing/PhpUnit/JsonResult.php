<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\Testing\PhpUnit;

use PHPCensor\Common\Exception\Exception;

/**
 * Class JsonResult parses the results for the PhpUnitV2 plugin
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Pablo Tejada <pablo@ptejada.com>
 */
class JsonResult extends Result
{
    const EVENT_TEST        = 'test';
    const EVENT_TEST_START  = 'testStart';
    const EVENT_SUITE_START = 'suiteStart';

    protected $options;
    protected $arguments = [];

    /**
     * {@inheritdoc}
     */
    public function parse()
    {
        $rawResults = \file_get_contents($this->outputFile);

        $events = [];
        if ($rawResults && $rawResults[0] == '{') {
            $fixedJson = '[' . \str_replace('}{', '},{', $rawResults) . ']';
            $events    = \json_decode($fixedJson, true);
        } elseif ($rawResults) {
            $events = \json_decode($rawResults, true);
        }

        // Reset the parsing variables
        $this->results  = [];
        $this->errors   = [];
        $this->failures = 0;

        if ($events) {
            $started = null;
            foreach ($events as $event) {
                if (isset($event['event']) && $event['event'] == self::EVENT_TEST) {
                    $this->parseTestcase($event);
                    $started = null;
                } elseif (isset($event['event']) && $event['event'] == self::EVENT_TEST_START) {
                    $started = $event;
                }
            }
            if ($started) {
                $event = $started;
                $event['status'] = 'error';
                $event['message'] = 'Test is not finished';
                $event['output'] = '';
                $this->parseTestcase($event);
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function getSeverity($event): string
    {
        $status = $event['status'];
        switch ($status) {
            case 'fail':
                $severity = self::SEVERITY_FAIL;
                break;
            case 'error':
                if (\strpos($event['message'], 'Skipped') === 0 || \strpos($event['message'], 'Incomplete') === 0) {
                    $severity = self::SEVERITY_SKIPPED;
                } else {
                    $severity = self::SEVERITY_ERROR;
                }
                break;
            case 'pass':
            case 'warning':
                $severity = self::SEVERITY_PASS;
                break;
            default:
                throw new Exception("Unexpected PHPUnit test status: {$status}");
        }

        return $severity;
    }

    /**
     * {@inheritdoc}
     */
    protected function buildMessage($event): string
    {
        $message = $event['test'];
        if ($event['message']) {
            $message .= PHP_EOL . $event ['message'];
        }

        return $message;
    }

    /**
     * {@inheritdoc}
     */
    protected function buildTrace($event): array
    {
        $formattedTrace = [];
        if (!empty($event['trace'])) {
            foreach ($event['trace'] as $step) {
                $line             = \str_replace($this->buildPath, '', $step['file']) . ':' . $step['line'];
                $formattedTrace[] = $line;
            }
        }

        return $formattedTrace;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFileAndLine($event)
    {
        if (empty($event['trace'])) {
            return [
                'file' => '',
                'line' => '',
            ];
        }
        $firstTrace = \end($event['trace']);
        \reset($event['trace']);

        return [
            'file' => \str_replace($this->buildPath, '', $firstTrace['file']),
            'line' => $firstTrace['line'],
        ];
    }
}
