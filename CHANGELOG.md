# Changelog

All notable changes to SwiftFusePHP are documented in this file. The project
follows [Semantic Versioning](https://semver.org/).

## [0.12.0] - 2026-10-05

Applications can add their own commands to `php fuse` without modifying the
framework, e.g. `php fuse reports:monthly` run monthly from cron. Backwards
compatible: a project without `config/console.php` keeps its CLI behavior.

### Added

- **Application commands.** `SwiftFuse\Contracts\CommandInterface`
  (`name()`, `description()`, `handle(array $arguments): int`) and the
  `console.commands` setting in the new `config/console.php`, a list of command
  classes. The console kernel runs a registered command when no built-in command
  has that name, passing the arguments after the name and returning its exit
  code. See [docs/CLI.md](docs/CLI.md#application-commands).
- Validation of the registry, with a message on STDERR and exit code 1: the
  class must exist, implement `CommandInterface` and be creatable without
  arguments, and its name must match `/^[a-z][a-z0-9-]*(:[a-z0-9-]+)*$/`,
  not be a built-in command and not be registered twice. An uncaught exception
  in `handle()` prints `Command <name> failed: <message>` and exits with 1.
- `php fuse list` shows an **Application commands** section, in alphabetical
  order, when commands are registered.
- `php fuse make:command <Name>` scaffolds `app/Console/<Name>.php` and reminds
  you to register it in `config/console.php`.
- A cron example for periodic commands in [docs/CLI.md](docs/CLI.md#running-a-command-periodically-cron),
  and a *Console commands* section in [docs/EXTENDING.md](docs/EXTENDING.md).
- Console tests in `tests/console-commands-test.php`.

### Changed

- Built-in commands keep priority and never load the command registry; their
  behavior is unchanged. `make:command` is the new built-in, listed by
  `php fuse list`.

### Upgrading from 0.11.0

No action is required. To add commands, copy `config/console.php` into the
project (it is optional; without it `console.commands` is empty) and list your
command classes under `commands`. Set `APP_VERSION=0.12.0` in `.env` if you track
it there.

## [0.11.0] - 2026-09-28

A job queue that is safe for long-running, non-idempotent jobs such as bulk
imports: every job runs exactly once, jobs interrupted by a dead worker are set
aside instead of retried, and a new `deferred` driver starts jobs right after
the response on shared hosting without `exec()`. With the default configuration
(`QUEUE_DRIVER=file`), applications built on 0.10.0 keep their behavior, now
with the reservation protecting them.

### Added

- **Exclusive job reservation.** Before running a job, `Worker` takes an
  exclusive, non-blocking `flock()` on its `.job` file and, holding it, renames it
  to `.job.running`; the lock is kept while `handle()` runs. Only one process
  runs a given job, however many crons, daemons, `queue:run` calls or deferred
  requests overlap. See [docs/QUEUE.md](docs/QUEUE.md#4-reservation-every-job-runs-once).
- **Interrupted jobs.** At the start of `Worker::work()`, a `.running` file whose
  lock is free (its worker died) is moved to `failed/` with the reason
  `Worker interrupted` and logged. It is not retried automatically.
- **`deferred` queue driver.** `QUEUE_DRIVER=deferred` writes the job like `file`
  and, under PHP-FPM or LiteSpeed, runs it after the response has been sent
  (`fastcgi_finish_request()` / `litespeed_finish_request()`), even when the
  controller ends with `exit`. Jobs of one request run in dispatch order after a
  single finish call; their failures go to `failed/` and the log, never to the
  response. Without a finish function, and on the CLI, it behaves like `file`.
- `QueueManager::logPath()`, `QueueManager::log()` and
  `QueueManager::execAvailable()`, an optional second constructor argument that
  replaces the `exec()` availability check, the `Worker::INTERRUPTED` constant,
  and the `queue.log` setting (default `storage/logs/queue.log`).
- Queue tests in `tests/queue-test.php`, with real concurrent processes and a
  deferred request served by PHP's built-in server. `PhpProcess` gained
  `start()`/`wait()` and php.ini settings for child processes.

### Changed

- `Worker::work()` counts only the jobs it ran; jobs reserved by another process
  are skipped silently.
- `Worker::runFile()` reserves the job first and returns `false` without moving
  it to `failed/` when another process holds it, or when given a `.running` file.
- `QueueManager::dispatch()` writes the payload to a temporary file and renames
  it into place, so a worker never reads a half-written job.
- `php fuse queue:run` only accepts files located directly in the queue
  directory (resolved with `realpath()`); other paths exit with code 1 and a
  message. See [docs/CLI.md](docs/CLI.md#queuerun-job-file).

### Fixed

- A job could run twice or more in parallel: the cron of the next minute, a
  daemon or the `async` process picked up a job that was still running, because
  its file was only removed at the end.
- With `QUEUE_DRIVER=async` and `exec()` in `disable_functions`, `dispatch()`
  threw an `Error` after saving the job. The driver now checks `exec()` first,
  falls back to `deferred`, and logs one warning per process.
- `php fuse queue:run` ran, and on failure moved into `failed/`, any file it was
  given, including files outside the queue directory.

### Upgrading from 0.10.0

No change is required: pending `.job` files written by 0.10.0 are processed as
before. Recommended:

1. On shared hosting without `exec()`, set `QUEUE_DRIVER=deferred`, and keep the
   `php fuse queue:work` cron every minute with every driver.
2. If your project keeps its own `config/queue.php`, optionally add
   `'log' => storage_path('logs/queue.log')`; the default is the same file.
3. Watch `storage/framework/jobs/failed/` for `Worker interrupted` entries, and
   check whether each job ran partly before requeuing it.

`queue:work` now reports only the jobs it ran, and `queue:run` refuses paths
outside the queue directory.

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
