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
and exits (ideal for cron); with `--daemon` it runs continuously.

```bash
php fuse queue:work
php fuse queue:work --daemon
```

See [QUEUE.md](QUEUE.md).

### `queue:run <job-file>`

Process a single serialized job file. Used internally by the `async` queue
driver; you rarely call it directly.

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

## Adding your own commands

Commands are dispatched by `SwiftFuse\Console\Kernel`. To keep the core untouched
you can drive custom tasks from a job or a small script that bootstraps the
framework:

```php
#!/usr/bin/env php
<?php
require __DIR__ . '/bootstrap/app.php';   // autoloader, env, config

// your task here, using app(...), config(...), models, etc.
```

For framework-level command additions, follow the
[coding standard](STANDARD.md) and the
[extension guidelines](EXTENDING.md).

## Exit codes

`fuse` returns `0` on success and a non-zero code on failure, so it composes well
with cron and CI.
