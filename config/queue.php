<?php

/**
 * Background queue configuration.
 *
 * Consumed by SwiftFuse\Queue\QueueManager and Worker.
 */

declare(strict_types=1);

return [
    // Every driver persists jobs to disk, so a worker (`php fuse queue:work`,
    // e.g. from a cron every minute) always processes what was not run earlier.
    //   "file":     only persist; the worker runs the job.
    //   "async":    also spawn a detached process to run the job immediately.
    //               Needs exec(); without it, falls back to "deferred" and logs
    //               a warning.
    //   "deferred": run the job in the same PHP process after the HTTP response
    //               is sent (PHP-FPM or LiteSpeed only; elsewhere and on the CLI
    //               it behaves like "file"). For shared hosting without exec().
    'driver' => env('QUEUE_DRIVER', 'file'),

    // Directory where serialized job payloads are stored.
    'path' => storage_path('framework/jobs'),

    // PHP binary used by the "async" driver to spawn worker processes.
    'php_binary' => env('QUEUE_PHP_BINARY', PHP_BINARY),

    // Log of processed, failed and interrupted jobs and driver warnings.
    'log' => storage_path('logs/queue.log'),
];
