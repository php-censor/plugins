<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Testing\Codeception;

/**
 * Codeception Plugin
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Adam Cooper <adam@networkpie.co.uk>
 */
class Parser implements ParserInterface
{
    private string $buildPath;

    private string $xmlPath;

    private int $totalTests = 0;

    private int $totalTimeTaken = 0;

    private int $totalFailures = 0;

    private int $totalErrors = 0;

    public function __construct(string $builderPath, string $xmlPath)
    {
        $this->buildPath = $builderPath;
        $this->xmlPath   = $xmlPath;
    }

    /**
     * @return array An array of key/value pairs for storage in the plugins result metadata
     */
    public function parse(): array
    {
        $rtn     = [];
        $results = $this->loadFromFile($this->xmlPath);

        if ($results) {
            foreach ($results->testsuite as $testSuite) {
                $this->totalTests     += (int)$testSuite['tests'];
                $this->totalTimeTaken += (int)$testSuite['time'];
                $this->totalFailures  += (int)$testSuite['failures'];
                $this->totalErrors    += (int)$testSuite['errors'];

                foreach ($testSuite->testcase as $testCase) {
                    $testResult = [
                        'suite'      => (string)$testSuite['name'],
                        'file'       => \str_replace($this->buildPath, '/', (string)$testCase['file']),
                        'name'       => (string)$testCase['name'],
                        'feature'    => (string)$testCase['feature'],
                        'assertions' => (int)$testCase['assertions'],
                        'time'       => (float)$testCase['time'],
                        'class'      => 'Unknown',
                    ];

                    if (isset($testCase['class'])) {
                        $testResult['class'] = (string)$testCase['class'];
                    }

                    // PHPUnit testcases does not have feature field. Use class::method instead
                    if (!$testResult['feature']) {
                        $testResult['feature'] = \sprintf('%s::%s', $testResult['class'], $testResult['name']);
                    }

                    if (isset($testCase->failure) || isset($testCase->error)) {
                        $testResult['pass']    = false;
                        $testResult['message'] = isset($testCase->failure) ? (string)$testCase->failure : (string)$testCase->error;
                    } else {
                        $testResult['pass'] = true;
                    }

                    $rtn[] = $testResult;
                }
            }
        }

        return $rtn;
    }

    /**
     * Get the total number of tests performed.
     */
    public function getTotalTests(): int
    {
        return $this->totalTests;
    }

    /**
     * The time take to complete all tests
     */
    public function getTotalTimeTaken(): float
    {
        return $this->totalTimeTaken;
    }

    /**
     * A count of the test failures
     */
    public function getTotalFailures(): int
    {
        return $this->totalFailures + $this->totalErrors;
    }

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
