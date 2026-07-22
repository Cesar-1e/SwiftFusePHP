# SwiftFusePHP Development Standard

[← Back to README](../README.md)

> SwiftFusePHP: an efficient and versatile PHP framework for seamless web
> development. Boost productivity with its modular architecture and smooth
> integration capabilities.

This document defines how code is written across the framework. It applies to the
framework core (`src/SwiftFuse/`, namespace `SwiftFuse\`) and to application code
(`app/`, namespace `App\`). Every contribution must follow it.

**Baseline:** PHP 8.4, **English only** (identifiers, comments and documentation),
**PSR-12** code style, **PSR-4** autoloading, `declare(strict_types=1);` in every
file.

---

## 1. Naming

Names describe intent and read like prose. No abbreviations beyond well-known ones
(`id`, `url`, `db`, `sql`), no transliteration, no Hungarian notation.

| Element | Convention | Example |
|---------|------------|---------|
| Namespace segment | `PascalCase` | `SwiftFuse\Storage` |
| Class / Enum / Trait | `PascalCase` | `StorageManager`, `Extensible` |
| Interface | `PascalCase` + `Interface` suffix | `JobInterface` |
| Abstract base class | `PascalCase`, no prefix | `Controller`, `Model` |
| Method / function | `camelCase`, verb-first | `streamWithRange()`, `dispatch()` |
| Property / variable | `camelCase`, noun | `$jobsPath`, `$statement` |
| Constant / enum case | `UPPER_SNAKE_CASE` | `CHUNK_SIZE`, `MAX_BYTES` |
| Config / `.env` key | `snake_case` / `UPPER_SNAKE` | `app.locale`, `DB_HOST` |
| Boolean | affirmative predicate | `$isValid`, `hasExtension()` |

Rules:

- **One type per file**; the file name matches the type name (`Router.php` →
  `class Router`).
- File-and-folder layout mirrors the namespace (PSR-4): `SwiftFuse\Storage\SignedUrl`
  → `src/SwiftFuse/Storage/SignedUrl.php`.
- Controllers end in `Controller` (`InvoiceController`); jobs are nouns describing
  the work (`CompressImageJob`); services describe a capability (`StorageManager`).
- Methods that return a boolean read as a question (`isExpired()`, `wantsJson()`).
- Avoid “utils/misc/helper” catch-all class names; group by capability instead
  (`Str`, `Format`, `Files`).

## 2. Format

- Follow **PSR-12**. 4-space indentation, no tabs, LF line endings, UTF-8, a
  trailing newline, no trailing whitespace.
- Soft limit of **120 columns** per line.
- One blank line between methods; no more than one consecutive blank line.
- Braces on their own line for classes and methods; same line for control
  structures.
- **Type everything** the language allows: parameters, return types, and typed
  properties. Prefer precise nullable types (`?string`) over `mixed`.
- Use the strict, modern syntax already used in the core: constructor property
  promotion, `match` over long `switch`, arrow functions for small callbacks,
  `::class`, named arguments where they aid clarity.
- Import symbols with `use`; do not reference fully-qualified names inline.
- `declare(strict_types=1);` is the first statement after the opening tag.

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Invoice;
use SwiftFuse\Http\Controller;

final class InvoiceController extends Controller
{
    protected string $folder = 'invoices';

    public function show(string $id): void
    {
        $invoice = $this->model('Invoice')->find((int) $id);
        $this->view('invoices.show', ['invoice' => $invoice]);
    }
}
```

Classes that are not designed for extension are declared `final`.

## 3. Error handling

Errors are signaled with **exceptions**, never with magic return values, error
codes, or output. The framework converts any uncaught throwable into the right
HTTP response and a log entry, so let exceptions propagate unless you can
genuinely recover.

- **HTTP errors:** throw `SwiftFuse\Http\HttpException` with the status code.

  ```php
  use SwiftFuse\Http\HttpException;

  if ($invoice === null) {
      throw new HttpException(404, "Invoice {$id} not found.");
  }
  ```

- **Invalid arguments / state:** throw the matching SPL exception
  (`InvalidArgumentException`, `RuntimeException`, …) with a clear message.
- **Catch narrowly:** catch the most specific exception type, only where you can
  do something meaningful (retry, fall back, add context). Never catch
  `\Throwable` just to silence it.
- **Add context, then rethrow** when you can’t handle it:

  ```php
  try {
      $payload = $this->decode($raw);
  } catch (\JsonException $e) {
      throw new \RuntimeException('Malformed job payload.', previous: $e);
  }
  ```

- **Database:** wrap PDO work in `try/catch (PDOException $e)`; inside a
  transaction, let it bubble so the rollback runs; outside one, capture the
  message and degrade gracefully (the model exposes it).
- **Validate inputs** at the boundary (`SwiftFuse\Support\Validator`); never trust
  superglobals.
- **Visibility:** `APP_DEBUG=true` surfaces detail in development; in production
  `APP_DEBUG=false` shows a generic error page while the detail goes to the log.
- **Never suppress** errors with the `@` operator.

## 4. Logging

The framework logs to the **`storage/logs/`** directory (outside the web root):

| Channel | File | Source |
|---------|------|--------|
| Application/runtime errors | `storage/logs/error.log` | `SwiftFuse\Foundation\ErrorHandler` |
| Background jobs | `storage/logs/queue.log` | `SwiftFuse\Queue\Worker` |

Guidelines:

- **Don’t print to the response.** Diagnostic output never goes to the browser or
  to STDOUT in web context; it goes to a log file. No `var_dump`, `print_r` or
  `echo`-debugging in committed code.
- Write log lines under `storage_path('logs/...')`, one event per line, prefixed
  with an ISO-like timestamp and a level, e.g.:

  ```php
  $line = sprintf('[%s] [%s] %s%s', date('Y-m-d H:i:s'), 'WARNING', $message, PHP_EOL);
  error_log($line, 3, storage_path('logs/app.log'));
  ```

- Use levels consistently: `INFO` (notable events), `WARNING` (recoverable),
  `ERROR` (failed operation), `CRITICAL` (system unusable).
- **Log facts, not secrets.** Never log passwords, tokens, full card numbers,
  `APP_KEY`, or raw request bodies containing credentials.
- Log a message **once**, at the point you decide not to handle it further;
  avoid logging the same error at every layer.
- Logs are append-only operational records; rotate them in deployment (e.g.
  `logrotate`) rather than from application code.

## 5. Comments

Comments explain **why**, not **what** — the code already says what. Keep them
accurate; a wrong comment is worse than none.

- **Every** class, interface, trait, method, function and property carries a
  **PHPDoc** block, in English.
- Method docblocks document `@param`, `@return`, and `@throws` when the method can
  throw. Use generic array shapes where useful (`array<int, object>`,
  `array{name: string, size: int}`).
- A class docblock states the class’s single responsibility in one or two
  sentences.
- Inline `//` comments are reserved for non-obvious decisions, edge cases, or
  security-relevant reasoning — not narration of obvious code.

```php
/**
 * Stream a protected storage file after an authorization check passes.
 *
 * @param string $relativePath Path relative to the storage root.
 * @param callable(string):bool $authorize Receives the absolute path; return true to allow.
 * @return never
 *
 * @throws HttpException With status 403 when authorization fails.
 */
public function stream(string $relativePath, callable $authorize): never
```

Avoid: commented-out code (delete it — version control remembers), `TODO`s
without an owner/context, and decorative banner comments.

## 6. Dependencies

The framework is **dependency-light by design**: the core runs on PHP and its
standard extensions, with no required third-party packages.

- **Autoloading:** PSR-4 via the built-in autoloader. Composer is optional —
  `vendor/autoload.php` is loaded automatically when present, so libraries
  integrate without ceremony.
- **Prefer what exists:** reach for the standard library and the framework’s own
  `SwiftFuse\Support\*` utilities (`Str`, `Validator`, `Format`, `Files`, `Config`,
  `Env`, `Hooks`) before adding anything new.
- **Add a dependency deliberately:** only when it earns its weight (security,
  correctness, or large scope you should not reimplement). Pin it in
  `composer.json`, prefer well-maintained, narrowly-scoped packages, and isolate
  it behind one of your own classes so it can be swapped.
- **Inject, don’t hardcode:** depend on the smallest interface you need and obtain
  services from the container (`app(StorageManager::class)`), or via constructor
  injection. Bind/replace implementations in `config/services.php`.
- **Direction of dependencies:** application code (`app/`) depends on the core
  (`src/SwiftFuse/`); the core never depends on application code.
- **Configuration over constants:** read environment-specific values through
  `config()` / `env()`, never hardcode hosts, paths, or secrets.

## 7. Anti-patterns

Avoid these. Each row pairs the smell with the standard’s remedy.

| Anti-pattern | Do this instead |
|--------------|-----------------|
| Editing the framework core to customize behavior | Extend from `app/` (inheritance, container bindings, macros, hooks) — see [EXTENDING.md](EXTENDING.md) |
| Building SQL by concatenating input | Prepared statements with `query()` + `bind()` |
| Fat controllers holding business logic | Keep controllers thin; put logic in models/services |
| Logic, queries or side effects in views | Views only present data passed to them |
| `new Service()` scattered through the code | Resolve from the container (`app(...)`) / inject it |
| Global mutable state and ad-hoc `global` | Pass dependencies explicitly; use `config()` for settings |
| Reading `$_GET`/`$_POST`/`$_SERVER` everywhere | Read input through `SwiftFuse\Http\Request` |
| Silencing errors with `@` or empty `catch` | Handle, add context, or let it propagate |
| Catching `\Throwable` and continuing blindly | Catch the specific type you can handle |
| `echo`/`var_dump` debugging left in code | Write to a log under `storage/logs/` |
| Magic numbers/strings inline | Named constants or `config()` values |
| Returning `mixed`/untyped “anything” | Precise parameter and return types |
| Hardcoded hosts, paths, secrets | `env()` / `config()` and path helpers |
| Echoing unescaped output in views | `htmlspecialchars(..., ENT_QUOTES)` |
| God classes that do everything | One clear responsibility per class |
| Deep inheritance chains | Favor composition, traits, and small interfaces |

---

## Versioning

The project follows **Semantic Versioning**. Public APIs change only across major
versions; minor releases add functionality compatibly; patches fix bugs.

## Definition of done

A change is ready when it: is 100% English; passes `php -l`; follows PSR-12;
types every signature; documents every new method; handles and logs errors per
this standard; adds no needless dependency; and introduces none of the
anti-patterns above.
