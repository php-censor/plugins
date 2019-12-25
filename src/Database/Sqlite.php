<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\Database;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Plugin\Plugin;
use PDO;

/**
 * SQLite Plugin — Provides access to a SQLite database.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 */
class Sqlite extends Plugin
{
    /**
     * @var array
     */
    protected $queries = [];

    /**
     * @var string
     */
    protected $path = '';

    /**
     * @var array
     */
    protected $pdoOptions = [];

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'sqlite';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $pdoOptions = \array_merge([
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ], $this->pdoOptions);

        $pdo = new PDO('sqlite:' . $this->path, $pdoOptions);

        foreach ($this->queries as $query) {
            $pdo->query($query);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public static function canExecute(string $stage, BuildInterface $build): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function initPluginSettings(): void
    {
        $this->path       = $this->buildSettings->get('path', $this->path);
        $this->pdoOptions = $this->buildSettings->get('options', $this->pdoOptions);

        $this->queries = (array)$this->options->get('queries', $this->queries);
    }
}