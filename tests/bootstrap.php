<?php

/**
 * Bootstrap shared by the framework test scripts.
 *
 * Registers the framework autoloader and global helpers without handling a
 * request, and maps the SwiftFuse\Tests\ namespace (support classes and
 * fixtures) to this directory.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';
require_once BASE_PATH . '/src/SwiftFuse/Support/helpers.php';

/**
 * Autoload the test support classes and fixtures (SwiftFuse\Tests\ => tests/).
 *
 * @param string $class Fully qualified class name requested by PHP.
 * @return void
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'SwiftFuse\\Tests\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
