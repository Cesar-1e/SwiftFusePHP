# The `fuse` CLI

[← Back to README](../README.md)

`fuse` is SwiftFusePHP's command-line tool. It boots the framework (autoloader,
environment, configuration) and dispatches a command. Run it from the project
root:

```bash
php fuse <command> [arguments]
php fuse list           # show every command
```

## Commands

### `key:generate`

Generate a random `APP_KEY` and write it into `.env`. Required for signed URLs.

```bash
php fuse key:generate
```

### `queue:work [--daemon]`

Process pending background jobs. Without flags it processes the current backlog
and exits (ideal for cron); with `--daemon` it runs continuously. Jobs that
another process is running are skipped and not counted.

```bash
php fuse queue:work
php fuse queue:work --daemon
```

See [QUEUE.md](QUEUE.md).

### `queue:run <job-file>`

Process a single serialized job file. Used internally by the `async` queue
driver; you rarely call it directly.

The file must be directly inside the queue directory (`queue.path`, by default
`storage/framework/jobs/`); the path is resolved with `realpath()`, so `..` and
symlinks cannot escape it. Other paths, including `failed/`, are refused with an
error message and exit code 1:

```text
$ php fuse queue:run /tmp/payload.job
Refusing to run /tmp/payload.job: job files must be inside /var/www/app/storage/framework/jobs.
```

The job is reserved like in `queue:work`, so it never runs twice. The exit code
is 1 when the file is missing, the job failed, or another process already
reserved it.

### `make:controller <Name>`

Scaffold a controller in `app/Controllers/`. The `Controller` suffix is added if
missing, and the view `$folder` is inferred from the name.

```bash
php fuse make:controller Invoice      # -> app/Controllers/InvoiceController.php
```

### `make:job <Name>`

Scaffold a background job in `app/Jobs/` implementing `JobInterface`.

```bash
php fuse make:job SendWelcomeEmail    # -> app/Jobs/SendWelcomeEmail.php
```

### `make:command <Name>`

Scaffold an application command in `app/Console/` implementing
`CommandInterface`, with a suggested name derived from the class (`GenerateReport` →
`app:generate-report`). The command is not available until you register it in
`config/console.php` — the output reminds you. See
[Application commands](#application-commands).

```bash
php fuse make:command GenerateMonthlyReport   # -> app/Console/GenerateMonthlyReport.php
```

### `assets:publish [--force] [--link]`

Publish third-party assets into the public web root — **without npm or a
bundler**. It reads the `source => destination` map in
[`config/assets.php`](../config/assets.php), where sources are relative to the
project root and destinations are relative to `public/`, then copies each entry
(creating directories as needed) and reports what was published, skipped or
missing.

```bash
php fuse assets:publish            # copy configured assets into public/
php fuse assets:publish --force    # overwrite existing destinations
php fuse assets:publish --link     # symlink instead of copy (falls back to copy)
```

| Flag | Effect |
|------|--------|
| *(none)* | Copy each asset; skip destinations that already exist. |
| `--force` | Overwrite destinations that already exist. |
| `--link` | Create a symlink instead of copying. If the OS/host forbids symlinks, it transparently falls back to a copy (reported as `copied*`). |

The exit code is non-zero when any source is **missing** or a copy **fails**, so
it composes with CI.

**Configuring assets** — the map is source-agnostic (npm, Composer, or a manual
download):

```php
// config/assets.php
return [
    'node_modules/htmx.org/dist/htmx.min.js' => 'assets/htmx/htmx.min.js',
    'vendor/twbs/bootstrap/dist/css/bootstrap.min.css' => 'assets/bootstrap/bootstrap.min.css',
];
```

Reference a published asset from a view with `base_url()`:

```php
<script src="<?= base_url('assets/htmx/htmx.min.js') ?>"></script>
```

**Recommendations:**

- Publish under **`public/assets/`** (as above). Do **not** use `public/vendor/`:
  the project's root `.htaccess` blocks a top-level `/vendor` path.
- **Git-ignore the destination** — published files are generated. `/public/assets/`
  is already in `.gitignore`; run `assets:publish` after a clean checkout (and add
  it to your deploy step).

## Application commands

Your application can add its own commands to `php fuse` without touching the
framework: write a class that implements `SwiftFuse\Contracts\CommandInterface`
and list it in `config/console.php`.

### 1. Write the command

Generate it with `php fuse make:command <Name>` or write it by hand anywhere the
autoloader reaches (`app/`, or another PSR-4 root such as `Extensions\`):

```php
// app/Console/GenerateMonthlyReport.php
namespace App\Console;

use SwiftFuse\Contracts\CommandInterface;

final class GenerateMonthlyReport implements CommandInterface
{
    public function name(): string
    {
        return 'reports:monthly';
    }

    public function description(): string
    {
        return 'Generate the report of the previous month';
    }

    public function handle(array $arguments): int
    {
        $dryRun = in_array('--dry-run', $arguments, true);

        // ... use models, app(...), config(...) as anywhere else;
        //     skip the writes when $dryRun is true.
        fwrite(STDOUT, "Report generated." . PHP_EOL);

        return 0;   // the exit code of `php fuse reports:monthly`
    }
}
```

| Method | Purpose |
|--------|---------|
| `name()` | What you type after `php fuse`. Lowercase segments separated by `:`, made of letters, digits and `-`, starting with a letter (`/^[a-z][a-z0-9-]*(:[a-z0-9-]+)*$/`). |
| `description()` | The line shown by `php fuse list`. |
| `handle(array $arguments)` | Receives the arguments that follow the name (`php fuse reports:monthly --dry-run` → `['--dry-run']`) and returns the exit code. |

The kernel creates the command with `new $class()`, so the constructor must not
require arguments; resolve services inside the command with `app(...)`.

### 2. Register it

```php
// config/console.php
return [
    'commands' => [
        App\Console\GenerateMonthlyReport::class,
    ],
];
```

`php fuse list` now shows it under **Application commands** (in alphabetical
order), and `php fuse reports:monthly` runs it:

```text
$ php fuse list
...
Application commands:
  reports:monthly           Generate the report of the previous month
```

### Rules and errors

- **Built-in commands take precedence.** `key:generate`, `queue:work`,
  `queue:run`, `make:controller`, `make:job`, `make:command`, `assets:publish`
  and `list` cannot be replaced, and running them never loads the registry.
- Running an application command, or `list`, validates **every** registered
  class. Any problem is printed on STDERR and the exit code is `1`:

  | Problem | Message |
  |---------|---------|
  | Class not found | `Console command class App\Console\X does not exist.` |
  | Does not implement the interface | `Console command class App\Console\X must implement SwiftFuse\Contracts\CommandInterface.` |
  | Invalid name | `Console command App\Console\X has an invalid name "Monthly Report"; ...` |
  | Name of a built-in command | `Console command App\Console\X cannot use the name "queue:work": it is a built-in command.` |
  | Two classes with the same name | `Console command name "reports:monthly" is registered twice: ... and ....` |
  | Constructor or `name()` throws | `Console command class App\Console\X could not be created: ...` |

- An uncaught exception in `handle()` prints
  `Command reports:monthly failed: <message>` on STDERR and exits with `1`.
- Without `config/console.php` (or with an empty `commands` list) the CLI behaves
  exactly as in 0.11.0, apart from the new `make:command` line in `list`.

### Running a command periodically (cron)

The framework has no scheduler: periodicity belongs to cron. For example, to
generate the report at 02:00 on the first day of every month:

```cron
0 2 1 * * cd /path/to/project && php fuse reports:monthly >> storage/logs/cron.log 2>&1
```

`cd` into the project root first, so `fuse` finds `.env` and `config/`. Use the
full path of the PHP binary (`which php`) if cron's `PATH` does not include it.
Since STDERR is redirected to the log too, the validation and failure messages
above end up in `storage/logs/cron.log`.

For one-off scripts that do not deserve a command, you can still bootstrap the
framework yourself:

```php
#!/usr/bin/env php
<?php
require __DIR__ . '/bootstrap/app.php';   // autoloader, env, config

// your task here, using app(...), config(...), models, etc.
```

## Exit codes

`fuse` returns `0` on success and a non-zero code on failure, so it composes well
with cron and CI.
