<?php

declare(strict_types=1);

namespace SwiftFuse\Http;

use RuntimeException;
use Throwable;

/**
 * Exception that carries an HTTP status code.
 *
 * Throwing this from anywhere in the request lifecycle lets the framework send
 * the correct status and render the matching error view, replacing the legacy
 * procedural error()/setStatusCode() flow.
 */
class HttpException extends RuntimeException
{
    /**
     * The HTTP status code associated with this exception.
     *
     * @var int
     */
    protected int $statusCode;

    /**
     * @param int $statusCode HTTP status code (e.g. 404, 403, 500).
     * @param string $message Optional developer-facing message.
     * @param Throwable|null $previous Previous exception for chaining.
     */
    public function __construct(int $statusCode, string $message = '', ?Throwable $previous = null)
    {
        $this->statusCode = $statusCode;
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the HTTP status code carried by this exception.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
