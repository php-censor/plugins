<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\Notification\EmailNotify;

use PHPCensor\Common\Exception\Exception;

/**
 * EmailNotify Plugin
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 */
interface ViewFactoryInterface
{
    /**
     * @param string      $viewPath
     * @param string|null $viewExtension
     *
     * @return ViewInterface
     *
     * @throws Exception
     */
    public function createView(string $viewPath, ?string $viewExtension = null): ViewInterface;
}
