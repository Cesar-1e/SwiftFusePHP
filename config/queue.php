<?php

/**
 * Background queue configuration.
 *
 * Consumed by SwiftFuse\Queue\QueueManager and Worker.
 */

declare(strict_types=1);

return [
    // "file": persist jobs to disk and process them with `php fuse queue:work`.
    // "async": additionally spawn a detached process to run each job immediately.
    'driver' => env('QUEUE_DRIVER', 'file'),

    // Directory where serialized job payloads are stored.
    'path' => storage_path('framework/jobs'),

    // PHP binary used by the "async" driver to spawn worker processes.
    'php_binary' => env('QUEUE_PHP_BINARY', PHP_BINARY),
];
