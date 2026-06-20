<?php

declare(strict_types=1);

namespace SwiftFuse\Support;

use DateTime;
use DateTimeZone;
use IntlDateFormatter;

/**
 * Locale-aware formatting helpers.
 *
 * English, internationalized replacement for the Spanish-only legacy helpers
 * formatCurrency(), getDateInSpanish() and getMonthInSpanish(). The locale
 * defaults to config('app.locale').
 */
final class Format
{
    /**
     * Format an amount as a human-readable currency string.
     *
     * @param int|float $amount The numeric amount.
     * @param string $symbol Currency symbol to prepend.
     * @param int $decimals Number of decimal places.
     * @return string The formatted currency string.
     */
    public static function currency(int|float $amount, string $symbol = '$', int $decimals = 0): string
    {
        return $symbol . ' ' . number_format((float) $amount, $decimals, ',', '.');
    }

    /**
     * Format a date as a long, localized string (e.g. "20 de junio de 2026").
     *
     * @param string $date A date string accepted by DateTime, or "now".
     * @param string|null $locale ICU locale; defaults to config('app.locale').
     * @return string The formatted date.
     */
    public static function date(string $date = 'now', ?string $locale = null): string
    {
        $locale ??= (string) config('app.locale', 'en');
        $dateTime = new DateTime($date, new DateTimeZone(date_default_timezone_get()));

        return (string) IntlDateFormatter::formatObject($dateTime, "d 'de' MMMM 'de' y", $locale);
    }

    /**
     * Get the localized name of a month by its 1-based number.
     *
     * @param int $month Month number from 1 (January) to 12 (December).
     * @param string|null $locale ICU locale; defaults to config('app.locale').
     * @return string The localized month name.
     */
    public static function monthName(int $month, ?string $locale = null): string
    {
        $locale ??= (string) config('app.locale', 'en');
        $month = max(1, min(12, $month));
        $dateTime = new DateTime(sprintf('2000-%02d-01', $month));

        return (string) IntlDateFormatter::formatObject($dateTime, 'MMMM', $locale);
    }
}
