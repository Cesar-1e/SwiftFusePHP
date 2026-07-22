<?php

/**
 * Storage configuration.
 *
 * Consumed by SwiftFuse\Storage\StorageManager. The storage root lives OUTSIDE
 * the public web root, so files are private by default and only delivered after
 * an authorization check.
 */

declare(strict_types=1);

return [
    // Absolute path to the private storage root for application files.
    'root' => storage_path('app'),

    // Delivery acceleration: "none" (PHP chunked stream with Range support),
    // "apache" (X-Sendfile via mod_xsendfile), or "nginx" (X-Accel-Redirect).
    'accel' => env('STORAGE_ACCEL', 'none'),

    // For the "nginx" mode: internal location that maps to the storage root.
    'nginx_internal' => env('STORAGE_NGINX_INTERNAL', '/protected/'),
];
