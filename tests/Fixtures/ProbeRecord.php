<?php

declare(strict_types=1);

namespace SwiftFuse\Tests\Fixtures;

use RuntimeException;
use SwiftFuse\Database\Connection;
use SwiftFuse\Database\Model;
use Throwable;

/**
 * Model fixture writing (id, label) rows to a test table, for the transaction tests.
 */
abstract class ProbeRecord extends Model
{
    /**
     * @param string $table Table this model writes to.
     */
    public function __construct(private readonly string $table)
    {
        parent::__construct();
    }

    /**
     * Insert a row and report the result of execute().
     *
     * @param int $id Primary key.
     * @param string $label Row label.
     * @return bool True on success, false on failure outside a transaction.
     */
    public function insert(int $id, string $label): bool
    {
        $this->connection->query("INSERT INTO {$this->table} (id, label) VALUES (:id, :label)");
        $this->connection->bind(':id', $id);
        $this->connection->bind(':label', $label);

        return $this->connection->execute();
    }

    /**
     * Get the database session id of this model's connection.
     *
     * @return int
     */
    public function connectionId(): int
    {
        $this->connection->query('SELECT CONNECTION_ID() AS id');

        return (int) $this->connection->getObject()->id;
    }

    /**
     * Insert a row inside the model's own transaction, like existing models do.
     *
     * @param int $id Primary key.
     * @param string $label Row label.
     * @param bool $failAfterInsert Throw after the insert to exercise the model's rollback.
     * @return void
     *
     * @throws RuntimeException When $failAfterInsert is true.
     */
    public function saveInOwnTransaction(int $id, string $label, bool $failAfterInsert = false): void
    {
        $db = $this->connection;
        $db->beginTransaction();

        try {
            $this->insert($id, $label);
            if ($failAfterInsert) {
                throw new RuntimeException('Simulated failure after the insert.');
            }
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Roll back when a transaction is active, without having begun one.
     *
     * @return void
     */
    public function rollBackWithoutBeginning(): void
    {
        if ($this->connection->inTransaction()) {
            $this->connection->rollBack();
        }
    }

    /**
     * Commit without having begun a transaction.
     *
     * @return bool The result of commit().
     */
    public function commitWithoutBeginning(): bool
    {
        return $this->connection->commit();
    }

    /**
     * Expose the model's connection to the tests.
     *
     * @return Connection
     */
    public function connection(): Connection
    {
        return $this->connection;
    }
}
