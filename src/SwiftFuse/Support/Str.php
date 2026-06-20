<?php

declare(strict_types=1);

namespace SwiftFuse\Support;

/**
 * String utility helpers.
 *
 * English home for assorted legacy string helpers previously declared as global
 * functions in Config/configurar.php.
 */
final class Str
{
    /**
     * Quote and escape a value for safe inline SQL usage, or return the literal
     * keyword "null" when the value is empty/null.
     *
     * Prefer prepared statements (SwiftFuse\Database\Connection::bind); this is
     * kept for legacy query-building scenarios.
     *
     * @param string|null $value The value to quote.
     * @return string The quoted value or the literal "null".
     */
    public static function quote(?string $value): string
    {
        if ($value === null || $value === 'null') {
            return 'null';
        }

        return "'" . addslashes($value) . "'";
    }

    /**
     * Convert a null value to zero, leaving everything else untouched.
     *
     * @param mixed $value The value to normalize.
     * @return mixed The original value, or 0 when it was null.
     */
    public static function nullToZero(mixed $value): mixed
    {
        return $value ?? 0;
    }

    /**
     * Generate a cryptographically secure random hexadecimal token.
     *
     * @param int $bytes Number of random bytes (token length is double this).
     * @return string The hexadecimal token.
     */
    public static function random(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
