<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\Database;

use PDO;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Plugin\Plugin;

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
    private $queries = [];

    /**
     * @var string
     */
    private $path = '';

    /**
     * @var array
     */
    private $pdoOptions = [];

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
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
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
        $buildSettings    = $this->buildSettings->get('sqlite', []);
        $buildSettingsBag = new Plugin\ParameterBag($buildSettings);

        $this->path       = $buildSettingsBag->get('path', $this->path);
        $this->pdoOptions = $buildSettingsBag->get('options', $this->pdoOptions);

        $this->queries = (array)$this->options->get('queries', $this->queries);
    }
}
