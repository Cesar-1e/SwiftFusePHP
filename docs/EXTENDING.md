# Extending SwiftFusePHP

[← Back to README](../README.md)

The framework core in `src/SwiftFuse/` is meant to stay untouched. You customize
and extend behavior entirely from `app/`, using the complementary mechanisms
below. Pick the lightest one that fits your need.

## 1. Inheritance + `app/` precedence

Framework base classes are abstract on purpose. Extend them in `app/`:

```php
// app/Controllers/InvoiceController.php
namespace App\Controllers;

use SwiftFuse\Http\Controller;

final class InvoiceController extends Controller
{
    protected string $folder = 'invoices';

    public function index(string $view = 'index', string ...$params): void
    {
        $this->view('invoices.index', ['invoices' => $this->model('Invoice')->all()]);
    }
}
```

The router resolves `App\Controllers\{Name}Controller` first, so your classes
take precedence over the (deprecated) legacy controllers automatically.

## 2. Runtime methods via the `Extensible` trait

Add methods to a core class at runtime — no subclassing, no core edits. Register
them in `app/bootstrap.php`:

```php
use SwiftFuse\Routing\Router;

Router::extend('redirect', function (string $to): void {
    header('Location: ' . base_url($to));
    exit;
});

// Anywhere you have the router:
$router->redirect('login');
```

`Closure`s are bound to the instance, so `$this` works inside them. Static
closures work as well; they just have no `$this`.

An extension belongs to the **class you register it on** and to its subclasses:

```php
use App\Controllers\Api\ApiController;
use SwiftFuse\Http\Controller;

Controller::extend('appVersion', fn (): string => (string) config('app.version'));  // every controller
ApiController::extend('apiVersion', fn (): string => 'v1');                         // API controllers only
```

- `Router` and `Controller` keep separate registries, so equal names never collide.
- An extension registered on a subclass is not visible on its parent or its
  siblings.
- When a subclass registers a name its parent already has, the subclass version
  wins for it and its descendants.
- `hasExtension()` and static calls (`ApiController::apiVersion()`) follow the
  same rules.

## 3. Service bindings (swap an implementation)

Every core service is resolved from the container. Rebind it to your own class in
`config/services.php` (or in `app/bootstrap.php`) without editing the core:

```php
// config/services.php
use SwiftFuse\Storage\StorageManager;

return [
    StorageManager::class => fn () => new App\Services\S3StorageManager(),
];
```

Resolve services with the `app()` helper: `app(StorageManager::class)`.

The router creates **controllers** through the container too, so a binding can
decorate or replace a controller — see
[CONTROLLERS.md](CONTROLLERS.md#decorating-or-replacing-a-controller).

## 4. Lifecycle hooks/events

Plug into named extension points with `SwiftFuse\Support\Hooks`. A **fired** event
lets any listener veto what is about to happen; a **filtered** event passes a value
through every listener so each one can transform it.

```php
use SwiftFuse\Support\Hooks;

// Block guests from any controller action globally.
Hooks::on('controller.before', function (string $action, array $params, object $c): bool {
    return isset($_SESSION['user']);  // false aborts the request with 403
});
```

### Priorities

`Hooks::on()` accepts an optional priority. Higher priorities run first; listeners
with the same priority run in registration order. The default priority is `0`.

```php
Hooks::on('controller.before', $authenticate, priority: 100);  // runs first
Hooks::on('controller.before', $authorize);                    // priority 0, runs after
```

### Filters

`Hooks::filter($event, $value, $arguments)` hands each listener the current value
followed by the arguments, and passes what the listener returns on to the next
one:

```php
Hooks::on('invoice.total', fn (float $total, string $currency): float => round($total, 2));

$total = Hooks::filter('invoice.total', $total, [$currency]);
```

A filter listener must return the value, unchanged when it has nothing to do.
`Hooks::fire()` keeps its veto semantics: the first listener that returns `false`
stops the dispatch.

### Built-in events

| Event | Kind | Listener receives | Listener returns |
|-------|------|-------------------|------------------|
| `controller.before` | fire | `string $action, array $params, Controller $controller` | `false` blocks the request (403) |
| `controller.after` | fire | `string $action, array $params, Controller $controller` | ignored |
| `controller.responding` | filter | `mixed $payload, int $status, Controller $controller, ?string $action, array $params` | the payload `json()` sends |

`controller.after` fires once per action. For actions that answer with `json()` it
fires only when `APP_JSON_LIFECYCLE=true` — see
[CONTROLLERS.md](CONTROLLERS.md#json-responses-apis--ajax).

Fire your own events with `Hooks::fire('my.event', [...])`, transform values with
`Hooks::filter('my.value', $value, [...])`, and listen with
`Hooks::on('my.event', ...)`.

## 5. Your own namespaces (PSR-4 roots)

Keep customizations outside `app/`, in their own namespace, by declaring the root
in `composer.json`:

```json
"autoload": {
    "psr-4": {
        "SwiftFuse\\": "src/SwiftFuse/",
        "App\\": "app/",
        "Extensions\\": "extensions/"
    }
}
```

- **With Composer**, run `composer dump-autoload`; `vendor/autoload.php` then loads
  the root.
- **Without Composer** (no `vendor/autoload.php`), the built-in autoloader reads
  the `autoload.psr-4` section of `composer.json` itself, so
  `Extensions\Billing\InvoiceFields` loads from
  `extensions/Billing/InvoiceFields.php` without a hand-written loader.

The built-in autoloader only accepts directories **inside the project**. Absolute
paths elsewhere, `..` escapes and stream wrappers are skipped with an
`E_USER_WARNING`, and so is an invalid `composer.json`; the application keeps
running with the remaining roots. A prefix may list several directories, and the
most specific prefix is searched first.

## 6. Transactions across models

Customizations often write next to a standard model. Enable
`DB_SHARED_CONNECTION=true` and wrap both writes in
`SwiftFuse\Database\Transaction::run()`, so they are committed or rolled back
together — see [DATABASE.md](DATABASE.md#transactions-across-models).

---

## Background jobs

```php
// Create one: php fuse make:job SendWelcomeEmail
use SwiftFuse\Queue\QueueManager;

app(QueueManager::class)->dispatch(new App\Jobs\SendWelcomeEmail($userId));
```

Process pending jobs: `php fuse queue:work` (or `--daemon`). Set
`QUEUE_DRIVER=async` to also run each job immediately in a detached process, or
`QUEUE_DRIVER=deferred` to run it after the response on PHP-FPM or LiteSpeed.

## Protected files

Put private files under `storage/app/` (outside the web root) and serve them only
after authorization:

```php
app(SwiftFuse\Storage\StorageManager::class)
    ->stream('invoices/2026/inv-1.pdf', fn (string $path): bool => isset($_SESSION['user']));
```

For media you want to embed in HTML, generate a short-lived signed URL:

```php
$url = SwiftFuse\Storage\SignedUrl::make('media/file', 'video/intro.mp4', 300);
// <video src="<?= $url ?>"> — validated and streamed with HTTP Range support.
```

For best performance set `STORAGE_ACCEL=apache` (mod_xsendfile) or `nginx`
(X-Accel-Redirect) so the web server streams the bytes instead of PHP.
