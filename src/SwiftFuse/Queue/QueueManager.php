<?php

declare(strict_types=1);

namespace SwiftFuse\Queue;

use SwiftFuse\Contracts\JobInterface;

/**
 * Background job queue manager.
 *
 * Dispatches jobs for out-of-band execution. Two drivers are supported:
 *
 *   - "file" (default): the job is serialized to storage/framework/jobs and
 *     later processed by a worker (php fuse queue:work). Requires no extra
 *     infrastructure, so it works on shared hosting.
 *   - "async": the job is serialized and immediately handed to a detached PHP
 *     process for fire-and-forget execution.
 *
 * This is the structured evolution of the legacy BackgroundService.
 */
final class QueueManager
{
    /**
     * Active queue driver ("file" or "async").
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
     * @param string|null $driver Queue driver; defaults to config('storage.queue.driver').
     */
    public function __construct(?string $driver = null)
    {
        $this->driver = $driver ?? (string) config('queue.driver', 'file');
        $this->jobsPath = rtrim((string) config('queue.path', storage_path('framework/jobs')), '/');
        $this->phpBinary = (string) config('queue.php_binary', PHP_BINARY);

        if (!is_dir($this->jobsPath)) {
            mkdir($this->jobsPath, 0755, true);
        }
    }

    /**
     * Dispatch a job for background execution.
     *
     * @param JobInterface $job The job to execute later.
     * @return string The identifier (file path) of the queued job.
     */
    public function dispatch(JobInterface $job): string
    {
        $file = $this->jobsPath . '/' . date('YmdHis') . '_' . uniqid('', true) . '.job';
        file_put_contents($file, serialize($job), LOCK_EX);

        if ($this->driver === 'async') {
            $this->runDetached($file);
        }

        return $file;
    }

    /**
     * List the pending job payload files, oldest first.
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
