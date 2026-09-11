# Database (PDO + MVC)

[← Back to README](../README.md)

SwiftFusePHP uses **PDO** exclusively, through `SwiftFuse\Database\Connection`.
Models extend `SwiftFuse\Database\Model`, which owns a connection and exposes the
last message/entity it produced. Connection settings come from
[`config/database.php`](../config/database.php) (see [CONFIGURATION.md](CONFIGURATION.md)).

## A model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use PDOException;
use SwiftFuse\Database\Model;

final class Person extends Model
{
    /** @return array<int, object> */
    public function all(): array
    {
        try {
            $this->connection->query('SELECT peopleId, name, email FROM people ORDER BY name');
            return $this->connection->getObjects();
        } catch (PDOException $e) {
            $this->message = $e->getMessage();   // surfaced via getMessage()
            return [];
        }
    }
}
```

Load it from a controller with `$this->model('Person')` and read errors with
`$model->getMessage()`.

## `Model` base class

| Member | Description |
|--------|-------------|
| `protected Connection $connection` | The PDO connection. |
| `protected ?string $message` | Last message (e.g. an error). |
| `protected ?object $entity` | Optional associated entity. |
| `getMessage(): ?string` | Read the last message. |
| `getEntity(): ?object` | Read the entity. |

## `Connection` API

### Prepared statements (always)

```php
$this->connection->query('SELECT * FROM users WHERE email = :email AND active = :active');
$this->connection->bind(':email', $email);
$this->connection->bind(':active', 1);          // type auto-detected
$user = $this->connection->getObject();
```

`bind()` auto-detects the PDO type (int, bool, null, string) and JSON-encodes
arrays. Pass an explicit `PDO::PARAM_*` as the third argument to override it.

### Fetching

| Method | Returns |
|--------|---------|
| `getObjects()` | `array<int, object>` — all rows as objects |
| `getObject()` | `object\|null` — one row as an object |
| `getArrays()` | `array<int, array>` — all rows as associative arrays |
| `getArray()` | `array\|null` — one row as an associative array |
| `getRowCount()` | `int` — affected/returned rows |
| `lastInsertId()` | `string` — last auto-increment id |

`execute()` runs the prepared statement explicitly and returns a `bool`; the
fetch helpers call it for you, so you rarely need it directly.

### Writes

```php
$this->connection->query('INSERT INTO people (name, email) VALUES (:n, :e)');
$this->connection->bind(':n', $name);
$this->connection->bind(':e', $email);
$this->connection->execute();
$id = $this->connection->lastInsertId();
```

### Transactions

```php
$db = $this->connection;
$db->beginTransaction();
try {
    $db->query('UPDATE accounts SET balance = balance - :a WHERE id = :id');
    $db->bind(':a', 100); $db->bind(':id', 1); $db->execute();

    $db->query('UPDATE accounts SET balance = balance + :a WHERE id = :id');
    $db->bind(':a', 100); $db->bind(':id', 2); $db->execute();

    $db->commit();
} catch (\PDOException $e) {
    $db->rollBack();
    $this->message = $e->getMessage();
}
```

Inside an active transaction, a failed `execute()` **throws** so your rollback
runs; outside a transaction it returns `false` and stores the error
(`getError()`), letting controllers degrade gracefully.

`runInTransaction(string $sql)` is a shortcut for a single statement, and
`inTransaction()` tells you whether one is active.

## Transactions across models

By default every model opens its **own** connection, so the models of a request
cannot share a transaction: committing one never includes the writes of another.
When an operation spans several models — an invoice and its lines, a standard
record and its custom fields — enable the **shared connection** and wrap the work
in `SwiftFuse\Database\Transaction::run()`.

### 1. Enable the shared connection

```dotenv
DB_SHARED_CONNECTION=true
```

`database.shared_connection` is `false` by default, which keeps the
one-connection-per-model behavior of earlier versions. When it is `true`:

- Every `Connection` of the process reuses **one PDO handle per DSN and user**,
  so all models talk to the same database session.
- Each instance still keeps its **own** prepared statement, execution state and
  last error, so models never read each other's results.
- `inTransaction()` reports the transaction of the shared session, whichever
  instance opened it.
- `lastInsertId()` belongs to the session as well: read it right after your insert.

> Projects that keep their own copy of `config/database.php` must add the key for
> the variable to take effect:
> `'shared_connection' => (bool) env('DB_SHARED_CONNECTION', false),`

### 2. Run the work in a transaction

```php
use App\Models\Invoice;
use App\Models\InvoiceLine;
use SwiftFuse\Database\Transaction;

$invoiceId = Transaction::run(function () use ($data): int {
    $invoiceId = (new Invoice())->create($data);
    (new InvoiceLine())->createMany($invoiceId, $data['lines']);

    return $invoiceId;
});
```

`run()` begins a transaction, runs the callback and then:

- **commits** when the callback returns, and returns the callback's value;
- **rolls back and rethrows** the same exception when the callback throws any
  `Throwable`.

The callback receives the `Connection` that opened the transaction, handy for SQL
that does not belong to a model. Do not commit or roll back that level yourself:
return or throw instead.

When the shared connection is disabled, `run()` throws a `LogicException` rather
than pretending that separate connections are atomic.

### Nesting

Calls nest, and only the **outermost** level commits or rolls back:

- An inner `Transaction::run()` joins the open transaction; returning from it
  commits nothing yet.
- An exception that escapes an inner level reaches the outer callback; if it keeps
  propagating, the outer level rolls everything back.
- If the outer callback **catches** the failure of an inner level, the transaction
  can no longer commit: the outer level rolls everything back and throws a
  `RuntimeException`. A partial write is never committed.

Existing models that manage their own transaction keep working inside `run()`. On
a shared handle, `beginTransaction()` opens a nested level, `commit()` closes it
without committing, and `rollBack()` marks the whole transaction for rollback. A
connection that did not begin the transaction cannot end it: its `commit()`
returns `false` and its `rollBack()` only marks the transaction for rollback.

### How `execute()` behaves inside the shared transaction

`execute()` keeps its contract: **outside** a transaction it catches the
`PDOException`, records the message (`getError()`) and returns `false`; **inside**
a transaction it rethrows the exception.

With the shared connection, *inside* means inside the transaction of the shared
session, whoever opened it. So a model method that returns `false` on its own
**throws** when it runs within `Transaction::run()`. That is the desired behavior:

- A `false` return would let the callback carry on and commit a partial result,
  such as an invoice without its lines. The exception unwinds the callback
  instead, so `run()` rolls back the writes of every model.
- MySQL usually undoes only the failed statement and keeps the transaction open,
  so the earlier writes stay pending until someone rolls back explicitly;
  rethrowing is what triggers that rollback.
- The caller receives the real database error instead of a silent `false`.

For the same reason, a model that catches `PDOException` to degrade gracefully
(like `Person::all()` above) should rethrow it when `inTransaction()` is true.

### Things to keep in mind

- **Respond after the transaction.** `json()` ends the script: called inside the
  callback, it leaves the transaction open and PHP rolls it back on shutdown, so
  the client would get a success response for discarded data. Return what you
  need from the callback and respond once `run()` has returned.
- **Long-running processes.** A worker keeps the shared handle for its whole life.
  Call `Connection::flushSharedConnections()` between jobs so the next job opens a
  fresh handle; it throws a `LogicException` while a shared transaction is open.

| Member | Description |
|--------|-------------|
| `Transaction::run(callable $callback): mixed` | Run the callback in a transaction on the shared connection. |
| `Connection::isSharingEnabled(): bool` | Whether `database.shared_connection` is enabled. |
| `Connection::flushSharedConnections(): void` | Forget the shared handles, e.g. between the jobs of a worker. |

## Error handling

- `Connection::getError()` returns the last connection/execution error message.
- On construction, a connection error is captured (not thrown), so `Model`
  surfaces it via `getMessage()` instead of crashing the request.

## Entities (optional)

For richer domain objects you can pair a model with an entity class (a plain PHP
object with typed getters/setters). Assign it to `$this->entity` and expose it via
`getEntity()`. Entities are optional — many models simply return row objects.

## Stored procedures

PDO supports calling stored procedures directly:

```php
$this->connection->query('CALL PR_getAllPeople()');
$rows = $this->connection->getArrays();
```
