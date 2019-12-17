<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins;

use PHPCensor\Common\Plugin\Plugin;

/**
 * PHP Spec Plugin - Allows PHP Spec testing.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Dan Cryer <dan@block8.co.uk>
 */
class PhpSpec extends Plugin
{
    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'php_spec';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);
        $success    = $this->commandExecutor->executeCommand($executable . ' --format=junit --no-code-generation run');
        $output     = $this->commandExecutor->getLastCommandOutput();

        /*
         * process xml output
         *
         * <testsuites time=FLOAT tests=INT failures=INT errors=INT>
         *   <testsuite name=STRING time=FLOAT tests=INT failures=INT errors=INT skipped=INT>
         *     <testcase name=STRING time=FLOAT classname=STRING status=STRING/>
         *   </testsuite>
         * </testsuites
         */

        $xml  = new \SimpleXMLElement($output);
        $attr = $xml->attributes();
        $data = [
            'time'     => (float)$attr['time'],
            'tests'    => (int)$attr['tests'],
            'failures' => (int)$attr['failures'],
            'errors'   => (int)$attr['errors'],
            // now all the tests
            'suites'   => [],
        ];

        /**
         * @var \SimpleXMLElement $group
         */
        foreach ($xml->xpath('testsuite') as $group) {
            $attr  = $group->attributes();
            $suite = [
                'name'     => (string)$attr['name'],
                'time'     => (float)$attr['time'],
                'tests'    => (int)$attr['tests'],
                'failures' => (int)$attr['failures'],
                'errors'   => (int)$attr['errors'],
                'skipped'  => (int)$attr['skipped'],
                // now the cases
                'cases'    => [],
            ];

            /**
             * @var \SimpleXMLElement $child
             */
            foreach ($group->xpath('testcase') as $child) {
                $attr = $child->attributes();
                $case = [
                    'name'      => (string)$attr['name'],
                    'classname' => (string)$attr['classname'],
                    'time'      => (float)$attr['time'],
                    'status'    => (string)$attr['status'],
                ];

                if ('failed' == $case['status']) {
                    $error = [];
                    /*
                     * ok, sad, we had an error
                     *
                     * there should be one - foreach makes this easier
                     */
                    foreach ($child->xpath('failure') as $failure) {
                        $attr             = $failure->attributes();
                        $error['type']    = (string)$attr['type'];
                        $error['message'] = (string)$attr['message'];
                    }

                    foreach ($child->xpath('system-err') as $systemError) {
                        $error['raw'] = (string)$systemError;
                    }

                    $case['error'] = $error;
                }

                $suite['cases'][] = $case;
            }

            $data['suites'][] = $suite;
        }

        $this->buildMetaWriter->write(
            $this->build->getId(),
            (self::getName() . '-data'),
            $data
        );

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    protected function initPluginSettings(): void
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'phpspec',
            'phpspec.php',
            'phpspec.phar',
        ];
    }
}
