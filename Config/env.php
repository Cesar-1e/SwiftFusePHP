<?php

/**
 * Load environment variables from .env file if present.
 *
 * This is a minimal loader for PHP 8.4 and a lightweight framework.
 *
 * @deprecated 0.9.9 Replaced by SwiftFuse\Support\Env. This procedural loader is
 *             only used by the legacy entry point and is intentionally NOT loaded
 *             by the new bootstrap. Removed in 1.0.
 */
function loadEnvFile(string $path = null): void
{
    $filePath = $path ?? dirname(__FILE__) . '/../.env';
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return;
    }

    $contents = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($contents as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($key === '') {
            continue;
        }

        if ($value === 'null') {
            $value = null;
        } elseif ($value === 'true') {
            $value = true;
        } elseif ($value === 'false') {
            $value = false;
        } elseif (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = substr($value, 1, -1);
        } elseif (str_starts_with($value, "'") && str_ends_with($value, "'")) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

function env(string $key, $default = null)
{
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    if ($value === 'true') {
        return true;
    }
    if ($value === 'false') {
        return false;
    }
    if ($value === 'null') {
        return null;
    }
    return $value;
}

loadEnvFile();
