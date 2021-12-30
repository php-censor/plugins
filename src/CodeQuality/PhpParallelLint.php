<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\CodeQuality;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Build\BuildMetaInterface;
use PHPCensor\Common\Plugin\Plugin;
use PHPCensor\Common\Plugin\ZeroConfigPluginInterface;

/**
 * Php Parallel Lint Plugin - Provides access to PHP lint functionality.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Vaclav Makes <vaclav@makes.cz>
 */
class PhpParallelLint extends Plugin implements ZeroConfigPluginInterface
{
    /**
     * @var string Comma separated list of file extensions
     */
    private string $extensions = 'php';

    /**
     * @var bool Enable short tags
     */
    private bool $shortTags = false;

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'php_parallel_lint';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $executable = $this->commandExecutor->findBinary($this->binaryNames, $this->binaryPath);

        $cmd     = $executable . ' -e %s' . '%s %s "%s"';
        $success = $this->commandExecutor->executeCommand(
            $cmd,
            $this->extensions,
            ($this->shortTags ? ' -s' : ''),
            $this->getExcludeString(),
            $this->directory
        );

        $output = $this->commandExecutor->getLastCommandOutput();

        $matches = [];
        if (\preg_match_all("#Parse error:#", $output, $matches)) {
            $this->buildMetaWriter->write(
                $this->build->getId(),
                self::getName(),
                BuildMetaInterface::KEY_ERRORS,
                \count($matches[0])
            );
        }

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
        $this->shortTags = (bool)$this->options->get('shorttags', $this->shortTags);

        if ($this->options->has('extensions')) {
            $pattern    = '#^([a-z]+)(,\ *[a-z]*)*$#';
            $extensions = (string)$this->options->get('extensions', '');

            if (\preg_match($pattern, $extensions)) {
                $this->extensions = \str_replace(' ', '', $extensions);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function getPluginDefaultBinaryNames(): array
    {
        return [
            'parallel-lint',
            'parallel-lint.phar',
        ];
    }

    private function getExcludeString(): string
    {
        $ignoreFlags = [];
        foreach ($this->ignores as $ignore) {
            $ignoreFlags[] = \sprintf(' --exclude "%s"', $this->build->getBuildPath() . $ignore);
        }

        return \implode(' ', $ignoreFlags);
    }
}
