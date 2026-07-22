<?php

/**
 * Application configuration.
 *
 * Values are read from the environment (.env) with sensible defaults, replacing
 * the constants previously defined in Config/configurar.user.php.
 */

declare(strict_types=1);

return [
    // Application identity.
    'name'     => env('APP_NAME', 'SwiftFusePHP'),
    'env'      => env('APP_ENV', 'production'),
    'debug'    => (bool) env('APP_DEBUG', false),
    'version'  => env('APP_VERSION', '0.9.9'),

    // Base URL used to build absolute links and signed URLs.
    'url'      => env('APP_URL', 'http://localhost'),

    // Localization.
    'locale'   => env('APP_LOCALE', 'en'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),

    // Application signing key (used by signed URLs). Generate with: php fuse key:generate
    'key'      => env('APP_KEY', ''),

    // Third-party integrations.
    'recaptcha_key' => env('RECAPTCHA_KEY', ''),
];
