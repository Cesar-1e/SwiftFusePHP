<?php

declare(strict_types=1);

namespace SwiftFuse\Support;

use SwiftFuse\Http\HttpException;

/**
 * View renderer.
 *
 * Resolves and renders PHP/HTML templates stored in resources/views. View
 * names use dot or slash notation, e.g. "home.index" maps to
 * resources/views/home/index.php. This is the English replacement for the
 * legacy Controlador::vista() resolver.
 */
final class View
{
    /**
     * Render a view template, exposing the given data as local variables.
     *
     * @param string $name View name in dot/slash notation, e.g. "home.index".
     * @param array<string, mixed> $data Variables made available to the template.
     * @return void
     *
     * @throws HttpException With status 404 when the template cannot be found.
     */
    public static function render(string $name, array $data = []): void
    {
        $file = self::resolve($name);
        if ($file === null) {
            throw new HttpException(404, "View [{$name}] not found.");
        }

        // Expose each data key as a local variable inside the template scope.
        extract($data, EXTR_SKIP);
        require $file;
    }

    /**
     * Resolve a view name to an existing template file path.
     *
     * Both ".php" and ".html" extensions are supported, with ".php" preferred.
     *
     * @param string $name View name in dot/slash notation.
     * @return string|null Absolute file path, or null when not found.
     */
    public static function resolve(string $name): ?string
    {
        $relative = str_replace(['.', '\\'], '/', trim($name, '/'));
        $base = base_path('resources/views/' . $relative);

        foreach (['.php', '.html'] as $extension) {
            $candidate = $base . $extension;
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
