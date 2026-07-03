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
