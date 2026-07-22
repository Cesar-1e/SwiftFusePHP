<?php

declare(strict_types=1);

namespace SwiftFuse\Storage;

/**
 * Time-limited signed URL generator and validator.
 *
 * Produces tamper-proof, expiring URLs to protected storage resources using an
 * HMAC-SHA256 signature keyed with config('app.key'). A front-end can embed the
 * signed URL (e.g. in a <video src>) so authorized media is delivered without
 * exposing real file paths. This mechanism is inspired by, but not copied from,
 * mainstream frameworks — the format and implementation are original.
 */
final class SignedUrl
{
    /**
     * Build a signed, expiring URL for a protected storage resource.
     *
     * @param string $routePath Application route that serves the file, e.g. "media/file".
     * @param string $resource Storage-relative resource path, e.g. "video/intro.mp4".
     * @param int $ttl Lifetime in seconds before the URL expires.
     * @return string The fully-qualified signed URL.
     */
    public static function make(string $routePath, string $resource, int $ttl = 3600): string
    {
        $expires = time() + $ttl;
        $signature = self::sign($resource, $expires);

        $query = http_build_query([
            'resource' => $resource,
            'expires' => $expires,
            'signature' => $signature,
        ]);

        return base_url(trim($routePath, '/')) . '?' . $query;
    }

    /**
     * Validate a signed request: signature integrity plus expiry window.
     *
     * @param string $resource Storage-relative resource path.
     * @param int $expires Unix timestamp when the URL expires.
     * @param string $signature Signature provided in the request.
     * @return bool True when the signature is valid and not expired.
     */
    public static function isValid(string $resource, int $expires, string $signature): bool
    {
        if ($expires < time()) {
            return false;
        }

        $expected = self::sign($resource, $expires);

        return hash_equals($expected, $signature);
    }

    /**
     * Compute the HMAC signature for a resource and expiry.
     *
     * @param string $resource Storage-relative resource path.
     * @param int $expires Unix timestamp when the URL expires.
     * @return string Hexadecimal HMAC-SHA256 signature.
     */
    private static function sign(string $resource, int $expires): string
    {
        $key = (string) config('app.key', '');

        return hash_hmac('sha256', $resource . '|' . $expires, $key);
    }
}
