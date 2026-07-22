<?php

/**
 * Developer helper functions.
 *
 * Define your own global helpers here. This file is loaded during bootstrap, so
 * the functions are available everywhere. Guard each function with
 * function_exists() to stay safe if a name collides with a future core helper.
 */

declare(strict_types=1);

if (!function_exists('asset')) {
    /**
     * Build a URL to a public asset (file under /public).
     *
     * @param string $path Asset path relative to the public directory.
     * @return string
     */
    function asset(string $path): string
    {
        return base_url(ltrim($path, '/'));
    }
}
