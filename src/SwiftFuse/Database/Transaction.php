<?php

declare(strict_types=1);

namespace SwiftFuse\Database;

use LogicException;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Database transaction spanning every model of a request.
 *
 * Runs a callback inside one transaction on the shared connection
 * (database.shared_connection), so the writes of several models are committed
 * or rolled back together. Calls nest: an inner call joins the transaction that
 * is already open, and only the outermost call commits or rolls back.
 */
final class Transaction
{
    /**
     * Run a callback inside a transaction on the shared connection.
     *
     * The transaction commits when the callback returns, and rolls back when it
     * throws, rethrowing the same exception. A nested call joins the open
     * transaction; if a nested level fails and the outer callback recovers, the
     * outermost level can no longer commit: it rolls everything back and throws.
     *
     * @template T
     *
     * @param callable(Connection): T $callback Work to run; receives a connection on the shared handle.
     * @return T The value returned by the callback.
     *
     * @throws LogicException When database.shared_connection is disabled, since models would not share the transaction.
     * @throws PDOException When the connection is unavailable or the transaction cannot start.
     * @throws RuntimeException When the transaction cannot be committed or rolled back.
     * @throws Throwable Whatever the callback throws, once the transaction is rolled back.
     */
    public static function run(callable $callback): mixed
    {
        if (!Connection::isSharingEnabled()) {
            throw new LogicException(
                'Transaction::run() requires a shared database connection, otherwise each model would write '
                . 'outside the transaction. Set DB_SHARED_CONNECTION=true (config database.shared_connection).'
            );
        }

        $connection = new Connection();
        $connection->beginTransaction();

        try {
            $result = $callback($connection);
        } catch (Throwable $exception) {
            try {
                $connection->rollBack();
            } catch (PDOException $rollbackException) {
                throw new RuntimeException(
                    sprintf(
                        'The transaction could not be rolled back (%s) after the callback failed: %s',
                        $rollbackException->getMessage(),
                        $exception->getMessage()
                    ),
                    previous: $exception
                );
            }

            throw $exception;
        }

        if (!$connection->commit()) {
            throw new RuntimeException('The transaction could not be committed: ' . $connection->getError());
        }

        return $result;
    }
}
