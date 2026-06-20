<?php

declare(strict_types=1);

namespace SwiftFuse\Routing;

/**
 * Backward-compatibility bridge to the legacy (Spanish) controller stack.
 *
 * The legacy core under Controlador/, Modelo/ and Libreria/ is @deprecated but
 * cannot be removed yet. When a request does not match an App\Controllers class,
 * the router asks this bridge to load the equivalent legacy controller on demand.
 *
 * The legacy bootstrap is loaded lazily and only once, intentionally skipping
 * Config/env.php (its global env() would clash with the new helper) since the
 * new framework already provides environment loading.
 *
 * @deprecated 0.9.9 This bridge exists only for backward compatibility and will
 *             be removed once all legacy controllers are migrated to App\Controllers.
 */
final class LegacyBridge
{
    /**
     * Whether the legacy bootstrap has already been loaded.
     *
     * @var bool
     */
    private static bool $booted = false;

    /**
     * Resolve a legacy controller instance by its short name.
     *
     * @param string $name Controller short name, e.g. "Person".
     * @return object|null The legacy "{Name}Control" instance, or null when absent.
     */
    public static function resolve(string $name): ?object
    {
        $file = base_path("Controlador/{$name}_Controller.php");
        if (!is_file($file)) {
            return null;
        }

        self::boot();
        require_once $file;

        $class = "{$name}Control";
        return class_exists($class) ? new $class() : null;
    }

    /**
     * Load the minimal legacy runtime (constants, helpers, base classes) once.
     *
     * @return void
     */
    private static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        if (!defined('RUTA_APP')) {
            define('RUTA_APP', BASE_PATH . '/');
        }

        // Legacy code resolves views and includes with paths relative to the
        // project root (e.g. file_exists("Vista/...")). The new front controller
        // runs from /public, so restore the original working directory to keep
        // those relative lookups working.
        chdir(BASE_PATH);

        $legacyFiles = [
            'Errores/log.php',
            'Config/configurar.php',
            'Config/configurar.user.php',
            'Modelo/Conexion_Model.php',
            'Libreria/Controlador.php',
        ];

        foreach ($legacyFiles as $relative) {
            $path = base_path($relative);
            if (is_file($path)) {
                require_once $path;
            }
        }
    }
}
