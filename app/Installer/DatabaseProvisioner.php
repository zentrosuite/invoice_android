<?php

declare(strict_types=1);

namespace App\Installer;

use PDO;
use PDOException;

/**
 * Connecting to MySQL, and creating the database if it is not there yet.
 */
final class DatabaseProvisioner
{
    /**
     * The migrations assume these. utf8mb4 for four-byte characters, and a modern accent- and
     * case-insensitive collation so product search behaves the way people expect.
     */
    public const CHARSET = 'utf8mb4';

    public const COLLATION = 'utf8mb4_0900_ai_ci';

    /**
     * Can we reach the SERVER with these credentials, ignoring the database name?
     *
     * @return array{ok: bool, message: string, serverVersion: string|null}
     */
    public function testServer(array $config): array
    {
        try {
            $pdo = $this->connect($config, null);

            return [
                'ok' => true,
                'message' => 'Connected to the MySQL server.',
                'serverVersion' => (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
            ];
        } catch (PDOException $e) {
            return [
                'ok' => false,
                'message' => $this->explain($e, $config),
                'serverVersion' => null,
            ];
        }
    }

    public function databaseExists(array $config): bool
    {
        try {
            $pdo = $this->connect($config, null);
            $statement = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
            );
            $statement->execute([$config['database']]);

            return (int) $statement->fetchColumn() > 0;
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * Is the database already carrying tables?
     */
    public function tableCount(array $config): int
    {
        try {
            $pdo = $this->connect($config, $config['database']);
            $statement = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
            );
            $statement->execute([$config['database']]);

            return (int) $statement->fetchColumn();
        } catch (PDOException) {
            return 0;
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function createDatabase(array $config): array
    {
        $name = (string) $config['database'];

        $quoted = '`' . str_replace('`', '', $name) . '`';

        try {
            $pdo = $this->connect($config, null);
            $pdo->exec(sprintf(
                'CREATE DATABASE IF NOT EXISTS %s CHARACTER SET %s COLLATE %s',
                $quoted,
                self::CHARSET,
                self::COLLATION,
            ));

            return ['ok' => true, 'message' => sprintf('Created the database %s.', $name)];
        } catch (PDOException $e) {
            return [
                'ok' => false,
                'message' => sprintf(
                    'Could not create %s: %s Create it yourself and try again — the SQL is shown below.',
                    $name,
                    $e->getMessage(),
                ),
            ];
        }
    }

    /**
     * The exact statement to run by hand, for when the MySQL user cannot create databases.
     */
    public function createStatement(string $database): string
    {
        return sprintf(
            "CREATE DATABASE `%s`\n  CHARACTER SET %s\n  COLLATE %s;",
            str_replace('`', '', $database),
            self::CHARSET,
            self::COLLATION,
        );
    }

    /**
     * Full check: reach the server, find the database, and confirm we may write to it.
     *
     * @return array{ok: bool, message: string, canCreateTables: bool, tables: int}
     */
    public function verify(array $config): array
    {
        try {
            $pdo = $this->connect($config, $config['database']);
        } catch (PDOException $e) {
            return [
                'ok' => false,
                'message' => $this->explain($e, $config),
                'canCreateTables' => false,
                'tables' => 0,
            ];
        }

        $probe = '_installer_write_probe';
        $canCreate = false;

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS {$probe} (id INT PRIMARY KEY)");
            $pdo->exec("DROP TABLE IF EXISTS {$probe}");
            $canCreate = true;
        } catch (PDOException) {
            // Left false; reported below rather than thrown.
        }

        return [
            'ok' => $canCreate,
            'message' => $canCreate
                ? 'Connected, and this user can create tables.'
                : sprintf(
                    'Connected to %s, but "%s" cannot create tables in it. Run: '
                    . 'GRANT ALL PRIVILEGES ON `%s`.* TO \'%s\'@\'%s\';',
                    $config['database'],
                    $config['username'],
                    $config['database'],
                    $config['username'],
                    $config['host'] === 'localhost' ? 'localhost' : '%',
                ),
            'canCreateTables' => $canCreate,
            'tables' => $this->tableCount($config),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function connect(array $config, ?string $database): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d', $config['host'], (int) $config['port']);

        if ($database !== null) {
            $dsn .= ';dbname=' . $database;
        }

        return new PDO($dsn, (string) $config['username'], (string) ($config['password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Five seconds. Long enough for a slow local socket, short enough that a wrong
            // host does not leave the page hanging for a minute with no explanation.
            PDO::ATTR_TIMEOUT => 5,
        ]);
    }

    /**
     * Turn a PDO error into something a person can act on.
     */
    private function explain(PDOException $e, array $config): string
    {
        $code = (int) $e->getCode();
        $raw = $e->getMessage();

        return match (true) {
            str_contains($raw, '1045') || $code === 1045 => sprintf(
                'MySQL rejected the username or password for "%s". Check both, and that the '
                . 'user is allowed to connect from this host.',
                $config['username'],
            ),
            str_contains($raw, '1049') => sprintf(
                'Connected to the server, but there is no database called "%s" yet. '
                . 'The installer can create it for you.',
                $config['database'],
            ),
            str_contains($raw, '2002') || str_contains($raw, 'refused') => sprintf(
                'Nothing is listening on %s:%s. Is MySQL running? On Ubuntu: sudo systemctl status mysql',
                $config['host'],
                $config['port'],
            ),
            str_contains($raw, 'timed out') || str_contains($raw, '2006') => sprintf(
                'The server at %s:%s did not answer within five seconds. Check the host, the '
                . 'port, and any firewall between here and there.',
                $config['host'],
                $config['port'],
            ),
            str_contains($raw, 'could not find driver') => 'PHP has no MySQL driver. '
                . 'Install it with: sudo apt install php8.4-mysql',
            default => 'MySQL said: ' . $raw,
        };
    }
}
