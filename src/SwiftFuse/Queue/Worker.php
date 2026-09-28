<?php

declare(strict_types=1);

namespace SwiftFuse\Queue;

use SwiftFuse\Contracts\JobInterface;
use Throwable;

/**
 * Background job worker.
 *
 * Reads serialized jobs from the queue directory, executes them, and removes
 * successful payloads. Failed payloads are moved to a "failed" sub-directory and
 * logged. Designed to be driven by the CLI (php fuse queue:work) or a cron job.
 *
 * Each job is reserved before it runs, so only one process ever executes it,
 * however many workers, crons, daemons or queue:run calls overlap:
 *
 *   1. The worker opens the ".job" file and takes an exclusive, non-blocking
 *      flock(). When another process holds it, the job is skipped.
 *   2. Holding the lock, it renames the file to ".running", which takes it out
 *      of QueueManager::pending().
 *   3. The lock is held while handle() runs. The file is then deleted, or moved
 *      to failed/ when the job throws.
 *
 * A ".running" file whose lock is free belongs to a process that died (fatal
 * error, out of memory, kill). The next work() call moves it to failed/ with the
 * reason "Worker interrupted". It is never retried automatically, because jobs
 * may not be idempotent.
 */
final class Worker
{
    /**
     * Reason recorded for jobs whose worker died while running them.
     *
     * @var string
     */
    public const INTERRUPTED = 'Worker interrupted';

    /**
     * The queue manager providing pending jobs.
     *
     * @var QueueManager
     */
    private QueueManager $queue;

    /**
     * @param QueueManager $queue The queue manager to pull jobs from.
     */
    public function __construct(QueueManager $queue)
    {
        $this->queue = $queue;
    }

    /**
     * Process every currently pending job once.
     *
     * Interrupted jobs left by dead workers are moved to failed/ first. Jobs
     * reserved by another process are skipped and not counted.
     *
     * @return int The number of jobs processed (successful or failed).
     */
    public function work(): int
    {
        $this->recoverInterrupted();

        $processed = 0;
        foreach ($this->queue->pending() as $file) {
            if ($this->process($file) !== null) {
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * Continuously poll the queue, processing jobs as they arrive.
     *
     * @param int $sleepSeconds Seconds to wait between empty polls.
     * @return void
     */
    public function daemon(int $sleepSeconds = 3): void
    {
        while (true) {
            if ($this->work() === 0) {
                sleep(max(1, $sleepSeconds));
            }
        }
    }

    /**
     * Reserve and execute a single serialized job file.
     *
     * Returns false without touching the file when the job is missing, already
     * reserved by another process, or a ".running" file.
     *
     * @param string $file Absolute path to the serialized job.
     * @return bool True when the job ran successfully.
     */
    public function runFile(string $file): bool
    {
        return $this->process($file) === true;
    }

    /**
     * Reserve and execute a job file.
     *
     * @param string $file Absolute path to the serialized job.
     * @return bool|null True on success, false on failure, null when the job was not reserved.
     */
    private function process(string $file): ?bool
    {
        if (str_ends_with($file, '.running')) {
            return null;
        }

        $reservation = $this->reserve($file);
        if ($reservation === null) {
            return null;
        }

        [$handle, $running] = $reservation;

        try {
            $job = @unserialize((string) stream_get_contents($handle, -1, 0));
        } catch (Throwable) {
            $job = null;
        }

        if (!$job instanceof JobInterface) {
            $this->fail($handle, $running, 'Payload is not a valid job.');
            return false;
        }

        try {
            $job->handle();
        } catch (Throwable $exception) {
            $this->fail($handle, $running, $exception->getMessage());
            return false;
        }

        $this->finish($handle, $running, null);
        $this->log(sprintf('Processed %s (%s)', basename($file), $job::class));

        return true;
    }

    /**
     * Take exclusive ownership of a pending job.
     *
     * On Linux the file is renamed while locked, so the lock never lapses. Where
     * an open file cannot be renamed (Windows), the lock is dropped and the job
     * is claimed by the rename itself, then locked again; see docs/QUEUE.md.
     *
     * @param string $file Absolute path to the ".job" file.
     * @return array{0: resource, 1: string}|null The locked handle and the ".running" path,
     *         or null when the job is gone or owned by another process.
     */
    private function reserve(string $file): ?array
    {
        $handle = $this->lock($file);
        if ($handle === null) {
            return null;
        }

        $running = $this->runningPath($file);
        if (@rename($file, $running)) {
            return [$handle, $running];
        }

        $this->unlock($handle);
        if (!@rename($file, $running)) {
            if (is_file($file)) {
                $this->log(sprintf('WARNING could not reserve %s; it stays queued.', basename($file)));
            }
            return null;
        }

        $handle = $this->lock($running);

        return $handle === null ? null : [$handle, $running];
    }

    /**
     * Move the ".running" files of dead workers to failed/.
     *
     * A ".running" file whose lock can be taken without blocking has no living
     * owner. It is failed, not retried, since the job may have partly run.
     *
     * @return void
     */
    private function recoverInterrupted(): void
    {
        foreach (glob($this->queue->jobsPath() . '/*.running') ?: [] as $running) {
            $handle = $this->lock($running);
            if ($handle !== null) {
                $this->fail($handle, $running, self::INTERRUPTED);
            }
        }
    }

    /**
     * Open a file and take an exclusive, non-blocking lock on it.
     *
     * The lock only counts when the path still names the locked file: another
     * worker may have renamed or deleted it between fopen() and flock().
     *
     * @param string $path Absolute path to lock.
     * @return resource|null The locked handle, or null when the lock is unavailable.
     */
    private function lock(string $path)
    {
        if (!is_file($path)) {
            return null;
        }

        $handle = @fopen($path, 'r+b');
        if ($handle === false) {
            return null;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return null;
        }

        clearstatcache(true, $path);
        $current = @stat($path);
        $locked = fstat($handle);

        if ($current === false || $locked === false
            || $current['ino'] !== $locked['ino'] || $current['dev'] !== $locked['dev']
        ) {
            $this->unlock($handle);
            return null;
        }

        return $handle;
    }

    /**
     * Release a lock and close its handle.
     *
     * @param resource $handle The locked handle.
     * @return void
     */
    private function unlock($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /**
     * Remove a reserved job, or move it to failed/, then release it.
     *
     * The file is removed while still locked. Where an open file cannot be
     * removed or renamed (Windows), it is retried after the lock is released.
     *
     * @param resource $handle The locked handle of the job.
     * @param string $running Absolute path to the ".running" file.
     * @param string|null $failedPath Destination in failed/, or null to delete the job.
     * @return void
     */
    private function finish($handle, string $running, ?string $failedPath): void
    {
        $move = static fn (): bool => $failedPath === null ? @unlink($running) : @rename($running, $failedPath);

        $done = $move();
        $this->unlock($handle);

        if (!$done && is_file($running) && !$move()) {
            $this->log(sprintf('WARNING could not remove %s', basename($running)));
        }
    }

    /**
     * Move a reserved job to the "failed" directory and log the reason.
     *
     * @param resource $handle The locked handle of the job.
     * @param string $running Absolute path to the ".running" file.
     * @param string $reason Human-readable failure reason.
     * @return void
     */
    private function fail($handle, string $running, string $reason): void
    {
        $failedDir = $this->queue->jobsPath() . '/failed';
        if (!is_dir($failedDir)) {
            mkdir($failedDir, 0755, true);
        }

        $name = $this->jobName($running);
        $this->finish($handle, $running, $failedDir . '/' . $name);
        $this->log(sprintf('FAILED %s: %s', $name, $reason));
    }

    /**
     * Get the ".running" path of a job file ("x.job" becomes "x.job.running").
     *
     * @param string $file Absolute path to the ".job" file.
     * @return string
     */
    private function runningPath(string $file): string
    {
        return $file . '.running';
    }

    /**
     * Get the original file name of a reserved job ("x.job.running" gives "x.job").
     *
     * @param string $running Absolute path to the ".running" file.
     * @return string
     */
    private function jobName(string $running): string
    {
        return basename($running, '.running');
    }

    /**
     * Append a timestamped entry to the worker log.
     *
     * @param string $message The message to record.
     * @return void
     */
    private function log(string $message): void
    {
        $this->queue->log($message);
    }
}
