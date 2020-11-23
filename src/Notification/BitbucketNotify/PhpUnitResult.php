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
class PhpUnitResult extends PluginResult
{
    /**
     * @param string $plugin
     * @param int    $left
     * @param int    $right
     */
    public function __construct(string $plugin, int $left, int $right)
    {
        parent::__construct($plugin, $left, $right);

        $this->outputFormat = "%s | %01.2f\t=> %01.2f\t%s";
    }

    /**
     * @return bool
     */
    public function isImproved(): bool
    {
        return $this->right > $this->left;
    }

    /**
     * @return bool
     */
    public function isDegraded(): bool
    {
        return $this->right < $this->left;
    }

    /**
     * @return string
     */
    protected function getTaskDescriptionMessage(): string
    {
        return 'pls fix %s because the coverage has decreased from %d to %d';
    }
}
