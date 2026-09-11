# Changelog

All notable changes to SwiftFusePHP are documented in this file. The project
follows [Semantic Versioning](https://semver.org/).

## [0.10.0] - 2026-09-11

Native support for extending applications without touching standard files:
transactions across models, a complete lifecycle for JSON responses, hook
priorities and filters, per-class runtime extensions, controllers resolved
through the container, and extra PSR-4 roots without Composer. With the default
configuration, applications built on 0.9.9 keep their behavior.

### Added

- **Shared connection and transactions across models.** `DB_SHARED_CONNECTION`
  (`database.shared_connection`, default `false`) makes every `Connection` of the
  process reuse one PDO handle per DSN and user, while each instance keeps its own
  statement and execution state. `SwiftFuse\Database\Transaction::run()` commits
  the writes of several models together, rolls them back and rethrows on any
  `Throwable`, supports nesting (only the outermost level commits or rolls back),
  and throws a `LogicException` when the connection is not shared. On a shared
  handle, `beginTransaction()`, `commit()` and `rollBack()` nest too, so models
  that manage their own transaction join the outer one. New helpers:
  `Connection::isSharingEnabled()` and `Connection::flushSharedConnections()`.
  See [docs/DATABASE.md](docs/DATABASE.md#transactions-across-models).
- **Complete lifecycle on JSON responses.** `APP_JSON_LIFECYCLE`
  (`app.json_lifecycle`, default `false`) makes `json()` run `after()` and the
  `controller.after` event exactly once before responding. It never runs twice,
  and never when `json()` answers from `before()`.
- **`controller.responding` filter.** `json()` passes its payload through it,
  together with the HTTP status, the controller, the dispatched action and its
  parameters, and sends what the listeners return.
- **Dispatch context.** The router tells the controller which action and
  parameters it dispatches, through the internal, final methods
  `Controller::enterAction()` and `Controller::leaveAction()`.
- **Hook priorities and filters.** `Hooks::on()` accepts a `$priority` (higher
  first; equal priorities keep registration order) and `Hooks::filter()` passes a
  value through the listeners of an event. `Hooks::fire()` keeps its veto
  semantics.
- **Controllers resolved through the container.** With a bootstrapped
  application, the router creates controllers with `Application::make()`, so a
  binding can decorate or replace them; without one it still uses `new`.
  `Application::hasInstance()` reports whether an application exists.
- **PSR-4 roots from `composer.json` without Composer.** When
  `vendor/autoload.php` is absent, `bootstrap/autoload.php` also registers the
  `autoload.psr-4` roots of `composer.json` whose directories are inside the
  project, such as `"Extensions\\": "extensions/"`. Other entries, and an invalid
  manifest, are skipped with an `E_USER_WARNING`.
- **Test suite.** Dependency-free test scripts in `tests/`, run with
  `php tests/run.php`. The database tests use the `DB_*` settings and are skipped
  when no database is reachable.

### Changed

- **`Extensible` keeps one registry per class.** An extension is available on the
  class it was registered on and on its subclasses, but no longer on parent or
  sibling classes. Before, every subclass of a class using the trait wrote to one
  shared array, so `PeopleController::extend('x')` also exposed `x` on
  `Controller` and on every other controller. Extensions registered on
  `Controller`, `Router` or any base class behave as before. (Unrelated classes
  that use the trait, such as `Router` and `Controller`, already had separate
  registries.)
- Convention routing never dispatches `enterAction` or `leaveAction`; such a URL
  segment falls back to `index()`, as it did before these methods existed.
- The root `.htaccess` also blocks direct web access to `tests/`.

### Fixed

- Static closures registered with `extend()`, like the `redirect` example in
  `app/bootstrap.php`, can now be called on instances. Binding them to `$this`
  failed with "Value of type null is not callable".

### Upgrading from 0.9.9

No change is required. To adopt the new capabilities:

1. If your project keeps its own `config/database.php` and `config/app.php`, add
   `'shared_connection' => (bool) env('DB_SHARED_CONNECTION', false)` and
   `'json_lifecycle' => (bool) env('APP_JSON_LIFECYCLE', false)` respectively;
   the environment variables have no effect without these keys.
2. Set `DB_SHARED_CONNECTION=true` and wrap multi-model writes in
   `Transaction::run()`. Inside the transaction, models that catch
   `PDOException` to degrade gracefully should rethrow it, and responses should be
   sent after `run()` returns.
3. Set `APP_JSON_LIFECYCLE=true` if `after()` hooks or `controller.after`
   listeners must also run for JSON responses, and drop any workaround that ran
   that logic before `json()`, or it will run twice.
4. Move payload decorations applied before calling `json()` into a
   `controller.responding` listener.
5. Declare extra namespaces in `composer.json` and remove hand-written
   autoloaders for them.

Two edge cases change:

- `Controller` gained the final methods `enterAction()` and `leaveAction()`; a
  controller that declares methods with those names must rename them.
- An extension registered on a subclass is no longer callable on its parent or
  sibling classes; register it on their common base class instead.
