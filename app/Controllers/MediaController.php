<?php

declare(strict_types=1);

namespace App\Controllers;

use SwiftFuse\Http\Controller;
use SwiftFuse\Http\HttpException;
use SwiftFuse\Storage\SignedUrl;
use SwiftFuse\Storage\StorageManager;

/**
 * Media controller.
 *
 * Demonstrates delivering protected media (a video) that lives outside the web
 * root. The page embeds a short-lived signed URL; the streaming endpoint
 * validates that signature before letting StorageManager serve the bytes with
 * HTTP Range support, so playback (including seeking) never exposes the real
 * file path nor loads the whole file into memory.
 */
final class MediaController extends Controller
{
    /**
     * Storage-relative path to the example protected video.
     */
    private const SAMPLE_VIDEO = 'video/sample.mp4';

    /**
     * The view folder for this controller.
     *
     * @var string
     */
    protected string $folder = 'media';

    /**
     * Render a page that plays the protected video via a signed URL.
     *
     * In a real application you would first verify the user is authenticated
     * before generating the signed URL.
     *
     * @return void
     */
    public function video(): void
    {
        // A signed URL valid for 5 minutes, only generated for authorized users.
        $signedUrl = SignedUrl::make('media/file', self::SAMPLE_VIDEO, 300);

        $this->view('media.video', ['signedUrl' => $signedUrl]);
    }

    /**
     * Validate the signed request and stream the protected file.
     *
     * @return never
     *
     * @throws HttpException With status 403 when the signature is missing/invalid.
     */
    public function file(): never
    {
        $resource = (string) ($_GET['resource'] ?? '');
        $expires = (int) ($_GET['expires'] ?? 0);
        $signature = (string) ($_GET['signature'] ?? '');

        if (!SignedUrl::isValid($resource, $expires, $signature)) {
            throw new HttpException(403, 'Invalid or expired media link.');
        }

        /** @var StorageManager $storage */
        $storage = app(StorageManager::class);

        // The signature already proves authorization, so allow the stream.
        $storage->stream($resource, static fn (string $path): bool => true);
    }
}
