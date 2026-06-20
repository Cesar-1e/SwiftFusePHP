<?php

declare(strict_types=1);

namespace SwiftFuse\Contracts;

/**
 * Contract for background jobs.
 *
 * A job is a self-contained unit of work dispatched to the queue and executed
 * later by a worker. Implementations must be serializable (their constructor
 * arguments are stored), since the file queue driver persists them to disk.
 */
interface JobInterface
{
    /**
     * Execute the job's work.
     *
     * @return void
     */
    public function handle(): void;
}
