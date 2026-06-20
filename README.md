# SwiftFusePHP

**An efficient and versatile PHP framework for seamless web development.**
Boost productivity with its modular architecture and smooth integration
capabilities. Built for **PHP 8.4**.

---

## Highlights

- **PSR-4 autoloading** with a built-in autoloader — no `composer install`
  required, yet fully Composer-compatible for third-party libraries.
- **`public/` web root**: the framework core, configuration, `.env` and
  `storage/` live outside the web root and cannot be requested directly.
- **MVC + PDO** with a clean, documented database layer.
- **Protected file delivery**: stream private media (e.g. video) to authorized
  users with X-Sendfile / X-Accel-Redirect and HTTP Range support — without
  loading files into PHP memory.
- **Signed URLs**: short-lived, tamper-proof links to protected resources.
- **Background queue**: file-based job queue plus a `fuse` CLI worker.
- **Modular by design**: a service container, runtime macros, lifecycle hooks and
  inheritance let you extend the core without editing it.

## Requirements

- PHP >= 8.4 with `pdo`, `json`, `gd` and `intl` extensions.
- A web server (Apache/Nginx) with the DocumentRoot pointing at `public/`.

## Quick start

```bash
# 1. Configure the environment
cp .env.example .env
php fuse key:generate

# 2. (Optional) import the example database
#    mysql < DB/framework_test.sql

# 3. Serve the public/ directory
php -S localhost:8000 -t public
```

Point your web server's DocumentRoot at `public/`. Then visit:

- `/` — landing page (MVC + PDO demo)
- `/media/video` — protected video streamed via a signed URL

## Project structure

```
public/        Web root (front controller + assets) — the ONLY exposed folder
app/           Your code: Controllers, Models, Services, Jobs (namespace App\)
src/SwiftFuse/ Framework core (namespace SwiftFuse\) — do not edit
config/        Configuration files read from .env
resources/views/  Views
routes/        Optional explicit routes
storage/       Private files, queued jobs and logs (outside the web root)
bootstrap/     Autoloader and application bootstrap
fuse           Command-line tool
```

## The `fuse` CLI

```bash
php fuse list                 # show all commands
php fuse key:generate         # generate APP_KEY
php fuse queue:work           # process pending background jobs
php fuse make:controller Foo  # scaffold App\Controllers\FooController
php fuse make:job SendEmail    # scaffold App\Jobs\SendEmail
```

## Documentation

- [docs/STANDARD.md](docs/STANDARD.md) — coding standard and conventions.
- [docs/EXTENDING.md](docs/EXTENDING.md) — how to extend the framework without
  editing the core.
- [docs/MIGRATION.md](docs/MIGRATION.md) — migrate a legacy project to the new
  structure (includes a ready-to-use migration prompt).

## Backward compatibility

The previous Spanish core (`Controlador/`, `Modelo/`, `Libreria/`, `Config/…`,
`Archivos/`) is **deprecated** but still functional through a compatibility
bridge: legacy modules stay reachable at `/<name>` via the new front controller
until you replace them. It will be removed in `1.0`. See
[docs/MIGRATION.md](docs/MIGRATION.md) to migrate at your own pace.

`SwiftFusePHP version: 0.9.9`
