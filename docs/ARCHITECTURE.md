# SwiftFusePHP Architecture Standard

[← Back to README](../README.md)

> SwiftFusePHP: an efficient and versatile PHP framework for seamless web
> development. Boost productivity with its modular architecture and smooth
> integration capabilities.

This document defines the architecture every application built on SwiftFusePHP
must follow: the pattern, the layers and their responsibilities, where business
logic and data access live, the folder structure, and which parts are framework
internals you must not edit.

---

## 1. Pattern

The architecture is **MVC over a layered design**, wired by a lightweight
**service container**.

- **MVC** organizes the request/response flow: a **Controller** receives the
  request, a **Model** owns the data, and a **View** renders the result.
- A **thin service layer** holds business logic that spans models or talks to
  external systems, keeping controllers and models focused.
- **Dependency inversion** is provided by the container: infrastructure
  capabilities (storage, queue) sit **behind contracts** (`SwiftFuse\Contracts\*`)
  and are resolved from the container, so any implementation can be swapped
  without touching the code that uses it. This ports-and-adapters influence is
  applied only at the infrastructure boundary — there is no hexagonal ceremony to
  pay for.

### Request lifecycle

```
HTTP request
   │
   ▼
public/index.php                  ← front controller (the only exposed PHP file)
   │  bootstrap/app.php → autoloader, .env, config, error handler, container
   ▼
Application                       ← kernel + service container
   │
   ▼
Router  ──resolves──▶ Controller (App\Controllers)        [Presentation / HTTP]
                          │
                          ├──▶ Service (App\Services)      [Application / business logic]
                          │         │
                          └─────────┴──▶ Model (App\Models) ──▶ Connection (PDO)   [Data access]
                          │
                          ▼
                       View (resources/views)  ──▶ HTTP response
```

Cross-cutting capabilities (Storage, Queue, Hooks, Support) are resolved from the
container and used by any layer that needs them.

## 2. Layers and responsibilities

| Layer | Lives in | Responsibility | Must NOT |
|-------|----------|----------------|----------|
| **Presentation / HTTP** | `Router`, `Controller`, `Request`, `resources/views/` | Parse the request, invoke the right service/model, return a view or JSON. | Contain business rules or run SQL. |
| **Application (business logic)** | `app/Services/`, `app/Models/` | Enforce business rules, orchestrate operations, coordinate models and infrastructure. | Read superglobals or emit HTTP/HTML. |
| **Data access** | `app/Models/` → `SwiftFuse\Database\Connection` (PDO) | Read/write persistent data with prepared statements. | Hold presentation logic. |
| **Infrastructure (core)** | `src/SwiftFuse/` | Routing, container, error handling, storage, queue, console, support utilities. | Depend on application code. |
| **Cross-cutting** | container, `SwiftFuse\Support\Hooks`, `Support\*` | DI, events/hooks, configuration, formatting, validation. | Become a dumping ground for unrelated code. |

**Dependency direction:** application code (`app/`) depends on the core
(`src/SwiftFuse/`); the **core never depends on `app/`**. Higher layers depend on
lower ones, never the reverse.

## 3. Where business logic lives

Business logic lives in the **Application layer** — **Models** and **Services** —
never in controllers or views.

- **Models** (`app/Models/`) own a domain concept and its persistence: queries,
  domain-specific validation, and the data shape it returns.
- **Services** (`app/Services/`) hold logic that does not belong to a single
  model: orchestrating several models, transactions across them, calling external
  APIs, or reusable rules shared by multiple controllers.
- **Controllers** stay **thin**: read input, delegate to a model/service, choose a
  response. A controller method should read like a short script.
- **Views** are presentation only: they render data handed to them and escape
  output. No queries, no decisions beyond simple display conditionals.

```php
// Thin controller — delegates, then responds.
public function store(): never
{
    $id = app(InvoiceService::class)->createFromRequest($_POST);
    $this->json(['ok' => true, 'id' => $id], 201);
}
```

Rule of thumb: if a controller grows branches and rules, push them into a service;
if a model starts orchestrating other models, introduce a service.

## 4. Data access

- **PDO only**, through `SwiftFuse\Database\Connection`. There is no ORM; SQL is
  explicit and intentional.
- Models extend `SwiftFuse\Database\Model`, which owns a `Connection`. Each model
  **encapsulates its own queries** — query strings never leak into controllers or
  views.
- **Always use prepared statements** (`query()` + `bind()`); never concatenate
  input into SQL.
- Use **transactions** for multi-statement operations
  (`beginTransaction()`/`commit()`/`rollBack()`).
- For larger domains, you may split persistence into repository-style classes
  under `app/Services/` (or `app/Repositories/`) that wrap a model/connection —
  the rest of the app then depends on that boundary, not on raw SQL.

```php
final class Invoice extends Model
{
    public function find(int $id): ?object
    {
        $this->connection->query('SELECT * FROM invoices WHERE id = :id');
        $this->connection->bind(':id', $id);
        return $this->connection->getObject();
    }
}
```

See [DATABASE.md](DATABASE.md) for the full `Connection`/`Model` reference.

## 5. Folder structure

```
SwiftFusePHP/
├── public/              # Web root — the ONLY exposed folder
│   ├── index.php        #   front controller
│   ├── .htaccess        #   pretty URLs + hardening
│   └── css/  js/        #   public assets (lowercase)
├── app/                 # YOUR application (namespace App\)
│   ├── Controllers/     #   presentation: thin HTTP handlers
│   ├── Models/          #   data access + domain data (PDO)
│   ├── Services/        #   business logic / orchestration
│   ├── Jobs/            #   background jobs
│   ├── helpers.php      #   your global helpers
│   └── bootstrap.php    #   register macros / hooks / bindings
├── src/SwiftFuse/       # FRAMEWORK CORE (namespace SwiftFuse\)
│   ├── Foundation/      #   Application (kernel), Container, ErrorHandler
│   ├── Routing/         #   Router
│   ├── Http/            #   Controller, Request, HttpException
│   ├── Database/        #   Connection (PDO), Model
│   ├── Storage/         #   StorageManager, SignedUrl
│   ├── Queue/           #   QueueManager, Worker
│   ├── Console/         #   CLI kernel
│   ├── Contracts/       #   interfaces (ports)
│   └── Support/         #   Env, Config, View, Hooks, Extensible, Str, …
├── config/              # configuration read from .env
├── resources/views/     # views (presentation)
├── routes/web.php       # optional explicit routes
├── storage/             # private files, queued jobs, logs (outside web root)
├── bootstrap/           # autoloader + application bootstrap
├── docs/                # documentation
└── fuse                 # command-line tool
```

| Folder | Layer | You edit it? |
|--------|-------|--------------|
| `app/` | Application + presentation handlers | **Yes** — this is where you build. |
| `resources/views/` | Presentation | **Yes** |
| `routes/`, `config/` | Wiring / configuration | **Yes** |
| `public/css`, `public/js` | Public assets | **Yes** |
| `storage/` | Runtime data | Written at runtime; don’t commit its contents. |
| `src/SwiftFuse/` | Infrastructure (core) | **No** — extend instead. |
| `bootstrap/`, `public/index.php`, `fuse` | Framework wiring/entry points | **No** |

## 6. What is generated — and must NOT be touched

A clear boundary separates **framework internals** (do not edit) from **your code**
(edit freely).

**Framework internals — never edit:**

- `src/SwiftFuse/**` — the core. Customize behavior by **extending from `app/`**:
  inheritance, container bindings, runtime macros, or hooks. See
  [EXTENDING.md](EXTENDING.md).
- `bootstrap/autoload.php`, `bootstrap/app.php` — the autoloader and the wiring.
  Configure through `config/` and `app/bootstrap.php`, not by editing these.
- `public/index.php` — the front controller.
- `fuse` — the CLI entry point.
- `vendor/**` — Composer-managed (when present); regenerated by `composer install`.

**Generated scaffolds — yours once created:**

Commands like `php fuse make:controller` and `php fuse make:job` generate a
starting file under `app/`. The generated file is a **starting point you own** —
edit it freely; it is not regenerated.

**The single rule:**

> Everything under `src/SwiftFuse/` is the framework — treat it as read-only and
> change behavior from `app/`. Everything under `app/`, `config/`,
> `resources/views/` and `routes/` is yours.

This separation is what lets the framework be updated without touching your
application, and your application to grow without forking the framework.

## Related

- [STANDARD.md](STANDARD.md) — naming, format, error handling, logging, comments,
  dependencies, anti-patterns.
- [EXTENDING.md](EXTENDING.md) — the four ways to extend the core without editing it.
