<?php

declare(strict_types=1);

namespace App\Models;

use PDOException;
use SwiftFuse\Database\Model;

/**
 * Person model.
 *
 * Example MVC model backed by PDO, demonstrating the framework's database layer.
 * It reads from the "people" table shipped in DB/framework_test.sql.
 */
final class Person extends Model
{
    /**
     * Retrieve every person, ordered by name.
     *
     * On failure the error is captured in the model message and an empty array
     * is returned, so controllers can degrade gracefully.
     *
     * @return array<int, object> List of person rows as objects.
     */
    public function all(): array
    {
        try {
            $this->connection->query('SELECT peopleId, name, email FROM people ORDER BY name');
            return $this->connection->getObjects();
        } catch (PDOException $exception) {
            $this->message = $exception->getMessage();
            return [];
        }
    }
}
