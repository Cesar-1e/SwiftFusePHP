# Routing

[← Back to README](../README.md)

The router (`SwiftFuse\Routing\Router`) resolves an incoming request to a
controller action using two strategies, checked in order:

1. **Explicit routes** registered in `routes/web.php` (highest priority).
2. **Convention-based** resolution from the URL.

If neither matches a framework controller, the router falls back to the
**legacy bridge** for backward compatibility.

## How a URL is parsed

Pretty URLs are produced by `public/.htaccess`, which rewrites every request to
`index.php?url=...`. The path is split into segments:

```
/people/show/42
   │     │   └── parameters → passed to the action
   │     └────── action (method)
   └──────────── controller
```

## Convention-based routing

`/{controller}/{method}/{params...}` maps to
`App\Controllers\{Controller}Controller`:

| URL | Controller | Action | Params |
|-----|------------|--------|--------|
| `/` | `HomeController` | `index` | — |
| `/people` | `PeopleController` | `index` | — |
| `/people/list` | `PeopleController` | `list` | — |
| `/people/show/42` | `PeopleController` | `show` | `['42']` |

Rules:

- The default controller is **`Home`**; the default action is **`index`**.
- If the second segment is a real method on the controller, it becomes the
  **action**; otherwise the action stays `index` and the segment is passed as the
  first parameter (so `/home/about` calls `index('about')`).
- Remaining segments are spread as individual string arguments to the action.

## Explicit routes

Declare routes in `routes/web.php`. The `$router` instance is provided for you:

```php
/** @var SwiftFuse\Routing\Router $router */

use App\Controllers\PeopleController;
use App\Controllers\MediaController;

$router->get('people', [PeopleController::class, 'index']);
$router->post('upload/store', [App\Controllers\UploadController::class, 'store']);
$router->get('media/file', [MediaController::class, 'file']);
```

Available methods: `get()`, `post()`, and `any()` (matches every HTTP method).

### Placeholders

Use `{name}` to capture a path segment; captured values are passed to the handler
in order:

```php
$router->get('people/{id}', [PeopleController::class, 'show']);
// /people/42  ->  PeopleController::show('42')
```

### Closures

A handler can also be a closure:

```php
$router->get('ping', fn () => print('pong'));
```

## Custom routing helpers (macros)

`Router` uses the `Extensible` trait, so you can add routing helpers at runtime
without editing the core — see
[EXTENDING.md](EXTENDING.md#2-runtime-methods-via-the-extensible-trait).

## Lifecycle hooks

Before an action runs, the controller's `before()` hook fires (and the global
`controller.before` event); after it runs, `after()` fires. Returning `false`
from `before()` (or a `controller.before` listener) aborts the request with a
**403**. See [CONTROLLERS.md](CONTROLLERS.md#lifecycle-hooks).

## The legacy bridge

When no `App\Controllers\{Name}Controller` exists, the router asks
`SwiftFuse\Routing\LegacyBridge` to load the equivalent legacy controller
(`Controlador/{Name}_Controller.php`, class `{Name}Control`) and dispatches it
using the original calling convention (default action `cargaVista`). This keeps
old URLs working at `/<name>` during migration. It is `@deprecated` and will be
removed in `1.0` — see [MIGRATION.md](MIGRATION.md).
