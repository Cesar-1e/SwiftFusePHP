<?php

/**
 * Developer bootstrap.
 *
 * This file is loaded after the framework is wired but before a request is
 * handled. Use it to extend SwiftFusePHP WITHOUT editing any core file:
 *
 *   - register global helper functions (app/helpers.php),
 *   - add runtime methods to core classes via the Extensible trait,
 *   - listen to lifecycle hooks,
 *   - rebind core services to your own implementations.
 *
 * The $app (Application/container) variable is available here.
 *
 * @var \SwiftFuse\Foundation\Application $app
 */

declare(strict_types=1);

use SwiftFuse\Routing\Router;
use SwiftFuse\Support\Hooks;

// Load developer helper functions, if any.
if (is_file(__DIR__ . '/helpers.php')) {
    require_once __DIR__ . '/helpers.php';
}

// Example: add a router method at runtime without modifying the Router class.
Router::extend('redirect', static function (string $to): void {
    header('Location: ' . base_url($to));
    exit;
});

// Example: react to every controller action before it runs.
Hooks::on('controller.before', static function (string $action, array $params, object $controller): bool {
    // Return false here to block a request globally. Always allow by default.
    return true;
});
