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
 */
final class Worker
{
    /**
     * The queue manager providing pending jobs.
     *
     * @var QueueManager
     */
    private QueueManager $queue;

    /**
     * Absolute path to the worker log file.
     *
     * @var string
     */
    private string $logFile;

    /**
     * @param QueueManager $queue The queue manager to pull jobs from.
     */
    public function __construct(QueueManager $queue)
    {
        $this->queue = $queue;
        $this->logFile = storage_path('logs/queue.log');
    }

    /**
     * Process every currently pending job once.
     *
     * @return int The number of jobs processed (successful or failed).
     */
    public function work(): int
    {
        $processed = 0;
        foreach ($this->queue->pending() as $file) {
            $this->runFile($file);
            $processed++;
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
     * Execute a single serialized job file.
     *
     * @param string $file Absolute path to the serialized job.
     * @return bool True when the job ran successfully.
     */
    public function runFile(string $file): bool
    {
        if (!is_file($file)) {
            return false;
        }

        $job = @unserialize((string) file_get_contents($file));

        if (!$job instanceof JobInterface) {
            $this->fail($file, 'Payload is not a valid job.');
            return false;
        }

        try {
            $job->handle();
            unlink($file);
            $this->log(sprintf('Processed %s (%s)', basename($file), $job::class));
            return true;
        } catch (Throwable $exception) {
            $this->fail($file, $exception->getMessage());
            return false;
        }
    }

    /**
     * Move a failed job to the "failed" directory and log the reason.
     *
     * @param string $file Absolute path to the job file.
     * @param string $reason Human-readable failure reason.
     * @return void
     */
    private function fail(string $file, string $reason): void
    {
        $failedDir = $this->queue->jobsPath() . '/failed';
        if (!is_dir($failedDir)) {
            mkdir($failedDir, 0755, true);
        }

        if (is_file($file)) {
            rename($file, $failedDir . '/' . basename($file));
        }

        $this->log(sprintf('FAILED %s: %s', basename($file), $reason));
    }

    /**
     * Append a timestamped entry to the worker log.
     *
     * @param string $message The message to record.
     * @return void
     */
    private function log(string $message): void
    {
        $directory = dirname($this->logFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        error_log(sprintf('[%s] %s%s', date('Y-m-d H:i:s'), $message, PHP_EOL), 3, $this->logFile);
    }
}
