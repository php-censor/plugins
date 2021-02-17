<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\CodeQuality;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Build\BuildMetaInterface;
use PHPCensor\Common\Plugin\Plugin;
use PHPCensor\Common\Plugin\ZeroConfigPluginInterface;

/**
 * PHP Loc - Allows PHP Copy / Lines of Code testing.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Johan van der Heide <info@japaveh.nl>
 */
class PhpLoc extends Plugin implements ZeroConfigPluginInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'php_loc';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $ignoreString = '';
        if ($this->ignores) {
            $map = function ($ignore) {
                return \sprintf(' --exclude="%s"', $ignore);
            };

            $ignoreString = \array_map($map, $this->ignores);
            $ignoreString = \implode('', $ignoreString);
        }

        $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);
        $success    = $this->commandExecutor->executeCommand(
            'cd "%s" && php -d xdebug.mode=0 -d error_reporting=0 ' . $executable . ' %s "%s"',
            $this->build->getBuildPath(),
            $ignoreString,
            $this->directory
        );

        $output = $this->commandExecutor->getLastCommandOutput();

        if (\preg_match_all('#\((LOC|CLOC|NCLOC|LLOC)\)\s+([0-9]+)#', $output, $matches)) {
            $data = [];
            foreach ($matches[1] as $k => $v) {
                $data[$v] = (int)$matches[2][$k];
            }

            $this->buildMetaWriter->write(
                $this->build->getId(),
                self::getName(),
                BuildMetaInterface::KEY_DATA,
                $data
            );
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public static function canExecute(string $stage, BuildInterface $build): bool
    {
        if (BuildInterface::STAGE_TEST === $stage) {
            return true;
        }

        return false;
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
            'phploc',
            'phploc.phar',
        ];
    }
}
