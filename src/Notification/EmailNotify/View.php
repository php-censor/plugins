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
class View implements ViewInterface
{
    /**
     * @var array
     */
    protected array $data = [];

    /**
     * @var string
     */
    protected string $viewPath;

    /**
     * @var string
     */
    protected string $viewExtension = 'phtml';

    /**
     * @param string      $viewPath
     * @param string|null $viewExtension
     *
     * @throws Exception
     */
    public function __construct(string $viewPath, ?string $viewExtension = null)
    {
        if (null !== $viewExtension) {
            $this->viewExtension = $viewExtension;
        }

        $this->viewPath = $viewPath;
        if (!\file_exists(
            \sprintf('%s.%s', $this->viewPath, $this->viewExtension)
        )) {
            throw new Exception('View file does not exist: ' . $this->viewPath);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasVariable(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function getVariable(string $key)
    {
        return $this->data[$key];
    }

    /**
     * {@inheritdoc}
     */
    public function setVariable(string $key, $value): bool
    {
        $this->data[$key] = $value;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function setVariables(array $value): bool
    {
        $this->data = \array_merge($this->data, $value);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function render(): string
    {
        \extract($this->data);

        \ob_start();

        require(
            \sprintf('%s.%s', $this->viewPath, $this->viewExtension)
        );

        $output = \ob_get_contents();
        \ob_end_clean();

        return $output;
    }
}
