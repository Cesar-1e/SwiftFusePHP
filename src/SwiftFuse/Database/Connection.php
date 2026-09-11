<?php

declare(strict_types=1);

namespace SwiftFuse\Database;

use LogicException;
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
 *
 * With database.shared_connection enabled, every instance reuses one PDO handle
 * per DSN and user, so the models of a request can join one transaction, while
 * each instance keeps its own statement and execution state. Transactions on a
 * shared handle nest: beginTransaction() opens a level, commit() and rollBack()
 * close the level the same instance opened, and only the outermost level
 * really commits or rolls back.
 */
class Connection
{
    /**
     * Error recorded when a transaction cannot commit because a nested level rolled it back.
     *
     * @var string
     */
    private const ROLLBACK_ONLY_ERROR = 'The transaction was rolled back because a nested level rolled it back.';

    /**
     * PDO handles shared while database.shared_connection is enabled, keyed by DSN and user.
     *
     * @var array<string, PDO>
     */
    private static array $sharedHandles = [];

    /**
     * Open transaction levels of each shared handle, keyed like $sharedHandles.
     *
     * @var array<string, int>
     */
    private static array $transactionLevels = [];

    /**
     * Whether a nested level rolled back the open transaction of each shared handle, keyed like $sharedHandles.
     *
     * @var array<string, bool>
     */
    private static array $rollbackOnly = [];

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
     * Key of the shared handle this instance uses, or null when it owns its PDO handle.
     *
     * @var string|null
     */
    private ?string $sharedKey = null;

    /**
     * Transaction levels this instance opened on the shared handle and has not closed yet.
     *
     * @var int
     */
    private int $openedLevels = 0;

    /**
     * Establish the PDO connection using the application configuration.
     *
     * With database.shared_connection enabled, the handle already opened for the
     * same DSN and user is reused instead of connecting again.
     */
    public function __construct()
    {
        $driver = (string) config('database.driver', 'mysql');
        $host = (string) config('database.host', 'localhost');
        $port = (string) config('database.port', '3306');
        $database = (string) config('database.database', '');
        $charset = (string) config('database.charset', 'utf8mb4');
        $username = (string) config('database.username', '');

        $dsn = "{$driver}:host={$host};port={$port};dbname={$database};charset={$charset}";

        if (self::isSharingEnabled()) {
            $this->sharedKey = "{$dsn};user={$username}";
            if (isset(self::$sharedHandles[$this->sharedKey])) {
                $this->pdo = self::$sharedHandles[$this->sharedKey];
                return;
            }
        }

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
            $this->pdo = new PDO($dsn, $username, (string) config('database.password', ''), $options);
        } catch (PDOException $exception) {
            $this->error = $exception->getMessage();
            return;
        }

        if ($this->sharedKey !== null) {
            self::$sharedHandles[$this->sharedKey] = $this->pdo;
        }
    }

    /**
     * Determine whether connections share one PDO handle (config database.shared_connection).
     *
     * @return bool
     */
    public static function isSharingEnabled(): bool
    {
        return (bool) config('database.shared_connection', false);
    }

    /**
     * Forget the shared PDO handles, so the next connection opens a new one.
     *
     * Useful between the jobs of a long-running worker, whose shared handle could
     * otherwise outlive the database server's idle timeout. Instances created
     * earlier keep the handle they already hold.
     *
     * @return void
     *
     * @throws LogicException When a shared transaction is still open.
     */
    public static function flushSharedConnections(): void
    {
        if (self::$transactionLevels !== []) {
            throw new LogicException('Shared connections cannot be flushed while a shared transaction is open.');
        }

        self::$sharedHandles = [];
        self::$rollbackOnly = [];
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
     * On a shared handle, a transaction opened by any instance counts as active,
     * so a failure inside Transaction::run() is rethrown to trigger its rollback.
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
     * On a shared handle whose transaction is already open, the call joins it by
     * opening a nested level, which this instance closes with commit() or
     * rollBack().
     *
     * @return void
     *
     * @throws PDOException When the transaction cannot be started.
     */
    public function beginTransaction(): void
    {
        if ($this->sharedKey === null) {
            $this->pdo->beginTransaction();
            return;
        }

        $level = self::$transactionLevels[$this->sharedKey] ?? 0;
        if ($level === 0) {
            $this->pdo()->beginTransaction();
            self::$rollbackOnly[$this->sharedKey] = false;
        }

        self::$transactionLevels[$this->sharedKey] = $level + 1;
        $this->openedLevels++;
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
     * On a shared transaction, closing a nested level only hands control back to
     * the enclosing level. The outermost level commits, unless a nested level
     * rolled back: then it rolls everything back and returns false. An instance
     * that did not open the shared transaction cannot commit it.
     *
     * @return bool True on success, false on failure.
     */
    public function commit(): bool
    {
        $key = $this->openSharedTransactionKey();
        if ($key !== null) {
            return $this->commitSharedLevel($key);
        }

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
     * A shared transaction is rolled back as a whole by its outermost level. A
     * nested level, or an instance that did not open the transaction, cannot
     * undo part of it: the call marks the transaction for rollback, so the
     * outermost commit() rolls it back.
     *
     * @return void
     */
    public function rollBack(): void
    {
        $key = $this->openSharedTransactionKey();
        if ($key !== null) {
            $this->rollBackSharedLevel($key);
            return;
        }

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

    /**
     * Get the key of this instance's shared handle when a transaction is open on it.
     *
     * @return string|null The shared handle key, or null for an own handle or no open shared transaction.
     */
    private function openSharedTransactionKey(): ?string
    {
        if ($this->sharedKey === null || !isset(self::$transactionLevels[$this->sharedKey])) {
            return null;
        }

        return $this->sharedKey;
    }

    /**
     * Close a level of the open shared transaction by committing it.
     *
     * @param string $key Shared handle key.
     * @return bool True when the level closed and the transaction can still commit, false otherwise.
     */
    private function commitSharedLevel(string $key): bool
    {
        if ($this->openedLevels === 0) {
            $this->error = 'This connection did not begin the shared transaction, so it cannot commit it.';
            return false;
        }

        $this->openedLevels--;
        $level = self::$transactionLevels[$key];
        $isRollbackOnly = self::$rollbackOnly[$key] ?? false;

        if ($level > 1) {
            self::$transactionLevels[$key] = $level - 1;
            if ($isRollbackOnly) {
                $this->error = self::ROLLBACK_ONLY_ERROR;
                return false;
            }

            return true;
        }

        unset(self::$transactionLevels[$key], self::$rollbackOnly[$key]);

        try {
            if ($isRollbackOnly) {
                $this->pdo()->rollBack();
                $this->error = self::ROLLBACK_ONLY_ERROR;
                return false;
            }

            return $this->pdo()->commit();
        } catch (PDOException $exception) {
            $this->error = $exception->getMessage();
            return false;
        }
    }

    /**
     * Close a level of the open shared transaction by rolling it back.
     *
     * @param string $key Shared handle key.
     * @return void
     *
     * @throws PDOException When the outermost rollback fails.
     */
    private function rollBackSharedLevel(string $key): void
    {
        $level = self::$transactionLevels[$key];

        if ($this->openedLevels === 0 || $level > 1) {
            // Part of a transaction cannot be undone: doom it so the outermost level rolls everything back.
            self::$rollbackOnly[$key] = true;
            if ($this->openedLevels > 0) {
                $this->openedLevels--;
                self::$transactionLevels[$key] = $level - 1;
            }

            return;
        }

        $this->openedLevels--;
        unset(self::$transactionLevels[$key], self::$rollbackOnly[$key]);
        $this->pdo()->rollBack();
    }
}
