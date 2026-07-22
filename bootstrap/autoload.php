<?php

/**
 * SwiftFusePHP PSR-4 autoloader.
 *
 * This is a self-contained PSR-4 autoloader so the framework works on any host
 * without requiring "composer install". When a Composer autoloader is present
 * (vendor/autoload.php) it is loaded first, so third-party libraries integrate
 * seamlessly alongside the framework's own classes.
 *
 * Namespace map:
 *   - SwiftFuse\  => src/SwiftFuse/   (framework core, not meant to be edited)
 *   - App\        => app/             (developer space: overrides and custom code)
 */

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    /**
     * Absolute path to the project root (the directory above /public).
     *
     * @var string
     */
    define('BASE_PATH', dirname(__DIR__));
}

/**
 * PSR-4 namespace prefixes mapped to their base directories.
 *
 * @var array<string, string>
 */
$swiftfusePsr4 = [
    'SwiftFuse\\' => BASE_PATH . '/src/SwiftFuse/',
    'App\\'       => BASE_PATH . '/app/',
];

/**
 * Register the PSR-4 autoloader for SwiftFusePHP namespaces.
 *
 * @param string $class Fully qualified class name requested by PHP.
 * @return void
 */
spl_autoload_register(static function (string $class) use ($swiftfusePsr4): void {
    foreach ($swiftfusePsr4 as $prefix => $baseDir) {
        $length = strlen($prefix);
        if (strncmp($class, $prefix, $length) !== 0) {
            continue;
        }

        $relativeClass = substr($class, $length);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_file($file)) {
            require_once $file;
        }

        return;
    }
});

// Integrate Composer-managed dependencies when available.
$composerAutoload = BASE_PATH . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}
