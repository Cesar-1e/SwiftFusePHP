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
