<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\Testing\PhpUnit;

use PHPCensor\Common\Exception\Exception;

/**
 * Class JunitResult parses the results for the PhpUnitV2 plugin
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Pablo Tejada <pablo@ptejada.com>
 */
class JunitResult extends Result
{
    /**
     * {@inheritdoc}
     */
    public function parse(): JunitResult
    {
        // Reset the parsing variables
        $this->results  = [];
        $this->errors   = [];
        $this->failures = 0;

        $suites = $this->loadResultFile();

        if ($suites) {
            foreach ($suites->xpath('//testcase') as $testCase) {
                $this->parseTestcase($testCase);
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function getSeverity($testCase): string
    {
        $severity = self::SEVERITY_PASS;
        foreach ($testCase as $child) {
            switch ($child->getName()) {
                case 'failure':
                    $severity = self::SEVERITY_FAIL;
                    break 2;
                case 'error':
                    if ('PHPUnit\Framework\RiskyTestError' == $child['type']) { // == because conversion to string is desired
                        $severity = self::SEVERITY_RISKY;
                    } else {
                        $severity = self::SEVERITY_ERROR;
                    }
                    break 2;
                case 'skipped':
                    // skipped and ignored, can not distinguish
                    $severity = self::SEVERITY_SKIPPED;
                    break 2;
                case 'warning':
                    $severity = self::SEVERITY_WARN;
                    break 2;
                case 'system-out':
                case 'system-err':
                    // not results
                    continue 2;
                default:
                    $severity = 'UNKNOWN RESULT TYPE: '.$child->getName();
                    break 2;
            }
        }

        return $severity;
    }

    /**
     * {@inheritdoc}
     */
    protected function buildMessage($testCase): string
    {
        $tracePos = -1;
        $msg = $this->getMessageTrace($testCase);
        if ('' !== $msg) {
            //strip trace
            $trPos = \strrpos($msg, "\n\n");
            if (false !== $trPos) {
                $tracePos = $trPos;
                $msg = \substr($msg, 0, $trPos);
            }
        }
        if ('' === $msg) {
            $msg = $testCase['class'].'::'.$testCase['name'];
        }
        $testCase['_tracePos'] = $tracePos; // will be converted to string

        return $msg;
    }

    /**
     * {@inheritdoc}
     */
    protected function getOutput($testCase): string
    {
        return (string)$testCase->{'system-out'};
    }

    /**
     * {@inheritdoc}
     */
    protected function buildTrace($testCase): array
    {
        if (!\is_int($testCase['_tracePos'])) {
            $this->buildMessage($testCase);
        }

        if ($testCase['_tracePos'] >= 0) {
            $stackStr = \substr($this->getMessageTrace($testCase), (int)$testCase['_tracePos'] + 2, -1);
            $trace = \explode("\n", \str_replace($this->buildPath, '.', $stackStr));
        } else {
            $trace = [];
        }

        return $trace;
    }

    /**
     * @param mixed $testCase
     *
     * @return string
     */
    private function getMessageTrace($testCase): string
    {
        $msg = '';
        foreach ($testCase as $child) {
            switch ($child->getName()) {
                case 'system-out':
                case 'system-err':
                    // not results
                    continue 2;
                default:
                    $msg = (string)$child['message']; // according to xsd
                    if ('' === $msg) {
                        $msg = (string)$child;
                    }
                    break 2;
            }
        }

        return $msg;
    }

    /**
     * @return \SimpleXMLElement|null
     *
     * @throws Exception
     */
    private function loadResultFile(): ?\SimpleXMLElement
    {
        if (!\file_exists($this->outputFile) || 0 === \filesize($this->outputFile)) {
            $this->internalProblem('empty output file');

            return new \SimpleXMLElement('<empty />'); // new empty element
        }

        return $this->loadFromFile($this->outputFile);
    }

    /**
     * @param string $description
     *
     * @throws Exception
     */
    private function internalProblem(string $description)
    {
        throw new Exception($description);
    }

    /**
     * @param string $filePath
     *
     * @return \SimpleXMLElement|null
     */
    private function loadFromFile(string $filePath): ?\SimpleXMLElement
    {
        \stream_filter_register('xml_utf8_clean', 'PHPCensor\Helper\Xml\Utf8CleanFilter');

        try {
            $xml = \simplexml_load_file('php://filter/read=xml_utf8_clean/resource=' . $filePath);
        } catch (\Exception $ex) {
            $xml = null;
        } catch (\Throwable $ex) { // since php7
            $xml = null;
        }

        if (!$xml) {
            // from https://stackoverflow.com/questions/7766455/how-to-handle-invalid-unicode-with-simplexml/8092672#8092672
            $oldUse = \libxml_use_internal_errors(true);

            \libxml_clear_errors();

            $dom = new \DOMDocument("1.0", "UTF-8");

            $dom->strictErrorChecking = false;
            $dom->validateOnParse     = false;
            $dom->recover             = true;

            $dom->loadXML(\strtr(
                \file_get_contents($filePath),
                ['&quot;' => "'"] // &quot; in attribute names may mislead the parser
            ));

            $xmlError = \libxml_get_last_error();
            if ($xmlError) {
                $warning = \sprintf('L%s C%s: %s', $xmlError->line, $xmlError->column, $xmlError->message);
                print 'WARNING: ignored errors while reading phpunit result, '.$warning."\n";
            }

            if (!$dom->hasChildNodes()) {
                return new \SimpleXMLElement('<empty />');
            }

            $xml = \simplexml_import_dom($dom);

            \libxml_clear_errors();
            \libxml_use_internal_errors($oldUse);
        }

        return $xml;
    }
}
