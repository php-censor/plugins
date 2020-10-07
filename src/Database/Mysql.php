<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\Database;

use PDO;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Exception\Exception;
use PHPCensor\Common\Plugin\Plugin;

/**
 * MySQL Plugin - Provides access to a MySQL database.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Dan Cryer <dan@block8.co.uk>
 * @author Steve Kamerman <stevekamerman@gmail.com>
 */
class Mysql extends Plugin
{
    /**
     * @var string
     */
    private $host = '127.0.0.1';

    /**
     * @var int
     */
    private $port = 3306;

    /**
     * @var string|null
     */
    private $dbName = null;

    /**
     * @var string|null
     */
    private $charset = null;

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
     * @var array
     */
    private $imports = [];

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'mysql';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $pdoOptions = \array_merge([
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ], $this->pdoOptions);
        $dsn     = \sprintf('mysql:host=%s;port=%s', $this->host, $this->port);

        if (null !== $this->dbName) {
            $dsn .= ';dbname=' . $this->dbName;
        }

        if (null !== $this->charset) {
            $dsn .= ';charset=' . $this->charset;
        }

        $pdo = new PDO($dsn, $this->user, $this->password, $pdoOptions);

        foreach ($this->queries as $query) {
            $pdo->query($query);
        }

        foreach ($this->imports as $import) {
            $this->executeFile($import);
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
        $buildSettings    = (array)$this->buildSettings->get('mysql', []);
        $buildSettingsBag = new Plugin\ParameterBag($buildSettings);

        $this->host       = $buildSettingsBag->get('host', $this->host);
        $this->port       = $buildSettingsBag->get('port', $this->port);
        $this->dbName     = $buildSettingsBag->get('dbname', $this->dbName);
        $this->user       = $buildSettingsBag->get('user', $this->user);
        $this->password   = $buildSettingsBag->get('password', $this->password);
        $this->pdoOptions = $buildSettingsBag->get('options', $this->pdoOptions);
        $this->charset    = $buildSettingsBag->get('charset', $this->charset);

        $this->queries = (array)$this->options->get('queries', $this->queries);
        $this->imports = (array)$this->options->get('imports', $this->imports);
    }

    /**
     * @param array $query
     *
     * @return bool
     *
     * @throws Exception
     */
    protected function executeFile(array $query): bool
    {
        if (!isset($query['file'])) {
            throw new Exception('Import statement must contain a \'file\' key');
        }

        $importFile = $this->pathResolver->resolvePath($query['file']);
        if (!\is_readable($importFile)) {
            throw new Exception(\sprintf('Cannot open SQL import file: %s', $importFile));
        }

        $database = isset($query['database']) ? $this->variableInterpolator->interpolate($query['database']) : null;

        $importCommand = $this->getImportCommand($importFile, $database);
        if (!$this->commandExecutor->executeCommand($importCommand)) {
            throw new Exception('Unable to execute SQL file');
        }

        return true;
    }

    /**
     * Builds the MySQL import command required to import/execute the specified file
     *
     * @param string $importFile Path to file, relative to the build root
     * @param string $database   If specified, this database is selected before execution
     *
     * @return string
     */
    protected function getImportCommand(string $importFile, $database = null): string
    {
        $decompression = [
            'bz2' => '| bzip2 --decompress',
            'gz'  => '| gzip --decompress',
        ];

        $extension        = \strtolower(\pathinfo($importFile, PATHINFO_EXTENSION));
        $decompressionCmd = '';
        if (\array_key_exists($extension, $decompression)) {
            $decompressionCmd = $decompression[$extension];
        }

        $args = [
            ':import_file' => \escapeshellarg($importFile),
            ':decomp_cmd'  => $decompressionCmd,
            ':host'        => \escapeshellarg($this->host),
            ':user'        => \escapeshellarg($this->user),
            ':pass'        => (!$this->password) ? '' : '-p' . \escapeshellarg($this->password),
            ':database'    => ($database === null) ? '' : \escapeshellarg($database),
        ];

        return \strtr('cat :import_file :decomp_cmd | mysql -h:host -u:user :pass :database', $args);
    }
}
