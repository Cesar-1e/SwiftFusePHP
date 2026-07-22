<?php

/**
 * Application bootstrap.
 *
 * Wires the framework together and returns a ready-to-run Application instance.
 * Used by both the web front controller (public/index.php) and the CLI (fuse).
 *
 * @return \SwiftFuse\Foundation\Application
 */

declare(strict_types=1);

use SwiftFuse\Foundation\Application;
use SwiftFuse\Foundation\ErrorHandler;
use SwiftFuse\Routing\Router;
use SwiftFuse\Support\Config;
use SwiftFuse\Support\Env;

// 1. Register the PSR-4 autoloader (defines BASE_PATH).
require __DIR__ . '/autoload.php';

// 2. Load global helper functions (they cannot be autoloaded).
require BASE_PATH . '/src/SwiftFuse/Support/helpers.php';

// 3. Load environment variables and configuration files.
Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

// 4. Apply global runtime settings.
date_default_timezone_set((string) config('app.timezone', 'UTC'));

// 5. Register the error/exception handler.
$errorHandler = new ErrorHandler(
    (bool) config('app.debug', false),
    storage_path('logs/error.log')
);
$errorHandler->register();

// 6. Create the application container and register core services.
$app = new Application($errorHandler);
$app->registerServices(require BASE_PATH . '/config/services.php');

// 7. Register explicit routes (optional).
if (is_file(BASE_PATH . '/routes/web.php')) {
    /** @var Router $router */
    $router = $app->make(Router::class);
    require BASE_PATH . '/routes/web.php';
}

// 8. Let the developer register macros, hooks and bindings without touching core.
if (is_file(BASE_PATH . '/app/bootstrap.php')) {
    require BASE_PATH . '/app/bootstrap.php';
}

return $app;
