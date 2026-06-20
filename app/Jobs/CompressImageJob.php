<?php

declare(strict_types=1);

namespace App\Jobs;

use SwiftFuse\Contracts\JobInterface;

/**
 * Example background job: compress an image off the request cycle.
 *
 * This replaces the legacy "exec(compress_pdf.sh)" approach with a structured,
 * queued job. Dispatch it with:
 *
 *   app(SwiftFuse\Queue\QueueManager::class)
 *       ->dispatch(new App\Jobs\CompressImageJob('app/images/photo.jpg'));
 *
 * and process it with: php fuse queue:work
 */
final class CompressImageJob implements JobInterface
{
    /**
     * Storage-relative path to the image to compress.
     *
     * @var string
     */
    private string $relativePath;

    /**
     * Maximum width/height in pixels for the resized image.
     *
     * @var int
     */
    private int $maxEdge;

    /**
     * @param string $relativePath Storage-relative path to the source image.
     * @param int $maxEdge Maximum width/height in pixels.
     */
    public function __construct(string $relativePath, int $maxEdge = 1280)
    {
        $this->relativePath = $relativePath;
        $this->maxEdge = $maxEdge;
    }

    /**
     * Resize the image in place, preserving its aspect ratio and format.
     *
     * @return void
     */
    public function handle(): void
    {
        $path = storage_path('app/' . ltrim($this->relativePath, '/'));
        $info = @getimagesize($path);
        if ($info === false) {
            return;
        }

        [$width, $height] = $info;
        $scale = min(1.0, $this->maxEdge / max($width, $height));
        if ($scale >= 1.0) {
            return; // Already within bounds.
        }

        $newWidth = (int) round($width * $scale);
        $newHeight = (int) round($height * $scale);

        $source = $this->createImage($path, $info[2]);
        if ($source === null) {
            return;
        }

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $this->saveImage($canvas, $path, $info[2]);

        imagedestroy($source);
        imagedestroy($canvas);
    }

    /**
     * Create a GD image resource from a file based on its IMAGETYPE_* constant.
     *
     * @param string $path Absolute image path.
     * @param int $type IMAGETYPE_* constant.
     * @return \GdImage|null
     */
    private function createImage(string $path, int $type): ?\GdImage
    {
        return match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($path) ?: null,
            IMAGETYPE_PNG  => imagecreatefrompng($path) ?: null,
            IMAGETYPE_GIF  => imagecreatefromgif($path) ?: null,
            IMAGETYPE_WEBP => imagecreatefromwebp($path) ?: null,
            default        => null,
        };
    }

    /**
     * Save a GD image resource back to disk using the original format.
     *
     * @param \GdImage $image The image to save.
     * @param string $path Destination path.
     * @param int $type IMAGETYPE_* constant.
     * @return void
     */
    private function saveImage(\GdImage $image, string $path, int $type): void
    {
        match ($type) {
            IMAGETYPE_JPEG => imagejpeg($image, $path, 85),
            IMAGETYPE_PNG  => imagepng($image, $path),
            IMAGETYPE_GIF  => imagegif($image, $path),
            IMAGETYPE_WEBP => imagewebp($image, $path),
            default        => false,
        };
    }
}
