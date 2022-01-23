<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Database;

use PDO;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\ParameterBag;
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
    private array $queries = [];

    private string $path = '';

    private array $pdoOptions = [];

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'sqlite';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $pdoOptions = \array_merge([
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ], $this->pdoOptions);

        $pdo = new PDO(('sqlite:' . $this->path), null, null, $pdoOptions);

        foreach ($this->queries as $query) {
            $pdo->query($query);
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public static function canExecute(string $stage, BuildInterface $build): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    protected function initPluginSettings(): void
    {
        $buildSettings    = (array)$this->buildSettings->get('sqlite', []);
        $buildSettingsBag = new ParameterBag($buildSettings);

        $this->path       = (string)$buildSettingsBag->get('path', $this->path);
        $this->pdoOptions = (array)$buildSettingsBag->get('options', $this->pdoOptions);

        $queries = (array)$this->options->get('queries', $this->queries);
        foreach ($queries as $query) {
            $this->queries[] = $this->variableInterpolator->interpolate($query);
        }
    }
}
