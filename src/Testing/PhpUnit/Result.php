<?php

declare(strict_types = 1);

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
    const SEVERITY_PASS    = 'success';
    const SEVERITY_FAIL    = 'fail';
    const SEVERITY_ERROR   = 'error';
    const SEVERITY_SKIPPED = 'skipped';
    const SEVERITY_WARN    = self::SEVERITY_PASS;
    const SEVERITY_RISKY   = self::SEVERITY_PASS;

    /**
     * @var string
     */
    protected $outputFile;

    /**
     * @var string
     */
    protected $buildPath;

    /**
     * @var array
     */
    protected $results;

    /**
     * @var int
     */
    protected $failures = 0;

    /**
     * @var array
     */
    protected $errors = [];

    /**
     * @param string $outputFile
     * @param string $buildPath
     */
    public function __construct(string $outputFile, string $buildPath = '')
    {
        $this->outputFile = $outputFile;
        $this->buildPath  = $buildPath;
    }

    /**
     * Parse the results
     *
     * @return $this
     *
     * @throws Exception If fails to parse the output
     */
    abstract public function parse();

    /**
     * @param mixed $testcase
     *
     * @return string
     */
    abstract protected function getSeverity($testcase): string;

    /**
     * @param mixed $testcase
     *
     * @return string
     */
    abstract protected function buildMessage($testcase): string;

    /**
     * @param mixed $testcase
     *
     * @return array
     */
    abstract protected function buildTrace($testcase): array;

    /**
     * @param mixed $testcase
     *
     * @return mixed
     */
    protected function getFileAndLine($testcase)
    {
        return $testcase;
    }

    /**
     * @param mixed $testcase
     *
     * @return string
     */
    protected function getOutput($testcase): string
    {
        return $testcase['output'];
    }

    /**
     * @param mixed $testcase
     */
    protected function parseTestcase($testcase): void
    {
        $severity = $this->getSeverity($testcase);
        $pass = isset(\array_fill_keys([self::SEVERITY_PASS, self::SEVERITY_SKIPPED], true)[$severity]);
        $data = [
            'pass'     => $pass,
            'severity' => $severity,
            'message'  => $this->buildMessage($testcase),
            'trace'    => $pass ? [] : $this->buildTrace($testcase),
            'output'   => $this->getOutput($testcase),
        ];

        if (!$pass) {
            $this->failures++;
            $info = $this->getFileAndLine($testcase);
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
     *
     * @return int
     */
    public function getFailures(): int
    {
        return $this->failures;
    }

    /**
     * Get the tests with failing status
     *
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
