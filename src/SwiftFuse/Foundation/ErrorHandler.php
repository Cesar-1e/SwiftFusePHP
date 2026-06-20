<?php

declare(strict_types=1);

namespace SwiftFuse\Foundation;

use SwiftFuse\Http\HttpException;
use SwiftFuse\Support\View;
use Throwable;

/**
 * Centralized error and exception handler.
 *
 * Registers PHP error, exception and shutdown handlers; logs failures to
 * storage/logs/error.log; and renders the appropriate HTTP status with a
 * matching error view. This is the English replacement for the procedural
 * handler in Errores/log.php.
 */
final class ErrorHandler
{
    /**
     * Standard HTTP reason phrases used when sending status lines.
     *
     * @var array<int, string>
     */
    private const REASON_PHRASES = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        419 => 'Authentication Timeout',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        503 => 'Service Unavailable',
    ];

    /**
     * Whether debug mode is enabled (verbose output).
     *
     * @var bool
     */
    private bool $debug;

    /**
     * Absolute path to the log file.
     *
     * @var string
     */
    private string $logFile;

    /**
     * @param bool $debug Whether to expose detailed error output.
     * @param string $logFile Absolute path to the error log file.
     */
    public function __construct(bool $debug, string $logFile)
    {
        $this->debug = $debug;
        $this->logFile = $logFile;
    }

    /**
     * Register this handler for PHP errors, exceptions and fatal shutdowns.
     *
     * @return void
     */
    public function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', $this->debug ? '1' : '0');

        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'renderThrowable']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Convert a PHP error into an ErrorException so it flows through one path.
     *
     * @param int $level Error level (E_*).
     * @param string $message Error message.
     * @param string $file File where the error occurred.
     * @param int $line Line where the error occurred.
     * @return bool Always false, so PHP's internal handler still runs when needed.
     */
    public function handleError(int $level, string $message, string $file = '', int $line = 0): bool
    {
        if ((error_reporting() & $level) === 0) {
            return false;
        }

        $this->log(sprintf('PHP %d: %s in %s on line %d', $level, $message, $file, $line));

        return false;
    }

    /**
     * Capture fatal errors during shutdown and render a 500 response.
     *
     * @return void
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();
        $fatal = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;

        if ($error !== null && ($error['type'] & $fatal) !== 0) {
            $this->log(sprintf('FATAL: %s in %s on line %d', $error['message'], $error['file'], $error['line']));
            $this->renderStatus(500, $error['message']);
        }
    }

    /**
     * Render an HttpException using its own status code.
     *
     * @param HttpException $exception The HTTP exception to render.
     * @return void
     */
    public function renderHttpException(HttpException $exception): void
    {
        if ($exception->getMessage() !== '') {
            $this->log(sprintf('HTTP %d: %s', $exception->getStatusCode(), $exception->getMessage()));
        }

        $this->renderStatus($exception->getStatusCode(), $exception->getMessage());
    }

    /**
     * Log and render any unhandled throwable as a 500 (or its HTTP status).
     *
     * @param Throwable $exception The throwable to render.
     * @return void
     */
    public function renderThrowable(Throwable $exception): void
    {
        if ($exception instanceof HttpException) {
            $this->renderHttpException($exception);
            return;
        }

        $this->log(sprintf(
            '%s: %s in %s on line %d',
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        ));

        $this->renderStatus(500, $exception->getMessage());
    }

    /**
     * Append a timestamped entry to the error log.
     *
     * @param string $message The message to record.
     * @return void
     */
    public function log(string $message): void
    {
        $directory = dirname($this->logFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $entry = sprintf('[%s] %s%s', date('Y-m-d H:i:s'), $message, PHP_EOL);
        error_log($entry, 3, $this->logFile);
    }

    /**
     * Send the HTTP status line and render the matching error view.
     *
     * @param int $status HTTP status code.
     * @param string $message Detail message (only shown in debug mode).
     * @return void
     */
    private function renderStatus(int $status, string $message = ''): void
    {
        if (!headers_sent()) {
            $reason = self::REASON_PHRASES[$status] ?? 'Error';
            http_response_code($status);
            header(sprintf('HTTP/1.1 %d %s', $status, $reason));
        }

        if (View::resolve("errors.{$status}") !== null) {
            View::render("errors.{$status}", ['status' => $status, 'message' => $message, 'debug' => $this->debug]);
            return;
        }

        $detail = $this->debug && $message !== '' ? ': ' . htmlspecialchars($message, ENT_QUOTES) : '';
        echo sprintf('<h1>%d %s</h1><p>%s%s</p>', $status, self::REASON_PHRASES[$status] ?? 'Error', 'The request could not be completed', $detail);
    }
}
