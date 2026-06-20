# Extending SwiftFusePHP

The framework core in `src/SwiftFuse/` is meant to stay untouched. You customize
and extend behavior entirely from `app/`, using four complementary mechanisms.
Pick the lightest one that fits your need.

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

`Closure`s are bound to the instance, so `$this` works inside them.

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

## 4. Lifecycle hooks/events

Plug into named extension points with `SwiftFuse\Support\Hooks`:

```php
use SwiftFuse\Support\Hooks;

// Block guests from any controller action globally.
Hooks::on('controller.before', function (string $action, array $params, object $c): bool {
    return isset($_SESSION['user']);  // false aborts the request with 403
});
```

Built-in events: `controller.before`, `controller.after`. Fire your own with
`Hooks::fire('my.event', [...])` and listen with `Hooks::on('my.event', ...)`.

---

## Background jobs

```php
// Create one: php fuse make:job SendWelcomeEmail
use SwiftFuse\Queue\QueueManager;

app(QueueManager::class)->dispatch(new App\Jobs\SendWelcomeEmail($userId));
```

Process pending jobs: `php fuse queue:work` (or `--daemon`). Set
`QUEUE_DRIVER=async` to also run each job immediately in a detached process.

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
