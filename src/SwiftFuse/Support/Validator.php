<?php

declare(strict_types=1);

namespace SwiftFuse\Support;

/**
 * Input validation and sanitization helpers.
 *
 * English home for the legacy filterINPUT() and isEmail() global functions.
 */
final class Validator
{
    /**
     * Sanitize a scalar input: trim it, treat empty/"null" as null, and reject
     * values that contain HTML tag markers or statement separators.
     *
     * @param string|null $value The raw input value.
     * @return string|null The trimmed value, or null when empty/"null".
     *
     * @throws \InvalidArgumentException When the value contains forbidden characters.
     */
    public static function clean(?string $value): ?string
    {
        if ($value === null || $value === '' || mb_strtolower($value) === 'null') {
            return null;
        }

        if (preg_match('/^<|.<|>$|;/', $value) >= 1) {
            throw new \InvalidArgumentException('Input contains forbidden characters.');
        }

        return trim($value);
    }

    /**
     * Determine whether a value is a well-formed email address.
     *
     * @param string|null $value The value to validate.
     * @return bool True when the value is a valid email address.
     */
    public static function isEmail(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
}
