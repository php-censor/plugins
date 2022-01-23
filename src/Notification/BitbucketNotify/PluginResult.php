<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Notification\BitbucketNotify;

/**
 * BitbucketNotify Plugin
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Eugen Ganshorn <eugen.ganshorn@check24.de>
 */
class PluginResult
{
    public const DEFAULT_PLUGIN_OUTPUT_FORMAT = "%s | %d\t=> %d\t%s";

    protected string $plugin;

    protected int $left;

    protected int $right;

    protected string $outputFormat;

    public function __construct(string $plugin, int $left, int $right)
    {
        $this->plugin       = $plugin;
        $this->left         = $left;
        $this->right        = $right;
        $this->outputFormat = self::DEFAULT_PLUGIN_OUTPUT_FORMAT;
    }

    public function getPlugin(): string
    {
        return $this->plugin;
    }

    /**
     * @return $this
     */
    public function setPlugin(string $plugin): self
    {
        $this->plugin = $plugin;

        return $this;
    }

    public function getLeft(): int
    {
        return $this->left;
    }

    /**
     * @return $this
     */
    public function setLeft(int $left): self
    {
        $this->left = $left;

        return $this;
    }

    public function getRight(): int
    {
        return $this->right;
    }

    /**
     * @return $this
     */
    public function setRight(int $right): self
    {
        $this->right = $right;

        return $this;
    }

    public function isImproved(): bool
    {
        return $this->right < $this->left;
    }

    public function isDegraded(): bool
    {
        return $this->right > $this->left;
    }

    public function isUnchanged(): bool
    {
        return $this->right === $this->left;
    }

    public function generateFormattedOutput(int $maxPluginNameLength): string
    {
        return \trim(\sprintf(
            $this->outputFormat,
            \str_pad($this->plugin, $maxPluginNameLength),
            $this->left,
            $this->right,
            $this->generateComment()
        ));
    }

    public function generateTaskDescription(): string
    {
        if (!$this->isDegraded()) {
            return '';
        }

        return \sprintf(
            $this->getTaskDescriptionMessage(),
            $this->plugin,
            $this->left,
            $this->right
        );
    }

    protected function getTaskDescriptionMessage(): string
    {
        return 'pls fix %s because it has increased from %d to %d errors';
    }

    public function __toString(): string
    {
        return $this->plugin;
    }

    protected function generateComment(): string
    {
        if ($this->isDegraded()) {
            return '!!!!! o_O';
        }

        if ($this->isImproved()) {
            return 'great success!';
        }

        if ($this->left > 0 && $this->isUnchanged()) {
            return 'pls improve me :-(';
        }

        return '';
    }
}
