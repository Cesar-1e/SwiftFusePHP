<?php

declare(strict_types=1);

namespace SwiftFuse\Support;

/**
 * Environment variable loader and accessor.
 *
 * Reads a ".env" file (if present) into PHP's environment and exposes a typed
 * accessor. Values "true", "false" and "null" are cast to their native types,
 * and surrounding quotes are stripped. This is a lightweight loader tailored to
 * SwiftFusePHP; it intentionally avoids external dependencies.
 *
 * Replaces the legacy procedural loader in Config/env.php.
 */
final class Env
{
    /**
     * Whether the environment file has already been loaded.
     *
     * @var bool
     */
    private static bool $loaded = false;

    /**
     * Load environment variables from a ".env" file into the process.
     *
     * Existing environment variables are never overwritten, so real server
     * variables always take precedence over the file.
     *
     * @param string $path Absolute path to the .env file.
     * @return void
     */
    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;

        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            if ($key === '') {
                continue;
            }

            $value = self::normalizeRawValue(trim($value));

            if (getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }

    /**
     * Retrieve an environment variable with native type casting.
     *
     * @param string $key Variable name.
     * @param mixed $default Value returned when the variable is not defined.
     * @return mixed The casted value, or the default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }

        return match (strtolower($value)) {
            'true'        => true,
            'false'       => false,
            'null', ''    => $default,
            default       => $value,
        };
    }

    /**
     * Strip wrapping quotes from a raw .env value.
     *
     * @param string $value Raw value as read from the file.
     * @return string The unquoted value.
     */
    private static function normalizeRawValue(string $value): string
    {
        $isQuoted = (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"));

        return $isQuoted ? substr($value, 1, -1) : $value;
    }
}
