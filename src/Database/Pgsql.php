<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\Database;

use PDO;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\ParameterBag;
use PHPCensor\Common\Plugin\Plugin;

/**
 * PgSQL Plugin - Provides access to a PgSQL database.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Dan Cryer <dan@block8.co.uk>
 */
class Pgsql extends Plugin
{
    /**
     * @var string
     */
    private string $host = '127.0.0.1';

    /**
     * @var int
     */
    private int $port = 5432;

    /**
     * @var string
     */
    private string $dbName = '';

    /**
     * @var array
     */
    private array $pdoOptions = [];

    /**
     * @var string
     */
    private string $user = '';

    /**
     * @var string
     */
    private string $password = '';

    /**
     * @var array
     */
    private array $queries = [];

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'pgsql';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $pdoOptions = \array_merge([
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ], $this->pdoOptions);
        $dsn     = \sprintf('pgsql:host=%s;port=%s', $this->host, $this->port);

        if ($this->dbName) {
            $dsn .= ';dbname=' . $this->dbName;
        }

        $pdo = new PDO($dsn, $this->user, $this->password, $pdoOptions);

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
        $buildSettings    = (array)$this->buildSettings->get('pgsql', []);
        $buildSettingsBag = new ParameterBag($buildSettings);

        $this->host       = (string)$buildSettingsBag->get('host', $this->host);
        $this->port       = (int)$buildSettingsBag->get('port', $this->port);
        $this->dbName     = (string)$buildSettingsBag->get('dbname', $this->dbName);
        $this->user       = (string)$buildSettingsBag->get('user', $this->user);
        $this->password   = (string)$buildSettingsBag->get('password', $this->password);
        $this->pdoOptions = (array)$buildSettingsBag->get('options', $this->pdoOptions);

        $this->queries = (array)$this->options->get('queries', $this->queries);
    }
}
