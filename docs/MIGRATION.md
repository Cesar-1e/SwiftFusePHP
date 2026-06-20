# Migrating to the new SwiftFusePHP structure

[← Back to README](../README.md)

Starting with `0.9.9`, SwiftFusePHP ships a new, English, PSR-4 core in
`src/SwiftFuse/` and a developer space in `app/`, served from a `public/` web
root. The previous (Spanish) layout still works but is **`@deprecated`** and will
be removed in `1.0`. This guide explains how to run both side by side during the
transition and how to migrate your code, controller by controller.

---

## 1. Accessing the legacy app during the transition

The supported entry point is now `public/index.php`, with the web server's
**DocumentRoot pointing at `public/`**. The old root `index.php` is intentionally
no longer the web entry point — that is what keeps `storage/`, `.env` and
`config/` outside the web root.

Your legacy controllers remain reachable **through the new front controller** via
the compatibility bridge (`SwiftFuse\Routing\LegacyBridge`):

| Request               | Resolves to                                   |
|-----------------------|-----------------------------------------------|
| `/inicio`             | `App\Controllers\InicioController` if it exists, otherwise legacy `Controlador/Inicio_Controller.php` (`InicioControl`) |
| `/person/list`        | New `App\Controllers\PersonController` first, else legacy `PersonControl` |

So during migration you can hit a legacy module at `/<name>` and it will run the
old controller until you create the new one (the new one always wins).

### Legacy views and assets

Legacy views in `Vista/` reference assets (`CSS/`, `JS/`, `Includ/`) using the
old paths, which now live **outside** `public/`. The page will render, but its
styles/scripts may 404. To keep a legacy page looking right until you migrate it,
copy the asset folders it needs into `public/`:

```bash
cp -r CSS public/CSS
cp -r JS  public/JS
```

> Do **not** move the DocumentRoot back to the project root to "restore" the old
> site. That re-exposes `storage/`, `.env` and `config/` to the web and undoes
> the privatization. Use it only as a last resort and revert it afterwards.

---

## 2. What maps to what

| Legacy (`@deprecated`)                              | New replacement                                              |
|-----------------------------------------------------|-------------------------------------------------------------|
| `Controlador/*_Controller.php` (`XxxControl`)       | `app/Controllers/XxxController.php` (extends `SwiftFuse\Http\Controller`) |
| `Modelo/*_Model.php` (`XxxMode`)                    | `app/Models/Xxx.php` (extends `SwiftFuse\Database\Model`)    |
| `Modelo/Conexion_Model.php` (`Conexion`)            | `SwiftFuse\Database\Connection` (same PDO-based API)         |
| `Vista/<folder>/<name>_View.php`                    | `resources/views/<folder>/<name>.php`                       |
| `Config/configurar.user.php` constants              | `config/{app,database,storage,queue}.php` + `.env`          |
| Helpers in `Config/configurar.php`                  | `SwiftFuse\Support\{Files,Validator,Str,Format}`            |
| `Libreria/File.php`, `StorageService`               | `SwiftFuse\Storage\StorageManager` (+ `SignedUrl`)          |
| `Libreria/Services/BackgroundService`               | `SwiftFuse\Queue\QueueManager` + `php fuse queue:work`      |
| `Archivos/` and its scripts                         | `storage/` + `app/Jobs/*`                                   |
| `error()` / `setStatusCode()`                       | `throw new SwiftFuse\Http\HttpException($code)`             |

### Internal framework-call substitutions

> These are **internal** adaptations inside the *new* file only. They never change
> a route, an action method name, a controller URL segment, or a response field —
> those stay identical (see the golden rules below). The class only gains the
> `Controller` suffix so its route segment is preserved (`PersonaControl` →
> `PersonaController`, still served at `/persona`).

| Legacy                          | New                                        |
|---------------------------------|--------------------------------------------|
| `class XxxControl`              | `class XxxController` (PascalCase + suffix) |
| `$folder` + `cargaVista()`      | `$folder` + `index()` / explicit actions    |
| `$this->modelo('Xxx')`          | `$this->model('Xxx')` (returns the model)   |
| `$this->vista('a/b')`           | `$this->view('a.b', $data)`                 |
| `$this->retorno` + `retornar()` | `$this->json($data)`                        |
| `beforeAction()/afterAction()`  | `before()/after()` or `SwiftFuse\Support\Hooks` |
| `$this->Conexion->getsArray()`  | `$this->connection->getArrays()`            |
| `getMensaje()` / `getEntidad()` | `getMessage()` / `getEntity()`              |

---

### Golden rules (non-breaking migration)

1. **Never touch the existing legacy files** — only add new ones.
2. **Never refactor** the ported logic — copy it 1:1.
3. **Never rename** classes' route segments, action methods, or response fields,
   **even if they are in Spanish** — that breaks the project and any API clients.
4. **Keep every route identical** (same path, same HTTP method, same response
   shape). Some projects are consumed as an API; a changed route breaks all clients.

## 3. Step-by-step (one module at a time)

1. **Model** — create `app/Models/<Xxx>.php` (keep the original name) extending
   `SwiftFuse\Database\Model`. Inside, swap only the framework calls:
   `$this->Conexion` → `$this->connection`, `getsArray` → `getArrays`, etc. Keep
   the SQL, the method names and the returned data shape exactly as they were.
2. **Controller** — create `app/Controllers/<Xxx>Controller.php` extending
   `SwiftFuse\Http\Controller`. Keep `$folder` and **all action method names
   unchanged** (they are the route). Swap only internal calls: `vista()`→`view()`,
   `modelo()`→`model()`, `retornar()`→`json($this->retorno)` keeping the same keys.
3. **View** — copy `Vista/<folder>/<name>_View.php` to
   `resources/views/<folder>/<name>.php` **unchanged** (same markup, same visible
   text); only adjust the include path. Do not retranslate or restyle it.
4. **Config** — add any constants the module used to the relevant `config/*.php`
   file, reading them from `.env` via `env()`. Keep the same effective values.
5. **Files / background** — optionally replace direct file delivery with
   `StorageManager::stream()` / `SignedUrl`, and `exec(...&)` with a queued job
   (`app/Jobs/*` + `php fuse queue:work`) — without changing the route or response.
6. **Test** — hit the **same** route(s); the new controller now takes precedence
   over the legacy one. Confirm the response is byte-for-byte compatible. Only
   after that, and only if nothing references them, the developer may remove the
   legacy files for that module (optional, their decision).

---

## 4. Developer migration prompt

Paste the following into your AI assistant (e.g. Claude) **together with the
legacy file(s)** you want to migrate. It encodes the rules above.

```text
You are migrating a module of a developer's SwiftFusePHP project from its legacy
(Spanish, no-namespace) layout to the new structure. This migration MUST be
NON-BREAKING. Follow these rules exactly.

HARD CONSTRAINTS — never violate these (breaking them breaks live projects/APIs):
- Do NOT modify, refactor or delete any existing project file. Only CREATE new files.
- Do NOT rename anything the project or its API clients depend on: keep the SAME
  action/method names, the SAME controller URL segment, the SAME route URLs and
  HTTP methods, and the SAME request/response field names — EVEN IF they are in
  Spanish. Renaming or translating these breaks the developer's code and any API
  consumers.
- PRESERVE every existing route EXACTLY. The new controller must answer at the
  identical path, with the identical HTTP method, and return the identical
  response shape (same JSON keys, same structure, same values).
- Do NOT translate domain names, variables, or anything that appears in the
  output. Keep Spanish names as-is. (Adding English PHPDoc is fine — it does not
  change behavior.)
- Do NOT change the database schema, the SQL, or the business logic. Port it 1:1.
- If any required change would alter a route, a publicly referenced class name, or
  a response field, STOP: leave that module on the legacy bridge and list it under
  "manual review" instead of changing it.

CONTEXT
- New core lives in src/SwiftFuse/ (namespace SwiftFuse\). Do NOT edit it.
- New code goes in app/ (namespace App\), PSR-4: App\ => app/.
- Views go in resources/views/<folder>/<name>.php. Config in config/*.php read
  from .env via env(). Database stays PDO via SwiftFuse\Database\Connection.

ALLOWED ADAPTATIONS — internal wiring ONLY; must not change any route/name/output:
- Place the new file under the new namespace/structure. The controller class keeps
  its ORIGINAL stem and only gains the "Controller" suffix required by the
  autoloader (e.g. PersonaControl -> PersonaController) SO THAT the route segment
  (/persona) stays identical. First confirm no other code instantiates the class
  by its old name; if it does, flag it for manual review instead.
- Keep ALL action method names identical (they are part of the route).
- Swap the legacy base class for the new one and adapt ONLY internal framework
  calls, never changing any route/name/output:
    $this->vista('a/b')   -> $this->view('a.b')        (same template, same output)
    $this->modelo('X')    -> $this->model('X')
    $this->retornar()     -> $this->json($this->retorno)  (KEEP the same JSON keys)
    $this->Conexion->...   -> $this->connection->...  (getsArray->getArrays, etc.)
    error($code)          -> throw new SwiftFuse\Http\HttpException($code)
- Move a view to resources/views/ UNCHANGED (same markup, same visible text); only
  fix the include path. Do NOT retranslate or restyle it.
- Add declare(strict_types=1) and PHPDoc to the NEW files only.

DELIVERABLES
1. The new file(s) with their full target path.
2. A route table proving each route is unchanged (METHOD + path: before == after).
3. Any config keys to add to config/*.php and .env.
4. A "manual review" list of anything that could not be migrated without a
   breaking change.

Here is the legacy code to migrate:
<paste your legacy file(s) here>
```

---

## 5. Verification checklist

- [ ] `php -l` passes on every new file.
- [ ] The migrated route renders correctly at `/<name>` (new controller wins).
- [ ] Database reads/writes still work (PDO, prepared statements).
- [ ] No direct access to `storage/`, `.env` or `config/` over the web.
- [ ] Once verified, the legacy files for that module can be removed.
