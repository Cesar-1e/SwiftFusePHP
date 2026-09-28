<?php

declare(strict_types=1);

namespace SwiftFuse\Queue;

use SwiftFuse\Contracts\JobInterface;
use Throwable;

/**
 * Background job queue manager.
 *
 * Dispatches jobs for out-of-band execution. Three drivers are supported:
 *
 *   - "file" (default): the job is serialized to storage/framework/jobs and
 *     later processed by a worker (php fuse queue:work). Requires no extra
 *     infrastructure, so it works on shared hosting.
 *   - "async": the job is serialized and immediately handed to a detached PHP
 *     process for fire-and-forget execution. When exec() is unavailable it
 *     degrades to "deferred" and logs a warning once per process.
 *   - "deferred": the job is serialized like "file" and, in an HTTP request
 *     served by PHP-FPM or LiteSpeed, runs in the same process right after the
 *     response has been sent to the client. Elsewhere it behaves like "file".
 *
 * Every driver leaves the job file on disk, so a cron-driven worker picks it up
 * whenever the immediate execution does not happen. Workers reserve each job
 * before running it (see Worker), so a job never runs twice.
 *
 * This is the structured evolution of the legacy BackgroundService.
 */
final class QueueManager
{
    /**
     * Jobs registered by the "deferred" driver in this process, in dispatch order.
     *
     * @var array<int, array{queue: QueueManager, file: string}>
     */
    private static array $deferred = [];

    /**
     * Whether the shutdown function that runs deferred jobs is registered.
     *
     * @var bool
     */
    private static bool $shutdownRegistered = false;

    /**
     * Whether the "async driver without exec()" warning was logged in this process.
     *
     * @var bool
     */
    private static bool $execWarningLogged = false;

    /**
     * Active queue driver ("file", "async" or "deferred").
     *
     * @var string
     */
    private string $driver;

    /**
     * Directory where pending job payloads are stored.
     *
     * @var string
     */
    private string $jobsPath;

    /**
     * Path to the PHP binary used by the async driver.
     *
     * @var string
     */
    private string $phpBinary;

    /**
     * Absolute path to the queue log file.
     *
     * @var string
     */
    private string $logFile;

    /**
     * Custom exec() availability check, or null to inspect the PHP runtime.
     *
     * @var (callable(): bool)|null
     */
    private $execAvailable;

    /**
     * @param string|null $driver Queue driver; defaults to config('queue.driver').
     * @param (callable(): bool)|null $execAvailable Overrides the exec() availability
     *        check of the "async" driver (useful for tests and unusual hosts).
     */
    public function __construct(?string $driver = null, ?callable $execAvailable = null)
    {
        $this->driver = $driver ?? (string) config('queue.driver', 'file');
        $this->jobsPath = rtrim((string) config('queue.path', storage_path('framework/jobs')), '/');
        $this->phpBinary = (string) config('queue.php_binary', PHP_BINARY);
        $this->logFile = (string) config('queue.log', storage_path('logs/queue.log'));
        $this->execAvailable = $execAvailable;

        if (!is_dir($this->jobsPath)) {
            mkdir($this->jobsPath, 0755, true);
        }
    }

    /**
     * Dispatch a job for background execution.
     *
     * The payload is written to a temporary file and renamed into place, so a
     * worker never reads a half-written job. This method never throws because
     * immediate execution is unavailable: the job then waits for a worker.
     *
     * @param JobInterface $job The job to execute later.
     * @return string The identifier (file path) of the queued job.
     */
    public function dispatch(JobInterface $job): string
    {
        $name = date('YmdHis') . '_' . uniqid('', true);
        $file = $this->jobsPath . '/' . $name . '.job';
        $temporary = $this->jobsPath . '/' . $name . '.tmp';

        if (file_put_contents($temporary, serialize($job), LOCK_EX) === false || !rename($temporary, $file)) {
            $this->log(sprintf('ERROR could not write job %s (%s)', basename($file), $job::class));
            return $file;
        }

        match ($this->driver) {
            'async'    => $this->runAsync($file),
            'deferred' => $this->defer($file),
            default    => null,
        };

        return $file;
    }

    /**
     * List the pending job payload files, oldest first.
     *
     * Jobs being processed (".running" files) are not pending.
     *
     * @return array<int, string> Absolute paths to pending job files.
     */
    public function pending(): array
    {
        $files = glob($this->jobsPath . '/*.job') ?: [];
        sort($files);

        return $files;
    }

    /**
     * Get the directory where job payloads are stored.
     *
     * @return string
     */
    public function jobsPath(): string
    {
        return $this->jobsPath;
    }

    /**
     * Get the absolute path of the queue log file.
     *
     * @return string
     */
    public function logPath(): string
    {
        return $this->logFile;
    }

    /**
     * Append a timestamped entry to the queue log.
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

        error_log(sprintf('[%s] %s%s', date('Y-m-d H:i:s'), $message, PHP_EOL), 3, $this->logFile);
    }

    /**
     * Report whether exec() can be called in this process.
     *
     * @return bool
     */
    public function execAvailable(): bool
    {
        if ($this->execAvailable !== null) {
            return (bool) ($this->execAvailable)();
        }

        if (!function_exists('exec')) {
            return false;
        }

        $disabled = array_map('trim', explode(',', strtolower((string) ini_get('disable_functions'))));

        return !in_array('exec', $disabled, true);
    }

    /**
     * Run a job through a detached process, or defer it when exec() is unavailable.
     *
     * @param string $file Absolute path to the serialized job file.
     * @return void
     */
    private function runAsync(string $file): void
    {
        if ($this->execAvailable()) {
            try {
                $this->runDetached($file);
                return;
            } catch (Throwable $exception) {
                $this->warnAsyncUnavailable('exec() failed: ' . $exception->getMessage());
            }
        } else {
            $this->warnAsyncUnavailable('exec() is disabled');
        }

        $this->defer($file);
    }

    /**
     * Log, once per process, that the async driver fell back to "deferred".
     *
     * @param string $reason Why the detached process could not be started.
     * @return void
     */
    private function warnAsyncUnavailable(string $reason): void
    {
        if (self::$execWarningLogged) {
            return;
        }

        self::$execWarningLogged = true;
        $this->log(sprintf('WARNING async queue driver unavailable (%s); using the "deferred" driver instead.', $reason));
    }

    /**
     * Schedule a job to run after the HTTP response has been sent.
     *
     * On the CLI this does nothing (the job stays queued, like the "file"
     * driver).
     *
     * @param string $file Absolute path to the serialized job file.
     * @return void
     */
    private function defer(string $file): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        self::$deferred[] = ['queue' => $this, 'file' => $file];

        if (!self::$shutdownRegistered) {
            self::$shutdownRegistered = true;
            register_shutdown_function(static function (): void {
                self::runDeferred();
            });
        }
    }

    /**
     * Shutdown function: send the response, then run the deferred jobs in order.
     *
     * Runs only when the SAPI can end the request early (PHP-FPM's
     * fastcgi_finish_request() or LiteSpeed's litespeed_finish_request());
     * otherwise running here would hold the response, so the jobs stay queued
     * for the worker. Failures are handled by the worker (failed/ and the log);
     * nothing is written to the response that was already sent.
     *
     * @return void
     */
    private static function runDeferred(): void
    {
        $jobs = self::$deferred;
        self::$deferred = [];

        $finish = match (true) {
            function_exists('fastcgi_finish_request')   => 'fastcgi_finish_request',
            function_exists('litespeed_finish_request') => 'litespeed_finish_request',
            default                                     => null,
        };

        if ($jobs === [] || $finish === null) {
            return;
        }

        while (ob_get_level() > 0 && @ob_end_flush()) {
            // Flush every output buffer into the response.
        }
        flush();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $finish();

        ignore_user_abort(true);
        ini_set('display_errors', '0');
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        foreach ($jobs as $entry) {
            // Discard anything the job prints: the response is already gone.
            $level = ob_get_level();
            ob_start(static fn (): string => '');
            try {
                (new Worker($entry['queue']))->runFile($entry['file']);
            } catch (Throwable $exception) {
                $entry['queue']->log(sprintf('FAILED %s: %s', basename($entry['file']), $exception->getMessage()));
            } finally {
                while (ob_get_level() > $level) {
                    ob_end_clean();
                }
            }
        }
    }

    /**
     * Spawn a detached PHP process to run a single queued job immediately.
     *
     * @param string $file Absolute path to the serialized job file.
     * @return void
     */
    private function runDetached(string $file): void
    {
        $fuse = escapeshellarg(base_path('fuse'));
        $argument = escapeshellarg($file);
        $php = escapeshellarg($this->phpBinary);

        $isWindows = stripos(PHP_OS, 'WIN') === 0;
        $command = $isWindows
            ? "start /B {$php} {$fuse} queue:run {$argument}"
            : "{$php} {$fuse} queue:run {$argument} > /dev/null 2>&1 &";

        exec($command);
    }
}
