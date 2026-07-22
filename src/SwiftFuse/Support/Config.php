<?php

declare(strict_types=1);

namespace SwiftFuse\Support;

/**
 * Configuration repository.
 *
 * Loads every PHP file inside the /config directory (each returning an array)
 * and exposes the merged tree through dot-notation keys, e.g. "database.host".
 * This centralizes the constants that previously lived in
 * Config/configurar.user.php.
 */
final class Config
{
    /**
     * The loaded configuration tree, keyed by file name.
     *
     * @var array<string, mixed>
     */
    private static array $items = [];

    /**
     * Load every configuration file from the given directory.
     *
     * @param string $directory Absolute path to the config directory.
     * @return void
     */
    public static function load(string $directory): void
    {
        foreach (glob(rtrim($directory, '/') . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            $values = require $file;
            self::$items[$name] = is_array($values) ? $values : [];
        }
    }

    /**
     * Get a configuration value using dot notation.
     *
     * @param string $key Dot-notation key, e.g. "app.name".
     * @param mixed $default Value returned when the key is missing.
     * @return mixed The configuration value, or the default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Set or override a configuration value at runtime.
     *
     * @param string $key Dot-notation key.
     * @param mixed $value Value to assign.
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $ref = &self::$items;

        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $ref[$segment] = $value;
                return;
            }
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
    }

    /**
     * Get the entire configuration tree (mainly for debugging).
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return self::$items;
    }
}
