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

`dispatch()` serializes the job into `storage/framework/jobs/<id>.job` and
returns its id (the file path). The file is written under a temporary name and
renamed into place, so a worker never reads a half-written job.

## 3. Process the queue

```bash
php fuse queue:work            # process all currently pending jobs, then exit
php fuse queue:work --daemon   # keep running, polling for new jobs
```

Successful jobs are deleted; failed ones are moved to
`storage/framework/jobs/failed/` and logged to `storage/logs/queue.log`.

### Running it continuously

- **Cron** (simplest, shared-hosting friendly): run the one-shot worker every
  minute. Keep this cron with **every** driver: it is the safety net that runs
  whatever the `async` or `deferred` drivers could not.

  ```cron
  * * * * * cd /var/www/html/SwiftFusePHP && php fuse queue:work >> storage/logs/cron.log 2>&1
  ```

- **Daemon / supervisor:** keep `php fuse queue:work --daemon` alive with
  systemd or Supervisor for lower latency.

A job that takes longer than a minute is safe: the next cron run skips it while
it is still running (see below).

## 4. Reservation: every job runs once

Several processes can reach the same job: the cron of the next minute, a
daemon, the `async` process, or the `deferred` driver. Before running a job, the
worker **reserves** it, so exactly one process executes it:

1. It opens `<id>.job` and takes an exclusive, non-blocking `flock()`. If another
   process holds the lock, the job is skipped: no error, no log entry.
2. Holding the lock, it renames the file to `<id>.job.running`. Pending jobs are
   the `*.job` files only, so no other worker picks it up.
3. The lock is held while `handle()` runs. Afterwards the file is deleted, or
   moved to `failed/<id>.job` if the job threw.

`Worker::work()` returns (and `queue:work` prints) the jobs this worker ran,
not the ones it skipped. `Worker::runFile()` returns `false` for a job that is
already reserved, without moving it to `failed/`.

### Interrupted jobs are not retried

If the worker process dies in the middle of a job (fatal error, out of memory,
`kill`, a host timeout), the operating system releases its lock and a
`.running` file stays behind. At the start of every `work()` call, the worker
looks at the `.running` files: when it can take a lock without waiting, the
owner is dead. The job is moved to `failed/` with the reason
`Worker interrupted`, and a line is written to `queue.log`. A `.running` file
whose owner is still alive is left alone.

Interrupted jobs are **never retried automatically**, because a job may have
done part of its work before dying, and running it again could duplicate data
(for example, half of a 10,000-row import). Inspect it, clean up if needed, and
move `failed/<id>.job` back into `storage/framework/jobs/` to run it again.
Jobs that are safe to repeat can be requeued the same way without inspection.

### Windows

Linux is the main platform. On Windows an open file cannot be renamed or
deleted, so the worker releases the lock, claims the job by renaming it, and
locks it again, and it deletes or moves the file after releasing the lock. No
job is lost, but exclusion is best-effort: in those short windows another
worker can move a job that is about to start, or one that has just finished,
to `failed/` as interrupted. Check `queue.log` (`Processed ...` lines) before
retrying a job from `failed/` on Windows.

## Drivers

Set `QUEUE_DRIVER` in `.env`. Every driver writes the job to disk first, so the
worker always processes what was not run earlier.

| Driver | Behavior |
|--------|----------|
| `file` *(default)* | Persist jobs to disk; a worker processes them. Needs no extra infrastructure. |
| `async` | Persist **and** immediately spawn a detached PHP process (`php fuse queue:run`) to run the job right away. Needs `exec()`; without it, falls back to `deferred`. |
| `deferred` | Persist, and run the job in the same PHP process **after the HTTP response has been sent**. Needs PHP-FPM or LiteSpeed; elsewhere it behaves like `file`. |

### `deferred`: shared hosting without `exec()`

On shared hosting (cPanel), `exec()` is usually disabled and PHP runs under
PHP-FPM or LiteSpeed. There, `deferred` starts a job right after the request
instead of waiting up to a minute for the cron:

1. `dispatch()` writes the job like `file` and registers a shutdown function.
2. When the request ends, even through `exit` (as `json()` does), the shutdown
   function flushes the output buffers, closes the session, and calls
   `fastcgi_finish_request()` or `litespeed_finish_request()`: the client has
   its response.
3. It then calls `ignore_user_abort(true)` and `set_time_limit(0)`, and runs the
   jobs dispatched during the request in dispatch order, through the same
   reservation as the worker.

A job that fails there is handled like any other failure (`failed/` and
`queue.log`); nothing reaches the response, which has already been sent, and
anything the job prints is discarded. When neither finish function exists
(Apache `mod_php`, the built-in server), nothing runs during the request,
because running a long job would hold the response; the job waits for the cron.
On the CLI, `deferred` behaves like `file`.

**Keep the every-minute cron with `deferred`.** It runs the jobs of requests
served without a finish function, and it is the only thing that notices a job
interrupted by a host limit: the PHP process still counts against the host's
limits (memory, CPU time, `max_execution_time` enforced by the host, the
FPM `request_terminate_timeout`), and the PHP-FPM worker stays busy until the
job ends. For jobs of several minutes, check these limits with your host.

### `async` without `exec()`

Before starting the detached process, the `async` driver checks that `exec()`
exists and is not listed in `disable_functions`. When it is unavailable, or
calling it fails, the job is handled by the `deferred` driver instead, and one
warning per PHP process is written to `storage/logs/queue.log`:

```
[2026-09-28 10:00:00] WARNING async queue driver unavailable (exec() is disabled); using the "deferred" driver instead.
```

`dispatch()` never throws because of this. On shared hosting, set
`QUEUE_DRIVER=deferred` directly to avoid the warning.

## API summary

### `QueueManager`

| Method | Description |
|--------|-------------|
| `dispatch(JobInterface $job): string` | Queue a job; returns its id. |
| `pending(): array` | List pending job files (oldest first). |
| `jobsPath(): string` | Directory where jobs are stored. |
| `logPath(): string` | Path of the queue log (`queue.log` setting). |
| `log(string $message): void` | Append a timestamped line to the queue log. |
| `execAvailable(): bool` | Whether the `async` driver can call `exec()`. The check can be replaced with the constructor's second argument. |

### `Worker`

| Method | Description |
|--------|-------------|
| `work(): int` | Fail interrupted jobs, then reserve and process all pending jobs; returns how many this worker ran. |
| `daemon(int $sleep = 3): void` | Poll and process forever. |
| `runFile(string $file): bool` | Reserve and process a single job file; `false` when it failed, is missing or is reserved by another process. |

See the example job [`CompressImageJob`](../app/Jobs/CompressImageJob.php) and the
CLI reference in [CLI.md](CLI.md).
