<?php

/**
 * SwiftFusePHP global helper functions.
 *
 * Functions cannot be autoloaded, so this file is required once during
 * bootstrap. These helpers are the convenient, productivity-oriented entry
 * points to the underlying support classes.
 */

declare(strict_types=1);

use SwiftFuse\Foundation\Application;
use SwiftFuse\Support\Config;
use SwiftFuse\Support\Env;
use SwiftFuse\Support\View;

if (!function_exists('env')) {
    /**
     * Read an environment variable with native type casting.
     *
     * @param string $key Variable name.
     * @param mixed $default Fallback when the variable is undefined.
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('config')) {
    /**
     * Read a configuration value using dot notation, e.g. config('app.name').
     *
     * @param string $key Dot-notation key.
     * @param mixed $default Fallback when the key is missing.
     * @return mixed
     */
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('base_path')) {
    /**
     * Build an absolute path from the project root.
     *
     * @param string $path Optional sub-path to append.
     * @return string
     */
    function base_path(string $path = ''): string
    {
        return BASE_PATH . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('app_path')) {
    /**
     * Build an absolute path inside the developer "app" directory.
     *
     * @param string $path Optional sub-path to append.
     * @return string
     */
    function app_path(string $path = ''): string
    {
        return base_path('app' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
    }
}

if (!function_exists('config_path')) {
    /**
     * Build an absolute path inside the config directory.
     *
     * @param string $path Optional sub-path to append.
     * @return string
     */
    function config_path(string $path = ''): string
    {
        return base_path('config' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
    }
}

if (!function_exists('storage_path')) {
    /**
     * Build an absolute path inside the private storage directory.
     *
     * @param string $path Optional sub-path to append.
     * @return string
     */
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
    }
}

if (!function_exists('public_path')) {
    /**
     * Build an absolute path inside the public web root.
     *
     * @param string $path Optional sub-path to append.
     * @return string
     */
    function public_path(string $path = ''): string
    {
        return base_path('public' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
    }
}

if (!function_exists('app')) {
    /**
     * Resolve a service from the container, or get the Application instance.
     *
     * @param string|null $abstract Service identifier to resolve, or null for the app.
     * @return mixed
     */
    function app(?string $abstract = null): mixed
    {
        $application = Application::getInstance();
        return $abstract === null ? $application : $application->make($abstract);
    }
}

if (!function_exists('view')) {
    /**
     * Render a view template from resources/views.
     *
     * @param string $name View name using dot or slash notation, e.g. "home.index".
     * @param array<string, mixed> $data Variables exposed to the template.
     * @return void
     */
    function view(string $name, array $data = []): void
    {
        View::render($name, $data);
    }
}

if (!function_exists('base_url')) {
    /**
     * Build a fully-qualified URL for the application.
     *
     * @param string $path Optional path to append to the base URL.
     * @return string
     */
    function base_url(string $path = ''): string
    {
        $base = rtrim((string) config('app.url', ''), '/');
        return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}
