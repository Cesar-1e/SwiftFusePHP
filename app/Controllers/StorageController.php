<?php

declare(strict_types=1);

namespace App\Controllers;

use SwiftFuse\Http\Controller;
use SwiftFuse\Http\HttpException;
use SwiftFuse\Storage\StorageManager;

/**
 * Storage controller.
 *
 * Serves private files from the storage root after an authorization check.
 * Override authorize() to plug in your own access rules (this is the
 * inheritance-based extension point of the framework). Example URL:
 *
 *   /storage/serve/invoices/2026/invoice-1.pdf
 */
final class StorageController extends Controller
{
    /**
     * Serve a protected file identified by the trailing path segments.
     *
     * @param string ...$segments Path segments forming the storage-relative path.
     * @return never
     *
     * @throws HttpException With status 404 when no path is given.
     */
    public function serve(string ...$segments): never
    {
        if ($segments === []) {
            throw new HttpException(404, 'No file requested.');
        }

        /** @var StorageManager $storage */
        $storage = app(StorageManager::class);
        $storage->stream(implode('/', $segments), [$this, 'authorize']);
    }

    /**
     * Decide whether the current user may access the given file.
     *
     * The default policy requires an authenticated session user. Override this
     * method to implement ownership checks, roles, etc.
     *
     * @param string $path Absolute path to the requested file.
     * @return bool True to allow delivery, false to deny.
     */
    public function authorize(string $path): bool
    {
        return isset($_SESSION['user']);
    }
}
