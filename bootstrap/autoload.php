<?php

/**
 * SwiftFusePHP PSR-4 autoloader.
 *
 * This is a self-contained PSR-4 autoloader so the framework works on any host
 * without requiring "composer install". When a Composer autoloader is present
 * (vendor/autoload.php) it is loaded too, so third-party libraries integrate
 * seamlessly alongside the framework's own classes.
 *
 * Namespace map:
 *   - SwiftFuse\  => src/SwiftFuse/   (framework core, not meant to be edited)
 *   - App\        => app/             (developer space: overrides and custom code)
 *   - every "autoload.psr-4" root of composer.json whose directory is inside the
 *     project (e.g. "Extensions\\": "extensions/"), read only when
 *     vendor/autoload.php is absent, since Composer covers them otherwise.
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
 * PSR-4 namespace prefixes mapped to the base directories searched for them, in order.
 *
 * @var array<string, array<int, string>>
 */
$swiftfusePsr4 = [
    'SwiftFuse\\' => [BASE_PATH . '/src/SwiftFuse/'],
    'App\\'       => [BASE_PATH . '/app/'],
];

$composerAutoload = BASE_PATH . '/vendor/autoload.php';

if (!is_file($composerAutoload)) {
    /**
     * Extend the namespace map with the "autoload.psr-4" roots of composer.json.
     *
     * A root is accepted only when its directory resolves inside the project;
     * absolute paths elsewhere, ".." escapes and stream wrappers are skipped. A
     * skipped root, or an unreadable manifest, raises an E_USER_WARNING instead
     * of stopping the application, because earlier versions ignored the file.
     *
     * @param array<string, array<int, string>> $psr4 The built-in namespace map.
     * @return array<string, array<int, string>> The map including the accepted composer.json roots.
     */
    $swiftfusePsr4 = (static function (array $psr4): array {
        $manifest = BASE_PATH . '/composer.json';
        if (!is_file($manifest)) {
            return $psr4;
        }

        $contents = is_readable($manifest) ? file_get_contents($manifest) : false;
        $composer = is_string($contents) ? json_decode($contents, true) : null;
        $declared = is_array($composer) ? ($composer['autoload']['psr-4'] ?? []) : null;
        if (!is_array($declared)) {
            trigger_error(
                'Ignoring the PSR-4 roots of composer.json: it is not a readable, valid JSON manifest.',
                E_USER_WARNING
            );
            return $psr4;
        }

        /**
         * Normalize an absolute path lexically: forward slashes, no "." or ".." segments.
         *
         * The check is lexical on purpose, so roots that do not exist yet are
         * accepted and symlinked folders inside the project keep working.
         *
         * @param string $path Absolute path.
         * @return string|null The normalized path, or null when ".." climbs above its root.
         */
        $normalize = static function (string $path): ?string {
            $path = str_replace('\\', '/', $path);
            $segments = [];

            foreach (explode('/', $path) as $segment) {
                if ($segment === '' || $segment === '.') {
                    continue;
                }

                if ($segment !== '..') {
                    $segments[] = $segment;
                } elseif (array_pop($segments) === null) {
                    return null;
                }
            }

            $normalized = (str_starts_with($path, '/') ? '/' : '') . implode('/', $segments);

            // Windows drive letters are case-insensitive: compare them in one case.
            return preg_match('#^[a-z]:#i', $normalized) === 1 ? ucfirst($normalized) : $normalized;
        };

        $root = (string) $normalize(BASE_PATH);

        foreach ($declared as $prefix => $paths) {
            $prefix = (string) $prefix;
            if ($prefix !== '' && !str_ends_with($prefix, '\\')) {
                trigger_error(
                    sprintf('Ignoring PSR-4 prefix "%s" of composer.json: it must end with "\\".', $prefix),
                    E_USER_WARNING
                );
                continue;
            }

            foreach (is_array($paths) ? $paths : [$paths] as $path) {
                $isSafeString = is_string($path) && !str_contains($path, "\0") && !str_contains($path, '://');
                $isAbsolute = $isSafeString && preg_match('#^([a-z]:)?[/\\\\]#i', $path) === 1;
                $directory = $isSafeString ? $normalize($isAbsolute ? $path : BASE_PATH . '/' . $path) : null;

                if ($directory === null || ($directory !== $root && !str_starts_with($directory, $root . '/'))) {
                    trigger_error(
                        sprintf(
                            'Ignoring PSR-4 path %s of prefix "%s" in composer.json: it is not inside the project.',
                            is_string($path) ? '"' . $path . '"' : get_debug_type($path),
                            $prefix
                        ),
                        E_USER_WARNING
                    );
                    continue;
                }

                $known = array_map(
                    static fn (string $existing): string => (string) $normalize($existing),
                    $psr4[$prefix] ?? []
                );
                if (!in_array($directory, $known, true)) {
                    $psr4[$prefix][] = $directory . '/';
                }
            }
        }

        return $psr4;
    })($swiftfusePsr4);
}

// Search longer prefixes first, so a nested namespace root wins over a broader one.
uksort($swiftfusePsr4, static fn (string $first, string $second): int => strlen($second) <=> strlen($first));

/**
 * Register the PSR-4 autoloader for the mapped namespaces.
 *
 * @param string $class Fully qualified class name requested by PHP.
 * @return void
 */
spl_autoload_register(static function (string $class) use ($swiftfusePsr4): void {
    foreach ($swiftfusePsr4 as $prefix => $baseDirectories) {
        $length = strlen($prefix);
        if (strncmp($class, $prefix, $length) !== 0) {
            continue;
        }

        $relativePath = str_replace('\\', '/', substr($class, $length)) . '.php';
        foreach ($baseDirectories as $baseDirectory) {
            if (is_file($baseDirectory . $relativePath)) {
                require_once $baseDirectory . $relativePath;
                return;
            }
        }
    }
});

// Integrate Composer-managed dependencies when available.
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}
