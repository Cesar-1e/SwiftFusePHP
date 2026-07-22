<?php

declare(strict_types=1);

namespace SwiftFuse\Database;

use PDO;
use PDOException;
use PDOStatement;

/**
 * PDO database connection wrapper.
 *
 * Provides a small, prepared-statement-first API over PDO (MySQL by default),
 * including transaction helpers. Connection settings are read from
 * config('database.*'). This is the English port of the legacy Conexion class;
 * the public method names are preserved so existing models keep working.
 */
class Connection
{
    /**
     * The underlying PDO handle.
     *
     * @var PDO|null
     */
    private ?PDO $pdo = null;

    /**
     * The current prepared statement.
     *
     * @var PDOStatement|null
     */
    private ?PDOStatement $statement = null;

    /**
     * The last connection or execution error message.
     *
     * @var string|null
     */
    private ?string $error = null;

    /**
     * Whether the current statement has already been executed.
     *
     * @var bool
     */
    private bool $executed = false;

    /**
     * Establish the PDO connection using the application configuration.
     */
    public function __construct()
    {
        $driver = (string) config('database.driver', 'mysql');
        $host = (string) config('database.host', 'localhost');
        $port = (string) config('database.port', '3306');
        $database = (string) config('database.database', '');
        $charset = (string) config('database.charset', 'utf8mb4');

        $dsn = "{$driver}:host={$host};port={$port};dbname={$database};charset={$charset}";
        $options = [
            PDO::ATTR_PERSISTENT => (bool) config('database.persistent', true),
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        ];

        $sslCa = (string) config('database.ssl_ca', '');
        if ($sslCa !== '') {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
        }

        try {
            $this->pdo = new PDO($dsn, (string) config('database.username', ''), (string) config('database.password', ''), $options);
        } catch (PDOException $exception) {
            $this->error = $exception->getMessage();
        }
    }

    /**
     * Get the last error message, or null when none occurred.
     *
     * @return string|null
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Prepare an SQL statement.
     *
     * @param string $sql The SQL query, optionally with named placeholders.
     * @return void
     */
    public function query(string $sql): void
    {
        $this->statement = $this->pdo()->prepare($sql);
        $this->executed = false;
    }

    /**
     * Get the live PDO handle, or fail loudly if the connection was not made.
     *
     * @return PDO
     *
     * @throws PDOException When no connection is available.
     */
    private function pdo(): PDO
    {
        if ($this->pdo === null) {
            throw new PDOException($this->error ?? 'Database connection is not available.');
        }

        return $this->pdo;
    }

    /**
     * Bind a value to a named/positional placeholder.
     *
     * The PDO parameter type is auto-detected when not provided; arrays are
     * JSON-encoded before binding.
     *
     * @param string|int $parameter Placeholder name (e.g. ":name") or position.
     * @param mixed $value The value to bind.
     * @param int|null $type Explicit PDO::PARAM_* type, or null to auto-detect.
     * @return void
     */
    public function bind(string|int $parameter, mixed $value, ?int $type = null): void
    {
        if ($type === null) {
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                is_array($value) => PDO::PARAM_STR,
                default => PDO::PARAM_STR,
            };

            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }

        $this->statement->bindValue($parameter, $value, $type);
    }

    /**
     * Execute the prepared statement (idempotent within a single prepare).
     *
     * @return bool True on success, false on failure outside a transaction.
     *
     * @throws PDOException When a failure occurs inside an active transaction.
     */
    public function execute(): bool
    {
        try {
            if ($this->executed) {
                return true;
            }

            return $this->executed = $this->statement->execute();
        } catch (PDOException $exception) {
            $this->error = $exception->getMessage();
            if ($this->inTransaction()) {
                throw $exception;
            }

            return false;
        }
    }

    /**
     * Execute and fetch every row as an array of objects.
     *
     * @return array<int, object>
     */
    public function getObjects(): array
    {
        $this->execute();
        return $this->statement->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Execute and fetch a single row as an object.
     *
     * @return object|null
     */
    public function getObject(): ?object
    {
        $this->execute();
        $row = $this->statement->fetch(PDO::FETCH_OBJ);
        return $row === false ? null : $row;
    }

    /**
     * Execute and fetch a single row as an associative array.
     *
     * @return array<string, mixed>|null
     */
    public function getArray(): ?array
    {
        $this->execute();
        $row = $this->statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Execute and fetch every row as associative arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getArrays(): array
    {
        $this->execute();
        return $this->statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Execute and return the number of affected/returned rows.
     *
     * @return int
     */
    public function getRowCount(): int
    {
        $this->execute();
        return $this->statement->rowCount();
    }

    /**
     * Begin a transaction.
     *
     * @return void
     */
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /**
     * Run a single statement within the current transaction.
     *
     * @param string $sql The SQL query to execute.
     * @return bool True on success, false on failure.
     */
    public function runInTransaction(string $sql): bool
    {
        try {
            $this->query($sql);
            return $this->execute();
        } catch (PDOException $exception) {
            $this->error = $exception->getMessage();
            return false;
        }
    }

    /**
     * Commit the active transaction.
     *
     * @return bool True on success, false on failure.
     */
    public function commit(): bool
    {
        try {
            return $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->error = $exception->getMessage();
            return false;
        }
    }

    /**
     * Roll back the active transaction.
     *
     * @return void
     */
    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    /**
     * Determine whether a transaction is currently active.
     *
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->pdo !== null && $this->pdo->inTransaction();
    }

    /**
     * Get the ID of the last inserted row.
     *
     * @return string
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }
}
