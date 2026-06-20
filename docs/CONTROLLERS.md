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
| `json(mixed $data, int $status = 200): never` | Send a JSON response and stop. |
| `model(string $name): Model` | Load a model (prefers `App\Models\{Name}`). |
| `before(string $action, array $params): bool` | Hook before the action (return `false` → 403). |
| `after(string $action, array $params): void` | Hook after the action. |

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

## Error responses

Throw `SwiftFuse\Http\HttpException` to return a specific status with the matching
error view (`resources/views/errors/{code}.php`):

```php
use SwiftFuse\Http\HttpException;

if ($person === null) {
    throw new HttpException(404, "Person {$id} not found.");
}
```
