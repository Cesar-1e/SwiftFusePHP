<?php

declare(strict_types=1);

namespace SwiftFuse\Http;

/**
 * HTTP request abstraction.
 *
 * A thin, read-only wrapper around PHP superglobals that exposes the parsed
 * route segments and convenient input accessors. The route is taken from the
 * "url" query parameter produced by the public/.htaccess rewrite rules.
 */
final class Request
{
    /**
     * Decoded route segments, e.g. ["home", "show", "5"].
     *
     * @var array<int, string>
     */
    private array $segments;

    /**
     * @param array<int, string> $segments Pre-parsed route segments.
     */
    public function __construct(array $segments)
    {
        $this->segments = $segments;
    }

    /**
     * Build a Request from the current PHP superglobals.
     *
     * @return self
     */
    public static function capture(): self
    {
        // Prefer the "url" parameter set by the web server rewrite rules; fall
        // back to the request path so the built-in PHP server also works.
        $path = $_GET['url'] ?? parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
        $path = trim((string) $path, '/');
        $segments = $path === '' ? [] : explode('/', $path);

        return new self($segments);
    }

    /**
     * Get the decoded route segments.
     *
     * @return array<int, string>
     */
    public function segments(): array
    {
        return $this->segments;
    }

    /**
     * Get the HTTP request method (GET, POST, ...), uppercased.
     *
     * @return string
     */
    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Read an input value from POST then GET.
     *
     * @param string $key Input field name.
     * @param mixed $default Fallback when the field is absent.
     * @return mixed
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    /**
     * Determine whether the client expects a JSON response.
     *
     * @return bool
     */
    public function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

        return str_contains($accept, 'application/json')
            || strtolower($requestedWith) === 'xmlhttprequest';
    }
}
