# Background Jobs & Queue

[← Back to README](../README.md)

Long-running work (sending email, processing images, calling slow APIs) should
not block the request. Dispatch a **job** to the queue and let a **worker**
process it out of band. Configuration lives in [`config/queue.php`](../config/queue.php).

## 1. Write a job

A job implements `SwiftFuse\Contracts\JobInterface`. Its constructor arguments are
serialized with it, so keep them simple (scalars/arrays):

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use SwiftFuse\Contracts\JobInterface;

final class CompressImageJob implements JobInterface
{
    public function __construct(private string $path) {}

    public function handle(): void
    {
        // ... compress $this->path ...
    }
}
```

Scaffold one with `php fuse make:job CompressImageJob`.

## 2. Dispatch it

```php
use SwiftFuse\Queue\QueueManager;

app(QueueManager::class)->dispatch(new App\Jobs\CompressImageJob($path));
```

`dispatch()` serializes the job into `storage/framework/jobs/` and returns its id
(the file path).

## 3. Process the queue

```bash
php fuse queue:work            # process all currently pending jobs, then exit
php fuse queue:work --daemon   # keep running, polling for new jobs
```

Successful jobs are deleted; failed ones are moved to
`storage/framework/jobs/failed/` and logged to `storage/logs/queue.log`.

### Running it continuously

- **Cron** (simplest, shared-hosting friendly): run the one-shot worker every
  minute.

  ```cron
  * * * * * cd /var/www/html/SwiftFusePHP && php fuse queue:work >> storage/logs/cron.log 2>&1
  ```

- **Daemon / supervisor:** keep `php fuse queue:work --daemon` alive with
  systemd or Supervisor for lower latency.

## Drivers

Set `QUEUE_DRIVER` in `.env`:

| Driver | Behavior |
|--------|----------|
| `file` *(default)* | Persist jobs to disk; process them with a worker. Needs no extra infrastructure. |
| `async` | Persist **and** immediately spawn a detached PHP process to run the job right away (fire-and-forget). Requires `exec()` to be enabled. |

The `async` driver is convenient on servers that allow `exec()`; the `file`
driver works everywhere, including restricted shared hosting.

## API summary

### `QueueManager`

| Method | Description |
|--------|-------------|
| `dispatch(JobInterface $job): string` | Queue a job; returns its id. |
| `pending(): array` | List pending job files (oldest first). |
| `jobsPath(): string` | Directory where jobs are stored. |

### `Worker`

| Method | Description |
|--------|-------------|
| `work(): int` | Process all currently pending jobs; returns the count. |
| `daemon(int $sleep = 3): void` | Poll and process forever. |
| `runFile(string $file): bool` | Process a single serialized job file. |

See the example job [`CompressImageJob`](../app/Jobs/CompressImageJob.php) and the
CLI reference in [CLI.md](CLI.md).
