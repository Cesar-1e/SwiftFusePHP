<?php

declare(strict_types=1);

namespace SwiftFuse\Database;

/**
 * Base model class.
 *
 * Every application model should extend this class. It owns a database
 * connection and exposes the last message/entity produced by an operation.
 * This is the English successor to the legacy abstract Model class.
 */
abstract class Model
{
    /**
     * The shared database connection for this model.
     *
     * @var Connection
     */
    protected Connection $connection;

    /**
     * The last human-readable message produced by a model operation.
     *
     * @var string|null
     */
    protected ?string $message = null;

    /**
     * The entity associated with this model, when applicable.
     *
     * @var object|null
     */
    protected ?object $entity = null;

    /**
     * Open the database connection and capture any connection error.
     */
    public function __construct()
    {
        $this->connection = new Connection();
        if ($this->connection->getError() !== null) {
            $this->message = $this->connection->getError();
        }
    }

    /**
     * Get the last message produced by a model operation.
     *
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Get the entity associated with this model.
     *
     * @return object|null
     */
    public function getEntity(): ?object
    {
        return $this->entity;
    }
}
