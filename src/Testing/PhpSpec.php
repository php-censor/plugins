<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Testing;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Build\BuildMetaInterface;
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
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'php_spec';
    }

    /**
     * {@inheritDoc}
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
            'time'     => $attr ? (float)$attr['time'] : 0,
            'tests'    => $attr ? (int)$attr['tests'] : 0,
            'failures' => $attr ? (int)$attr['failures'] : 0,
            'errors'   => $attr ? (int)$attr['errors'] : 0,
            // now all the tests
            'suites'   => [],
        ];

        foreach ($xml->xpath('testsuite') as $group) {
            $attr  = $group->attributes();
            $suite = [
                'name'     => $attr ? (string)$attr['name'] : '',
                'time'     => $attr ? (float)$attr['time'] : 0,
                'tests'    => $attr ? (int)$attr['tests'] : 0,
                'failures' => $attr ? (int)$attr['failures'] : 0,
                'errors'   => $attr ? (int)$attr['errors'] : 0,
                'skipped'  => $attr ? (int)$attr['skipped'] : 0,
                // now the cases
                'cases'    => [],
            ];

            foreach ($group->xpath('testcase') as $child) {
                $attr = $child->attributes();
                $case = [
                    'name'      => $attr ? (string)$attr['name'] : '',
                    'classname' => $attr ? (string)$attr['classname'] : '',
                    'time'      => $attr ? (float)$attr['time'] : 0,
                    'status'    => $attr ? (string)$attr['status'] : '',
                ];

                if ('failed' === $case['status']) {
                    $error = [];
                    /*
                     * ok, sad, we had an error
                     *
                     * there should be one - foreach makes this easier
                     */
                    foreach ($child->xpath('failure') as $failure) {
                        $attr             = $failure->attributes();
                        $error['type']    = $attr ? (string)$attr['type'] : '';
                        $error['message'] = $attr ? (string)$attr['message'] : '';
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
            self::getName(),
            BuildMetaInterface::KEY_DATA,
            $data
        );

        return $success;
    }

    /**
     * {@inheritDoc}
     */
    public static function canExecute(string $stage, BuildInterface $build): bool
    {
        if (BuildInterface::STAGE_TEST === $stage) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    protected function initPluginSettings(): void
    {
    }

    /**
     * {@inheritDoc}
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
