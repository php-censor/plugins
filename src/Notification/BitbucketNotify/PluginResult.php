<?php

declare(strict_types = 1);

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
    const DEFAULT_PLUGIN_OUTPUT_FORMAT = "%s | %d\t=> %d\t%s";

    /**
     * @var string $plugin
     */
    protected string $plugin;

    /**
     * @var int $left
     */
    protected int $left;

    /**
     * @var int $right
     */
    protected int $right;

    /**
     * @var string $outputFormat
     */
    protected string $outputFormat;

    /**
     * @param string $plugin
     * @param int    $left
     * @param int    $right
     */
    public function __construct(string $plugin, int $left, int $right)
    {
        $this->plugin       = $plugin;
        $this->left         = $left;
        $this->right        = $right;
        $this->outputFormat = self::DEFAULT_PLUGIN_OUTPUT_FORMAT;
    }

    /**
     * @return string
     */
    public function getPlugin(): string
    {
        return $this->plugin;
    }

    /**
     * @param string $plugin
     *
     * @return $this
     */
    public function setPlugin(string $plugin): self
    {
        $this->plugin = $plugin;

        return $this;
    }

    /**
     * @return int
     */
    public function getLeft(): int
    {
        return $this->left;
    }

    /**
     * @param int $left
     *
     * @return $this
     */
    public function setLeft(int $left): self
    {
        $this->left = $left;

        return $this;
    }

    /**
     * @return int
     */
    public function getRight(): int
    {
        return $this->right;
    }

    /**
     * @param int $right
     *
     * @return $this
     */
    public function setRight(int $right): self
    {
        $this->right = $right;

        return $this;
    }

    /**
     * @return bool
     */
    public function isImproved(): bool
    {
        return $this->right < $this->left;
    }

    /**
     * @return bool
     */
    public function isDegraded(): bool
    {
        return $this->right > $this->left;
    }

    /**
     * @return bool
     */
    public function isUnchanged(): bool
    {
        return $this->right === $this->left;
    }

    /**
     * @param int $maxPluginNameLength
     *
     * @return string
     */
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

    /**
     * @return string
     */
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

    /**
     * @return string
     */
    protected function getTaskDescriptionMessage(): string
    {
        return 'pls fix %s because it has increased from %d to %d errors';
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->plugin;
    }

    /**
     * @return string
     */
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
