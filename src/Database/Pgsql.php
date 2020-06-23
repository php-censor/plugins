<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\Database;

use PDO;
use PHPCensor\Common\Build\BuildInterface;
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
    private $host = '127.0.0.1';

    /**
     * @var int
     */
    private $port = 5432;

    /**
     * @var string|null
     */
    private $dbName = null;

    /**
     * @var array
     */
    private $pdoOptions = [];

    /**
     * @var string
     */
    private $user = '';

    /**
     * @var string
     */
    private $password = '';

    /**
     * @var array
     */
    private $queries = [];

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

        if (null !== $this->dbName) {
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
        $this->host       = $this->buildSettings->get('host', $this->host);
        $this->port       = $this->buildSettings->get('port', $this->port);
        $this->dbName     = $this->buildSettings->get('dbname', $this->dbName);
        $this->user       = $this->buildSettings->get('user', $this->user);
        $this->password   = $this->buildSettings->get('password', $this->password);
        $this->pdoOptions = $this->buildSettings->get('options', $this->pdoOptions);

        $this->queries = (array)$this->options->get('queries', $this->queries);
    }
}
