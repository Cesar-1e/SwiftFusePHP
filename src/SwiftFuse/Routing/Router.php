<?php

declare(strict_types=1);

namespace SwiftFuse\Routing;

use SwiftFuse\Http\Controller;
use SwiftFuse\Http\HttpException;
use SwiftFuse\Http\Request;
use SwiftFuse\Support\Extensible;

/**
 * HTTP router.
 *
 * Resolves a request to a controller action using two strategies:
 *   1. Explicit routes registered in routes/web.php (highest priority).
 *   2. Convention-based resolution: "/{controller}/{method}/{params...}" maps to
 *      App\Controllers\{Controller}Controller, falling back to a bridge that
 *      loads the legacy Controlador/ classes for backward compatibility.
 *
 * The Extensible trait allows adding routing helpers without editing the core.
 */
final class Router
{
    use Extensible;

    /**
     * Explicit routes, grouped by HTTP method.
     *
     * @var array<string, array<string, callable|array{0:class-string,1:string}>>
     */
    private array $routes = [];

    /**
     * Default controller short name used when none is provided.
     *
     * @var string
     */
    private string $defaultController = 'Home';

    /**
     * Register a GET route.
     *
     * @param string $uri URI pattern, e.g. "media/video/{id}".
     * @param callable|array{0:class-string,1:string} $handler Controller/method or closure.
     * @return void
     */
    public function get(string $uri, callable|array $handler): void
    {
        $this->addRoute('GET', $uri, $handler);
    }

    /**
     * Register a POST route.
     *
     * @param string $uri URI pattern.
     * @param callable|array{0:class-string,1:string} $handler Controller/method or closure.
     * @return void
     */
    public function post(string $uri, callable|array $handler): void
    {
        $this->addRoute('POST', $uri, $handler);
    }

    /**
     * Register a route that matches any HTTP method.
     *
     * @param string $uri URI pattern.
     * @param callable|array{0:class-string,1:string} $handler Controller/method or closure.
     * @return void
     */
    public function any(string $uri, callable|array $handler): void
    {
        $this->addRoute('ANY', $uri, $handler);
    }

    /**
     * Dispatch a request to the matching route or convention-based controller.
     *
     * @param Request $request The incoming request.
     * @return void
     *
     * @throws HttpException When no controller/action can be resolved.
     */
    public function dispatch(Request $request): void
    {
        if ($this->dispatchExplicit($request)) {
            return;
        }

        $this->dispatchByConvention($request);
    }

    /**
     * Register an explicit route under the given HTTP method.
     *
     * @param string $method HTTP method or "ANY".
     * @param string $uri URI pattern.
     * @param callable|array{0:class-string,1:string} $handler Route handler.
     * @return void
     */
    private function addRoute(string $method, string $uri, callable|array $handler): void
    {
        $this->routes[$method][trim($uri, '/')] = $handler;
    }

    /**
     * Attempt to dispatch an explicitly registered route.
     *
     * @param Request $request The incoming request.
     * @return bool True when a route matched and was dispatched.
     */
    private function dispatchExplicit(Request $request): bool
    {
        $path = implode('/', $request->segments());
        $candidates = array_merge($this->routes[$request->method()] ?? [], $this->routes['ANY'] ?? []);

        foreach ($candidates as $pattern => $handler) {
            $params = $this->matchPattern($pattern, $path);
            if ($params === null) {
                continue;
            }

            if (is_array($handler)) {
                [$class, $method] = $handler;
                $this->runModern(new $class(), $method, array_values($params));
            } else {
                $handler(...array_values($params));
            }

            return true;
        }

        return false;
    }

    /**
     * Resolve a controller and action by URL convention and dispatch it.
     *
     * @param Request $request The incoming request.
     * @return void
     *
     * @throws HttpException With status 404 when nothing resolves.
     */
    private function dispatchByConvention(Request $request): void
    {
        $segments = $request->segments();
        $name = ucfirst($segments[0] ?? $this->defaultController);
        array_shift($segments);

        $controller = $this->resolveController($name);
        if ($controller === null) {
            throw new HttpException(404, "Controller [{$name}] not found.");
        }

        if ($controller instanceof Controller) {
            $action = 'index';
            if (isset($segments[0]) && method_exists($controller, $segments[0])) {
                $action = array_shift($segments);
            }
            $this->runModern($controller, $action, array_values($segments));
            return;
        }

        $this->runLegacy($controller, $segments);
    }

    /**
     * Resolve a controller short name to an instance.
     *
     * Prefers App\Controllers\{Name}Controller, then bridges to the legacy
     * Controlador/{Name}_Controller.php class for backward compatibility.
     *
     * @param string $name Controller short name (PascalCase).
     * @return object|null The controller instance, or null when unresolved.
     */
    private function resolveController(string $name): ?object
    {
        $class = "App\\Controllers\\{$name}Controller";
        if (class_exists($class)) {
            return new $class();
        }

        return LegacyBridge::resolve($name);
    }

    /**
     * Invoke a modern controller action wrapped by its before/after hooks.
     *
     * Parameters are spread as individual arguments to the action.
     *
     * @param Controller $controller The controller instance.
     * @param string $action The method to call.
     * @param array<int, string> $params Parameters passed to the action.
     * @return void
     *
     * @throws HttpException With status 403 when a before-hook blocks the action.
     */
    private function runModern(Controller $controller, string $action, array $params): void
    {
        if ($controller->before($action, $params) === false) {
            throw new HttpException(403, 'Request blocked by controller hook.');
        }

        $controller->{$action}(...$params);
        $controller->after($action, $params);
    }

    /**
     * Invoke a legacy controller following the original calling convention.
     *
     * Legacy controllers default to the "cargaVista" action and receive the
     * view name and the parameter array as two arguments, mirroring the
     * behaviour of the deprecated Core router.
     *
     * @param object $controller The legacy controller instance.
     * @param array<int, string> $segments Remaining route segments after the controller.
     * @return void
     *
     * @throws HttpException With status 403 when a beforeAction hook blocks the request.
     */
    private function runLegacy(object $controller, array $segments): void
    {
        $method = 'cargaVista';
        $view = 'index';

        if (isset($segments[0])) {
            if (method_exists($controller, $segments[0])) {
                $method = array_shift($segments);
            } else {
                $view = array_shift($segments);
            }
        }

        $params = array_values($segments);

        if (method_exists($controller, 'beforeAction')
            && $controller->beforeAction($method, $params) === false) {
            throw new HttpException(403, 'Request blocked by controller hook.');
        }

        $controller->{$method}($view, $params);

        if (method_exists($controller, 'afterAction')) {
            $controller->afterAction($method, $params);
        }
    }

    /**
     * Match a route pattern against a path, extracting {placeholder} values.
     *
     * @param string $pattern Route pattern, e.g. "media/video/{id}".
     * @param string $path Concrete request path.
     * @return array<int|string, string>|null Captured parameters, or null on mismatch.
     */
    private function matchPattern(string $pattern, string $path): ?array
    {
        // Replace each {placeholder} with a capture group and quote literal parts.
        $regex = preg_replace_callback(
            '#\{[^/]+\}|[^{}]+#',
            static fn (array $m): string => str_starts_with($m[0], '{') ? '([^/]+)' : preg_quote($m[0], '#'),
            $pattern
        );

        if (preg_match('#^' . $regex . '$#', $path, $matches) !== 1) {
            return null;
        }

        array_shift($matches);
        return $matches;
    }
}
