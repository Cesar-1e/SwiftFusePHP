<?php

/**
 * Database: shared connection and Transaction::run() across models.
 *
 * Needs a MySQL database reachable with the DB_* settings (environment or .env).
 * Without one, the database cases are skipped (exit code 77). The script creates
 * two uniquely named swiftfuse_test_* tables and drops them when it ends.
 */

declare(strict_types=1);

use SwiftFuse\Database\Connection;
use SwiftFuse\Database\Transaction;
use SwiftFuse\Support\Config;
use SwiftFuse\Support\Env;
use SwiftFuse\Tests\Fixtures\ProbeOrder;
use SwiftFuse\Tests\Fixtures\ProbeOrderLine;
use SwiftFuse\Tests\Support\TestRun;

require __DIR__ . '/bootstrap.php';

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');
// A persistent handle would be reused by every non-shared connection; here each one must be its own session.
Config::set('database.persistent', false);
Config::set('database.shared_connection', false);

$test = new TestRun('Database: shared connection and Transaction::run()');

$test->test('Transaction::run() refuses to fake atomicity without sharing', function () use ($test): void {
    $calls = new ArrayObject();

    $exception = $test->assertThrows(
        LogicException::class,
        static fn (): mixed => Transaction::run(static fn () => $calls->append('called')),
        'run() throws a LogicException'
    );

    $test->assertTrue(
        $exception !== null && str_contains($exception->getMessage(), 'DB_SHARED_CONNECTION'),
        'the message explains how to enable the shared connection'
    );
    $test->assertSame([], $calls->getArrayCopy(), 'the callback never runs');
});

$availability = new Connection();
if ($availability->getError() !== null) {
    $test->skipRemaining('the database is not reachable: ' . $availability->getError());
}
unset($availability);

$observer = new PDO(
    sprintf(
        '%s:host=%s;port=%s;dbname=%s;charset=%s',
        (string) config('database.driver'),
        (string) config('database.host'),
        (string) config('database.port'),
        (string) config('database.database'),
        (string) config('database.charset')
    ),
    (string) config('database.username'),
    (string) config('database.password'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
// A failing case may leave locks behind; the observer must fail fast instead of hanging on them.
$observer->exec('SET SESSION lock_wait_timeout = 5, SESSION innodb_lock_wait_timeout = 5');

$suffix = bin2hex(random_bytes(4));
$ordersTable = "swiftfuse_test_orders_{$suffix}";
$linesTable = "swiftfuse_test_lines_{$suffix}";

foreach ([$ordersTable, $linesTable] as $table) {
    $observer->exec("CREATE TABLE {$table} (id INT NOT NULL PRIMARY KEY, label VARCHAR(40) NOT NULL) ENGINE=InnoDB");
}

register_shutdown_function(static function () use ($observer, $ordersTable, $linesTable): void {
    try {
        $observer->exec("DROP TABLE IF EXISTS {$ordersTable}, {$linesTable}");
    } catch (PDOException $exception) {
        fwrite(
            STDERR,
            "The test tables {$ordersTable} and {$linesTable} were not dropped: {$exception->getMessage()}\n"
        );
    }
});

/**
 * Count the committed rows of a table, as an independent session sees them.
 *
 * @param string $table Table name.
 * @return int
 */
$count = static fn (string $table): int => (int) $observer
    ->query("SELECT COUNT(*) FROM {$table}")
    ->fetchColumn();

/**
 * Empty both tables and choose whether the next connections share one handle.
 *
 * @param bool $shared Value of database.shared_connection.
 * @return void
 */
$prepare = static function (bool $shared) use ($observer, $ordersTable, $linesTable): void {
    $observer->exec("DELETE FROM {$ordersTable}");
    $observer->exec("DELETE FROM {$linesTable}");
    Config::set('database.shared_connection', $shared);
    Connection::flushSharedConnections();
};

$test->test('without sharing, each model has its own session and transaction', function () use (
    $test,
    $prepare,
    $ordersTable,
    $linesTable
): void {
    $prepare(false);
    $order = new ProbeOrder($ordersTable);
    $line = new ProbeOrderLine($linesTable);

    $test->assertTrue($order->connectionId() !== $line->connectionId(), 'the models use different sessions');

    $order->connection()->beginTransaction();
    $test->assertTrue(!$line->connection()->inTransaction(), "one model's transaction is invisible to the other");
    $order->connection()->rollBack();
});

$test->test('with sharing, models reuse one session but keep their own statements', function () use (
    $test,
    $prepare,
    $ordersTable,
    $linesTable
): void {
    $prepare(true);
    $order = new ProbeOrder($ordersTable);
    $line = new ProbeOrderLine($linesTable);

    $test->assertSame($order->connectionId(), $line->connectionId(), 'both models use the same session');

    $order->connection()->query('SELECT 1 AS n');
    $line->connection()->query('SELECT 2 AS n');
    $test->assertSame(2, (int) $line->connection()->getObject()->n, 'the second model reads its own statement');
    $test->assertSame(1, (int) $order->connection()->getObject()->n, 'the first model still reads its own');
});

$test->test('outside a transaction, a failing execute() returns false instead of throwing', function () use (
    $test,
    $prepare,
    $ordersTable
): void {
    foreach (['without sharing' => false, 'with sharing' => true] as $mode => $shared) {
        $prepare($shared);
        $order = new ProbeOrder($ordersTable);

        $test->assertTrue($order->insert(1, 'first'), "the first insert succeeds ({$mode})");
        $test->assertSame(false, $order->insert(1, 'duplicate'), "the duplicate insert returns false ({$mode})");
        $test->assertTrue(
            str_contains((string) $order->connection()->getError(), '23000'),
            "the error is recorded ({$mode})"
        );
    }
});

$test->test('two models writing inside Transaction::run() are committed together', function () use (
    $test,
    $prepare,
    $count,
    $ordersTable,
    $linesTable
): void {
    $prepare(true);
    $order = new ProbeOrder($ordersTable);

    $result = Transaction::run(static function (Connection $connection) use (
        $test,
        $count,
        $order,
        $ordersTable,
        $linesTable
    ): string {
        $order->insert(1, 'order');
        (new ProbeOrderLine($linesTable))->insert(1, 'line');

        $test->assertTrue($connection->inTransaction(), 'the callback runs inside a transaction');
        $test->assertSame([0, 0], [$count($ordersTable), $count($linesTable)], 'other sessions see nothing yet');

        return 'saved';
    });

    $test->assertSame('saved', $result, 'run() returns the value of the callback');
    $test->assertSame([1, 1], [$count($ordersTable), $count($linesTable)], 'both rows are committed');
    $test->assertTrue(!$order->connection()->inTransaction(), 'no transaction is left open');
});

$test->test('two models inside Transaction::run() are rolled back together when the callback throws', function () use (
    $test,
    $prepare,
    $count,
    $ordersTable,
    $linesTable
): void {
    $prepare(true);
    $failure = new RuntimeException('Simulated failure after both writes.');

    $thrown = $test->assertThrows(
        RuntimeException::class,
        static fn (): mixed => Transaction::run(static function () use ($failure, $ordersTable, $linesTable): void {
            (new ProbeOrder($ordersTable))->insert(2, 'order');
            (new ProbeOrderLine($linesTable))->insert(2, 'line');
            throw $failure;
        }),
        'run() rethrows'
    );

    $test->assertSame($failure, $thrown, 'the exception of the callback is rethrown as is');
    $test->assertSame([0, 0], [$count($ordersTable), $count($linesTable)], 'neither row is committed');
    $test->assertTrue(
        !(new ProbeOrder($ordersTable))->connection()->inTransaction(),
        'no transaction is left open'
    );
});

$test->test('inside Transaction::run(), a failing execute() throws and everything rolls back', function () use (
    $test,
    $prepare,
    $count,
    $observer,
    $ordersTable,
    $linesTable
): void {
    $prepare(true);
    $observer->exec("INSERT INTO {$linesTable} (id, label) VALUES (3, 'committed earlier')");

    $test->assertThrows(
        PDOException::class,
        static fn (): mixed => Transaction::run(static function () use ($ordersTable, $linesTable): void {
            (new ProbeOrder($ordersTable))->insert(3, 'order');
            (new ProbeOrderLine($linesTable))->insert(3, 'duplicate');
        }),
        'the PDOException raised by execute() propagates'
    );

    $test->assertSame(0, $count($ordersTable), 'the write of the other model is rolled back');
    $test->assertSame(1, $count($linesTable), 'rows committed before the transaction are untouched');
});

$test->test('a nested Transaction::run() joins the outer one, and only the outer level commits', function () use (
    $test,
    $prepare,
    $count,
    $ordersTable,
    $linesTable
): void {
    $prepare(true);

    Transaction::run(static function () use ($test, $count, $ordersTable, $linesTable): void {
        (new ProbeOrder($ordersTable))->insert(4, 'order');
        $inner = Transaction::run(static fn (): bool => (new ProbeOrderLine($linesTable))->insert(4, 'line'));

        $test->assertSame(true, $inner, 'the inner run() returns the value of its callback');
        $test->assertSame(0, $count($linesTable), 'the inner level does not commit');
    });

    $test->assertSame([1, 1], [$count($ordersTable), $count($linesTable)], 'the outer level commits both writes');
});

$test->test('a failed nested level rolls everything back even if the outer callback recovers', function () use (
    $test,
    $prepare,
    $count,
    $ordersTable,
    $linesTable
): void {
    $prepare(true);

    $exception = $test->assertThrows(
        RuntimeException::class,
        static fn (): mixed => Transaction::run(static function () use ($ordersTable, $linesTable): void {
            (new ProbeOrder($ordersTable))->insert(5, 'order');
            try {
                Transaction::run(static function () use ($linesTable): void {
                    (new ProbeOrderLine($linesTable))->insert(5, 'line');
                    throw new DomainException('Nested failure.');
                });
            } catch (DomainException) {
                // Recovering on purpose: the transaction must still refuse to commit.
            }
        }),
        'the outer run() cannot commit'
    );

    $test->assertTrue(
        $exception !== null && str_contains($exception->getMessage(), 'nested level'),
        'the message explains that a nested level rolled back'
    );
    $test->assertSame([0, 0], [$count($ordersTable), $count($linesTable)], 'nothing is committed');
});

$test->test('an exception from a nested level propagates and the outer level rolls back', function () use (
    $test,
    $prepare,
    $count,
    $ordersTable,
    $linesTable
): void {
    $prepare(true);

    $test->assertThrows(
        DomainException::class,
        static fn (): mixed => Transaction::run(static function () use ($ordersTable, $linesTable): void {
            (new ProbeOrder($ordersTable))->insert(6, 'order');
            Transaction::run(static function () use ($linesTable): void {
                (new ProbeOrderLine($linesTable))->insert(6, 'line');
                throw new DomainException('Nested failure.');
            });
        }),
        'the nested exception reaches the caller unchanged'
    );

    $test->assertSame([0, 0], [$count($ordersTable), $count($linesTable)], 'nothing is committed');
});

$test->test('models with their own begin/commit join Transaction::run() instead of committing early', function () use (
    $test,
    $prepare,
    $count,
    $ordersTable,
    $linesTable
): void {
    $prepare(true);

    Transaction::run(static function () use ($test, $count, $ordersTable, $linesTable): void {
        (new ProbeOrder($ordersTable))->saveInOwnTransaction(7, 'order');
        $test->assertSame(0, $count($ordersTable), "the model's commit() does not commit the shared transaction");

        (new ProbeOrderLine($linesTable))->saveInOwnTransaction(7, 'line');
    });

    $test->assertSame([1, 1], [$count($ordersTable), $count($linesTable)], 'the outer level commits both models');
});

$test->test('a model that rolls back its own transaction dooms the shared transaction', function () use (
    $test,
    $prepare,
    $count,
    $ordersTable,
    $linesTable
): void {
    $prepare(true);

    $test->assertThrows(
        RuntimeException::class,
        static fn (): mixed => Transaction::run(static function () use ($ordersTable, $linesTable): void {
            (new ProbeOrder($ordersTable))->saveInOwnTransaction(8, 'order');
            try {
                (new ProbeOrderLine($linesTable))->saveInOwnTransaction(8, 'line', true);
            } catch (RuntimeException) {
                // Swallowed on purpose: the rollback of the model alone must prevent the commit.
            }
        }),
        'the outer run() cannot commit'
    );

    $test->assertSame([0, 0], [$count($ordersTable), $count($linesTable)], 'nothing is committed');
});

$test->test('a model cannot commit or end a shared transaction it did not begin', function () use (
    $test,
    $prepare,
    $count,
    $ordersTable,
    $linesTable
): void {
    $prepare(true);

    $test->assertThrows(
        RuntimeException::class,
        static fn (): mixed => Transaction::run(static function (Connection $connection) use (
            $test,
            $count,
            $ordersTable,
            $linesTable
        ): void {
            $line = new ProbeOrderLine($linesTable);
            (new ProbeOrder($ordersTable))->insert(9, 'order');

            $test->assertSame(false, $line->commitWithoutBeginning(), 'commit() without beginTransaction() fails');
            $test->assertSame(0, $count($ordersTable), 'and does not commit the shared transaction');

            $line->rollBackWithoutBeginning();
            $test->assertTrue($connection->inTransaction(), 'rollBack() without beginTransaction() keeps it open');
        }),
        'a transaction marked for rollback cannot commit'
    );

    $test->assertSame(0, $count($ordersTable), 'nothing is committed');
});

$test->test('shared handles cannot be flushed while a shared transaction is open', function () use (
    $test,
    $prepare,
    $ordersTable
): void {
    $prepare(true);
    $firstSession = (new ProbeOrder($ordersTable))->connectionId();

    Transaction::run(static function () use ($test): void {
        $test->assertThrows(
            LogicException::class,
            static fn () => Connection::flushSharedConnections(),
            'flushing inside the transaction throws'
        );
    });

    Connection::flushSharedConnections();
    $test->assertTrue(
        (new ProbeOrder($ordersTable))->connectionId() !== $firstSession,
        'after a flush, the next model opens a new session'
    );
});

$test->finish();
