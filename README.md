<div align="center">

# SwiftFusePHP

**An efficient and versatile PHP framework for seamless web development.**

Boost productivity with its modular architecture and smooth integration capabilities.

`PHP 8.4` · `PSR-4` · `MVC` · `PDO` · `version 0.9.9`

</div>

---

## Table of contents

- [What is SwiftFusePHP](#what-is-swiftfusephp)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Directory structure](#directory-structure)
- [A quick tour](#a-quick-tour)
  - [Routing](#routing)
  - [Controllers & views](#controllers--views)
  - [Models & database](#models--database)
  - [Protected storage & signed URLs](#protected-storage--signed-urls)
  - [Background jobs](#background-jobs)
  - [The `fuse` CLI](#the-fuse-cli)
  - [Extending the core](#extending-the-core)
- [Deployment](#deployment)
- [Documentation](#documentation)
- [Backward compatibility](#backward-compatibility)
- [License](#license)

---

## What is SwiftFusePHP

SwiftFusePHP is a small, original PHP framework built for **PHP 8.4**. It keeps the
classic **MVC + PDO** model you already know, but wraps it in a professional,
**100% English** core with a clean public API and a strong security posture by
default: only the `public/` folder is exposed to the web, everything else —
framework code, configuration, `.env` and your private files — lives outside the
web root.

It is designed around four principles:

- **Efficient & versatile** — stream gigabyte-sized private media without loading
  it into memory; a zero-infrastructure file queue; routing by convention or by
  explicit routes.
- **Modular architecture** — a service container, runtime macros, lifecycle hooks
  and abstract base classes let you compose and replace behavior.
- **Smooth integration** — a built-in PSR-4 autoloader that also picks up
  Composer's `vendor/autoload.php` when present, so any library drops in.
- **Productivity** — a `fuse` CLI with generators, expressive helpers and a
  centralized `.env` + `config/` setup.

## Features

| Area | What you get |
|------|--------------|
| **Autoloading** | PSR-4 with a built-in autoloader — no `composer install` required, fully Composer-compatible. |
| **Web root** | `public/` is the only exposed directory; core, `config/`, `.env` and `storage/` are private by construction. |
| **MVC + PDO** | Documented `Controller`, `Model` and PDO `Connection` with prepared statements and transactions. |
| **Routing** | Convention-based (`/{controller}/{method}/{params}`) plus optional explicit routes with `{placeholders}`. |
| **Protected files** | `StorageManager` streams private files only after authorization, via X-Sendfile / X-Accel-Redirect or chunked HTTP **Range** streaming. |
| **Signed URLs** | Short-lived, tamper-proof links (HMAC) to embed protected media in HTML. |
| **Background queue** | File-based job queue + `fuse queue:work` worker, with an optional fire-and-forget `async` driver. |
| **CLI** | `fuse` tool: `key:generate`, `queue:work`, `make:controller`, `make:job`. |
| **Extensibility** | Inheritance, a service container, runtime macros (`Extensible`) and hooks/events. |

## Requirements

- **PHP >= 8.4** with the `pdo`, `json`, `gd` and `intl` extensions.
- A web server (**Apache** or **Nginx**), ideally with the DocumentRoot pointing
  at `public/`. A fallback `.htaccess` is provided for shared hosting where the
  DocumentRoot cannot be changed.
- **MySQL/MariaDB** (PDO) for the database examples.

## Installation

```bash
# 1. Configure the environment
cp .env.example .env
php fuse key:generate

# 2. (Optional) import the example database
mysql -u root -p < DB/swiftfusephp_test.sql

# 3. Run it (built-in server, docroot = public/)
php -S localhost:8000 -t public
```

Then open <http://localhost:8000/>. For Apache/Nginx virtual hosts, shared
hosting and the production checklist, see **[docs/INSTALLATION.md](docs/INSTALLATION.md)**.

## Directory structure

```
SwiftFusePHP/
├── public/              # Web root — the ONLY exposed folder
│   ├── index.php        #   front controller
│   ├── .htaccess        #   pretty URLs + hardening
│   └── css/  js/        #   public assets
├── app/                 # Your code (namespace App\)
│   ├── Controllers/     #   controllers
│   ├── Models/          #   PDO models
│   ├── Services/        #   your services
│   ├── Jobs/            #   background jobs
│   ├── helpers.php      #   your global helpers
│   └── bootstrap.php    #   register macros / hooks / bindings
├── src/SwiftFuse/       # Framework core (namespace SwiftFuse\) — do not edit
├── config/              # Configuration read from .env
├── resources/views/     # Views
├── routes/web.php       # Optional explicit routes
├── storage/             # Private files, queued jobs, logs (outside web root)
├── bootstrap/           # Autoloader + application bootstrap
├── docs/                # Detailed documentation
├── .env / .env.example  # Environment
├── composer.json        # Optional Composer integration
└── fuse                 # Command-line tool
```

## A quick tour

### Routing

Routing works by convention out of the box — `/{controller}/{method}/{params}`
maps to `App\Controllers\{Controller}Controller`. You can also declare explicit
routes in `routes/web.php`:

```php
use App\Controllers\PeopleController;

$router->get('people', [PeopleController::class, 'index']);
$router->get('people/list', [PeopleController::class, 'list']);
$router->get('people/{id}', [PeopleController::class, 'show']); // {placeholder}
```

Full details: **[docs/ROUTING.md](docs/ROUTING.md)**.

### Controllers & views

```php
namespace App\Controllers;

use App\Models\Person;
use SwiftFuse\Http\Controller;

final class PeopleController extends Controller
{
    protected string $folder = 'people';

    public function index(string $view = 'index', string ...$params): void
    {
        $this->view('people.index', ['people' => $this->model('Person')->all()]);
    }

    public function list(): never
    {
        $this->json(['ok' => true, 'data' => $this->model('Person')->all()]);
    }
}
```

Full details: **[docs/CONTROLLERS.md](docs/CONTROLLERS.md)**.

### Models & database

PDO with prepared statements; the public API is intentionally small:

```php
namespace App\Models;

use SwiftFuse\Database\Model;

final class Person extends Model
{
    public function find(int $id): ?object
    {
        $this->connection->query('SELECT * FROM people WHERE peopleId = :id');
        $this->connection->bind(':id', $id);
        return $this->connection->getObject();
    }
}
```

Full details: **[docs/DATABASE.md](docs/DATABASE.md)**.

### Protected storage & signed URLs

Private files live under `storage/app/` (outside the web root) and are served
only after authorization, without loading the file into PHP memory:

```php
use SwiftFuse\Storage\SignedUrl;

// In a controller: generate a 5-minute link for an authorized user.
$url = SignedUrl::make('media/file', 'video/intro.mp4', 300);
// <video src="<?= $url ?>"> — validated and streamed with HTTP Range support.
```

Full details: **[docs/STORAGE.md](docs/STORAGE.md)**.

### Background jobs

```php
use SwiftFuse\Queue\QueueManager;

app(QueueManager::class)->dispatch(new App\Jobs\CompressImageJob($path));
// then: php fuse queue:work
```

Full details: **[docs/QUEUE.md](docs/QUEUE.md)**.

### The `fuse` CLI

```bash
php fuse list                  # show all commands
php fuse key:generate          # generate APP_KEY in .env
php fuse queue:work            # process pending background jobs
php fuse make:controller Foo   # scaffold App\Controllers\FooController
php fuse make:job SendEmail     # scaffold App\Jobs\SendEmail
```

Full details: **[docs/CLI.md](docs/CLI.md)**.

### Extending the core

You never edit `src/SwiftFuse/`. Extend from `app/` via inheritance, the service
container, runtime macros or hooks:

```php
// app/bootstrap.php
use SwiftFuse\Support\Hooks;

Hooks::on('controller.before', fn () => isset($_SESSION['user'])); // false -> 403
```

Full details: **[docs/EXTENDING.md](docs/EXTENDING.md)**.

## Deployment

- **Recommended:** point the web server's **DocumentRoot at `public/`**. Then the
  core, `config/`, `.env` and `storage/` are unreachable by construction.
- **Fallback:** if you cannot change the DocumentRoot (e.g. shared hosting that
  serves the project folder), the root **`.htaccess`** transparently forwards
  every request into `public/` and blocks direct access to the internals.

Both scenarios are covered step by step in **[docs/INSTALLATION.md](docs/INSTALLATION.md)**.

## Documentation

| Guide | Contents |
|-------|----------|
| [docs/INSTALLATION.md](docs/INSTALLATION.md) | Install, web server setup (Apache/Nginx), DocumentRoot, database. |
| [docs/CONFIGURATION.md](docs/CONFIGURATION.md) | `.env` and `config/*` reference, every key explained. |
| [docs/ROUTING.md](docs/ROUTING.md) | Convention routing, explicit routes, placeholders, the legacy bridge. |
| [docs/CONTROLLERS.md](docs/CONTROLLERS.md) | Controllers, views, requests, JSON responses, lifecycle hooks. |
| [docs/DATABASE.md](docs/DATABASE.md) | PDO `Connection` API, `Model`, prepared statements, transactions. |
| [docs/STORAGE.md](docs/STORAGE.md) | Private storage, streaming, signed URLs, uploads, X-Sendfile. |
| [docs/QUEUE.md](docs/QUEUE.md) | Background jobs, queue drivers, the worker. |
| [docs/CLI.md](docs/CLI.md) | The `fuse` command-line tool. |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Architecture standard: pattern, layers, where logic lives, data access, folder structure, what not to edit. |
| [docs/EXTENDING.md](docs/EXTENDING.md) | Extending the core without editing it. |
| [docs/STANDARD.md](docs/STANDARD.md) | Development standard: naming, format, error handling, logging, comments, dependencies, anti-patterns. |
| [docs/MIGRATION.md](docs/MIGRATION.md) | Migrate a legacy project (includes a migration prompt). |

## Backward compatibility

The previous Spanish core (`Controlador/`, `Modelo/`, `Libreria/`, `Config/…`,
`Archivos/`) is **deprecated** but still functional: legacy modules remain
reachable at `/<name>` through a compatibility bridge until they are migrated.
It will be removed in `1.0`. See [docs/MIGRATION.md](docs/MIGRATION.md).

## License

See [LICENSE](LICENSE).

<div align="center"><sub>SwiftFusePHP · version 0.9.9</sub></div>
