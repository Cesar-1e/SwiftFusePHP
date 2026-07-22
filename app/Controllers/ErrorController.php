<?php

declare(strict_types=1);

namespace App\Controllers;

use SwiftFuse\Http\Controller;

/**
 * Error controller.
 *
 * Renders error views for direct "/error/{code}" URLs and for the web server's
 * ErrorDocument fallbacks. Most errors are rendered automatically by the
 * framework's ErrorHandler; this controller covers the explicit-URL case.
 */
final class ErrorController extends Controller
{
    /**
     * The view folder for this controller.
     *
     * @var string
     */
    protected string $folder = 'errors';

    /**
     * Render an error view by status code, defaulting to 404.
     *
     * @param string $view Status code segment, e.g. "404".
     * @param string ...$params Unused extra route parameters.
     * @return void
     */
    public function index(string $view = '404', string ...$params): void
    {
        $status = ctype_digit($view) ? (int) $view : 404;
        if (!headers_sent()) {
            http_response_code($status);
        }

        $this->view("errors.{$status}", [
            'status'  => $status,
            'message' => '',
            'debug'   => (bool) config('app.debug', false),
        ]);
    }
}
