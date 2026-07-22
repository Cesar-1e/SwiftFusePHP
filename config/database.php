<?php

/**
 * Database configuration (PDO).
 *
 * Consumed by SwiftFuse\Database\Connection. The framework keeps using PDO, as
 * required, and these values replace the legacy HOST/USERNAME/... constants.
 */

declare(strict_types=1);

return [
    'driver'     => env('DB_DRIVER', 'mysql'),
    'host'       => env('DB_HOST', 'localhost'),
    'port'       => env('DB_PORT', '3306'),
    'database'   => env('DB_DATABASE', 'swiftfusephp_test'),
    'username'   => env('DB_USERNAME', 'root'),
    'password'   => env('DB_PASSWORD', ''),
    'charset'    => env('DB_CHARSET', 'utf8mb4'),
    'persistent' => (bool) env('DB_PERSISTENT', true),
    'ssl_ca'     => env('DB_SSL_CA', ''),
];
