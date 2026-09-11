<?php

declare(strict_types=1);

namespace SwiftFuse\Http;

use SwiftFuse\Database\Model;
use SwiftFuse\Support\Extensible;
use SwiftFuse\Support\Hooks;
use SwiftFuse\Support\View;

/**
 * Base controller for SwiftFusePHP (MVC).
 *
 * Application controllers in App\Controllers should extend this class. It
 * provides view rendering, model loading and JSON responses, plus before/after
 * lifecycle hooks. The router tells the controller which action it dispatches,
 * so JSON responses know that context too. The Extensible trait lets developers
 * attach extra methods at runtime without editing the framework. This is the
 * English successor to the legacy Controlador base class.
 */
abstract class Controller
{
    use Extensible;

    /**
     * View sub-folder used when resolving views for this controller.
     *
     * @var string
     */
    protected string $folder = '';

    /**
     * The action the router is dispatching, or null outside a router dispatch.
     *
     * @var string|null
     */
    private ?string $dispatchedAction = null;

    /**
     * Route parameters of the action the router is dispatching.
     *
     * @var array<int, string>
     */
    private array $dispatchedParams = [];

    /**
     * Whether the before-hook allowed the dispatched action to run.
     *
     * @var bool
     */
    private bool $actionStarted = false;

    /**
     * Whether the after-hook already ran for the dispatched action.
     *
     * @var bool
     */
    private bool $afterHookRan = false;

    /**
     * Default action: render a view inside the controller's folder.
     *
     * Maps e.g. "/home" to view "{folder}.index" and "/home/about" to
     * "{folder}.about", mirroring the legacy convention-based routing.
     *
     * @param string $view View name within the controller folder.
     * @param string ...$params Additional route parameters (unused by default).
     * @return void
     */
    public function index(string $view = 'index', string ...$params): void
    {
        $this->view($this->viewName($view));
    }

    /**
     * Render a view template with the given data.
     *
     * @param string $name View name in dot/slash notation.
     * @param array<string, mixed> $data Variables exposed to the template.
     * @return void
     */
    protected function view(string $name, array $data = []): void
    {
        View::render($name, $data);
    }

    /**
     * Send a JSON response and stop execution.
     *
     * Before encoding, the payload passes through the "controller.responding"
     * filter: listeners receive the payload, the HTTP status, this controller,
     * the dispatched action (null outside a router dispatch) and its route
     * parameters, and return the payload to send. Because this method ends the
     * request, it also runs the after-hook once when app.json_lifecycle is
     * enabled and the action is running; otherwise the after-hook is skipped, as
     * in earlier versions.
     *
     * @param mixed $data Payload to encode.
     * @param int $status HTTP status code.
     * @return never
     */
    protected function json(mixed $data, int $status = 200): never
    {
        $data = Hooks::filter(
            'controller.responding',
            $data,
            [$status, $this, $this->dispatchedAction, $this->dispatchedParams]
        );

        if ($this->actionStarted && (bool) config('app.json_lifecycle', false)) {
            $this->runAfterHook();
        }

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Load a model instance, preferring App\Models over framework models.
     *
     * @param string $name Model class short name (without namespace/suffix).
     * @return Model
     *
     * @throws HttpException With status 500 when the model class is missing.
     */
    protected function model(string $name): Model
    {
        foreach (["App\\Models\\{$name}", "SwiftFuse\\Database\\Models\\{$name}"] as $class) {
            if (class_exists($class)) {
                /** @var Model $instance */
                $instance = new $class();
                return $instance;
            }
        }

        throw new HttpException(500, "Model [{$name}] not found.");
    }

    /**
     * Lifecycle hook executed before the routed action.
     *
     * Return false to block the request (the framework responds with 403).
     * Also fires the global "controller.before" hook so developers can plug in
     * from app/bootstrap.php.
     *
     * @param string $action The action about to run.
     * @param array<int, string> $params Route parameters.
     * @return bool True to proceed, false to abort.
     */
    public function before(string $action, array $params): bool
    {
        return Hooks::fire('controller.before', [$action, $params, $this]);
    }

    /**
     * Lifecycle hook executed after the routed action.
     *
     * The router runs it once per dispatched action. When the action answers
     * with json(), it runs only if app.json_lifecycle is enabled. Also fires the
     * global "controller.after" hook.
     *
     * @param string $action The action that ran.
     * @param array<int, string> $params Route parameters.
     * @return void
     */
    public function after(string $action, array $params): void
    {
        Hooks::fire('controller.after', [$action, $params, $this]);
    }

    /**
     * Start dispatching an action: record it and run the before-hook.
     *
     * The router calls this right before invoking the action, so json() and the
     * after-hook know which action and parameters are running. It belongs to the
     * dispatch lifecycle and is never dispatched as an action by convention.
     *
     * @internal Called by SwiftFuse\Routing\Router.
     *
     * @param string $action The action about to run.
     * @param array<int, string> $params Route parameters passed to the action.
     * @return bool True when the before-hook allows the action, false to abort it.
     */
    final public function enterAction(string $action, array $params): bool
    {
        $this->dispatchedAction = $action;
        $this->dispatchedParams = $params;
        $this->actionStarted = false;
        $this->afterHookRan = false;

        $this->actionStarted = $this->before($action, $params);

        return $this->actionStarted;
    }

    /**
     * Finish dispatching an action: run the after-hook unless it already ran.
     *
     * @internal Called by SwiftFuse\Routing\Router once the action returns.
     *
     * @return void
     */
    final public function leaveAction(): void
    {
        $this->runAfterHook();
    }

    /**
     * Run the after-hook of the dispatched action at most once.
     *
     * The flag is raised before the hook runs, so a hook that itself responds
     * with json() cannot run it a second time.
     *
     * @return void
     */
    private function runAfterHook(): void
    {
        if ($this->afterHookRan || $this->dispatchedAction === null) {
            return;
        }

        $this->afterHookRan = true;
        $this->after($this->dispatchedAction, $this->dispatchedParams);
    }

    /**
     * Build a fully-qualified view name within this controller's folder.
     *
     * @param string $view View name relative to the folder.
     * @return string
     */
    private function viewName(string $view): string
    {
        return ($this->folder !== '' ? $this->folder . '.' : '') . $view;
    }
}
