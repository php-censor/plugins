<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Testing\PhpUnit;

use PHPCensor\Common\Exception\Exception;

/**
 * Class Result parses the results for the PhpUnitV2 plugin
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Pablo Tejada <pablo@ptejada.com>
 */
abstract class Result
{
    public const SEVERITY_PASS    = 'success';
    public const SEVERITY_FAIL    = 'fail';
    public const SEVERITY_ERROR   = 'error';
    public const SEVERITY_SKIPPED = 'skipped';
    public const SEVERITY_WARN    = self::SEVERITY_PASS;
    public const SEVERITY_RISKY   = self::SEVERITY_PASS;

    protected array $results;

    protected int $failures = 0;

    protected array $errors = [];

    public function __construct(
        protected string $outputFile,
        protected string $buildPath = ''
    ) {
    }

    /**
     * Parse the results
     *
     * @return $this
     *
     * @throws Exception If fails to parse the output
     */
    abstract public function parse(): Result;

    abstract protected function getSeverity(mixed $testCase): string;

    abstract protected function buildMessage(mixed $testCase): string;

    abstract protected function buildTrace(mixed $testCase): array;

    protected function getFileAndLine(mixed $testCase): array
    {
        return $testCase;
    }

    protected function getOutput(mixed $testCase): string
    {
        return $testCase['output'];
    }

    protected function parseTestcase(mixed $testCase): void
    {
        $severity = $this->getSeverity($testCase);
        $pass = isset(\array_fill_keys([self::SEVERITY_PASS, self::SEVERITY_SKIPPED], true)[$severity]);
        $data = [
            'pass'     => $pass,
            'severity' => $severity,
            'message'  => $this->buildMessage($testCase),
            'trace'    => $pass ? [] : $this->buildTrace($testCase),
            'output'   => $this->getOutput($testCase),
        ];

        if (!$pass) {
            $this->failures++;
            $info = $this->getFileAndLine($testCase);
            $this->errors[] = [
                'message'  => $data['message'],
                'severity' => $severity,
                'file'     => $info['file'],
                'line'     => $info['line'],
            ];
        }

        $this->results[] = $data;
    }

    /**
     * Get the parse results
     *
     * @return string[]
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Get the total number of failing tests
     */
    public function getFailures(): int
    {
        return $this->failures;
    }

    /**
     * Get the tests with failing status
     *
     * @return array[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
