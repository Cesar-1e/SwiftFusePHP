# Controllers & Views

[← Back to README](../README.md)

Controllers live in `app/Controllers/` (namespace `App\`) and extend
`SwiftFuse\Http\Controller`. Views live in `resources/views/`.

## A controller

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Person;
use SwiftFuse\Http\Controller;

final class PeopleController extends Controller
{
    /** View sub-folder for this controller. */
    protected string $folder = 'people';

    public function index(string $view = 'index', string ...$params): void
    {
        $this->view('people.index', ['people' => $this->model('Person')->all()]);
    }
}
```

The file/class name (`PeopleController`) determines the route segment
(`/people`); see [ROUTING.md](ROUTING.md).

## The base controller API

| Member | Description |
|--------|-------------|
| `protected string $folder` | View sub-folder used by the default `index()`. |
| `index(string $view = 'index', string ...$params)` | Default action: renders `{folder}.{view}`. |
| `view(string $name, array $data = [])` | Render a view with data. |
| `json(mixed $data, int $status = 200): never` | Send a JSON response and stop; the payload passes through `controller.responding` first. |
| `model(string $name): Model` | Load a model (prefers `App\Models\{Name}`). |
| `before(string $action, array $params): bool` | Hook before the action (return `false` → 403). |
| `after(string $action, array $params): void` | Hook after the action; runs once per dispatched action. |
| `enterAction()` / `leaveAction()` | Internal and `final`: the router calls them around every action. They are never routes. |

`Controller` also uses the `Extensible` trait, so you can attach methods at
runtime — see [EXTENDING.md](EXTENDING.md).

## Rendering views

```php
$this->view('people.index', ['people' => $people, 'title' => 'Team']);
```

- The name uses **dot or slash** notation: `people.index` →
  `resources/views/people/index.php`.
- Each array key becomes a **local variable** inside the template (`$people`,
  `$title`).
- Both `.php` and `.html` templates are supported (`.php` wins).
- A missing view raises a `404`.

A view is plain PHP. Always escape output:

```php
<!-- resources/views/people/index.php -->
<h1><?= htmlspecialchars($title, ENT_QUOTES) ?></h1>
<ul>
<?php foreach ($people as $person): ?>
    <li><?= htmlspecialchars($person->name, ENT_QUOTES) ?></li>
<?php endforeach; ?>
</ul>
```

You can render a view from anywhere with the `view()` helper:

```php
view('errors.404', ['status' => 404]);
```

### Assets & links

Use `base_url()` for links and assets so they work under any deployment path:

```php
<link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
<a href="<?= base_url('people') ?>">People</a>
```

Public assets live in `public/css`, `public/js`, etc.

## JSON responses (APIs / AJAX)

```php
public function list(): never
{
    $people = $this->model('Person')->all();
    $this->json(['ok' => true, 'data' => $people]);
}
```

`json()` sets the `Content-Type`, encodes the payload and stops execution. Pass a
status code as the second argument: `$this->json(['error' => 'Nope'], 422)`.

### Shaping every JSON payload: `controller.responding`

Before encoding, `json()` passes the payload through the `controller.responding`
filter. Listeners receive the payload, the HTTP status, the controller, the action
being dispatched (`null` when `json()` runs outside a router dispatch) and its
route parameters, and **return** the payload to send:

```php
// app/bootstrap.php
use SwiftFuse\Http\Controller;
use SwiftFuse\Support\Hooks;

Hooks::on(
    'controller.responding',
    function (mixed $payload, int $status, Controller $controller, ?string $action, array $params): mixed {
        if (is_array($payload) && $status < 400) {
            $payload['meta'] = ['action' => $action];
        }

        return $payload;
    }
);
```

The filter also runs when `json()` answers from `before()`, so check the status or
the payload when you only want to decorate successful responses. Without
listeners the payload is sent unchanged.

### The after-hook on JSON responses: `APP_JSON_LIFECYCLE`

`json()` ends the request, so the router never gets to call `after()` for an action
that answers with it. Set `APP_JSON_LIFECYCLE=true` (`app.json_lifecycle`) to let
`json()` run `after()` — and the global `controller.after` event — itself:

- It runs **once**, after the payload is filtered and before anything is sent.
- It never runs twice, even when `after()` itself responds with `json()`.
- It does not run when `json()` answers from `before()`, because the action never
  started.

The option is off by default, matching earlier versions, where `after()` is
skipped for JSON responses. Actions that return normally run `after()` once in
both modes.

> Projects that keep their own copy of `config/app.php` must add the key for the
> variable to take effect:
> `'json_lifecycle' => (bool) env('APP_JSON_LIFECYCLE', false),`

## Loading models

```php
$person = $this->model('Person');   // App\Models\Person
$rows   = $person->all();
```

`model()` resolves `App\Models\{Name}` first. See [DATABASE.md](DATABASE.md).

## The request

`SwiftFuse\Http\Request` wraps the current request. The router builds it for you,
but you can capture it anywhere:

```php
use SwiftFuse\Http\Request;

$request = Request::capture();
$request->method();              // 'GET', 'POST', …
$request->segments();            // ['people', 'show', '42']
$request->input('email');        // POST then GET
$request->wantsJson();           // true for XHR / Accept: application/json
```

## Lifecycle hooks

Override `before()` / `after()` to run logic around every action of a controller:

```php
public function before(string $action, array $params): bool
{
    if (!isset($_SESSION['user'])) {
        return false;            // aborts with 403
    }
    return parent::before($action, $params);
}
```

`parent::before()` fires the global `controller.before` event, so app-wide
listeners registered in `app/bootstrap.php` still run — see
[EXTENDING.md](EXTENDING.md#4-lifecycle-hooksevents).

The router runs `after()` once per dispatched action, when the action returns. For
actions that answer with `json()`, see
[the after-hook on JSON responses](#the-after-hook-on-json-responses-app_json_lifecycle).

## Decorating or replacing a controller

When an application is bootstrapped (every web request), the router creates
controllers through the service container, so a binding can decorate or replace a
controller without editing its file or its routes. Without an application — a
console script, a test — the router instantiates the class directly.

**Decorate** a controller with a subclass that adds behavior around its actions
(the decorated class must not be `final`):

```php
// app/Controllers/AuditedInvoiceController.php
final class AuditedInvoiceController extends InvoiceController
{
    public function store(): never
    {
        app(AuditLog::class)->record('invoice.store');
        parent::store();
    }
}
```

```php
// app/bootstrap.php — $app is the application container
use App\Controllers\AuditedInvoiceController;
use App\Controllers\InvoiceController;

$app->bind(InvoiceController::class, fn () => new AuditedInvoiceController());
```

**Replace** it with another controller that provides the routed actions:

```php
$app->bind(
    InvoiceController::class,
    fn ($app) => new App\Controllers\V2\InvoiceController($app->make(App\Services\InvoiceService::class))
);
```

- Bind the class the route names (`[InvoiceController::class, 'store']`) or the
  one convention routing resolves (`App\Controllers\InvoiceController` for
  `/invoice`).
- The factory must return a `SwiftFuse\Http\Controller`; anything else makes the
  router throw an `UnexpectedValueException`. A replacement must declare every
  action its routes call.
- The container does not autowire: build constructor dependencies in the factory.
- `bind()` creates a controller per dispatch. Entries in `config/services.php` are
  shared (singletons), which is fine for a web request but reuses the instance in
  long-running processes.

## Error responses

Throw `SwiftFuse\Http\HttpException` to return a specific status with the matching
error view (`resources/views/errors/{code}.php`):

```php
use SwiftFuse\Http\HttpException;

if ($person === null) {
    throw new HttpException(404, "Person {$id} not found.");
}
```
