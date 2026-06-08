<?php
/**
 * Storage service for protected file delivery.
 *
 * This service resolves files inside the storage folder and streams them
 * only after authorization checks.
 */
class StorageService
{
    protected string $storageRoot;

    public function __construct(?string $storagePath = null)
    {
        $storagePath = $storagePath ?? (defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 2) . '/storage/');
        $this->storageRoot = rtrim(realpath($storagePath) ?: $storagePath, '/') . '/';
    }

    /**
     * Stream a protected storage file if authorization passes.
     *
     * @param string $relativePath Path relative to the storage root.
     * @param callable $authorize Callback receiving the absolute file path and returning bool.
     * @return void
     */
    public function streamProtectedFile(string $relativePath, callable $authorize): void
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $target = realpath($this->storageRoot . $relativePath);

        if (!$target || !str_starts_with($target, $this->storageRoot) || !file_exists($target) || is_dir($target)) {
            error(404);
        }

        if (!call_user_func($authorize, $target)) {
            error(403);
        }

        $this->sendFileHeaders($target);
        readfile($target);
        exit;
    }

    /**
     * Send standard headers for streamed files.
     *
     * @param string $filePath
     * @return void
     */
    protected function sendFileHeaders(string $filePath): void
    {
        header('Content-Type: ' . $this->getMimeType($filePath));
        header('Content-Length: ' . filesize($filePath));
        header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
        header('Cache-Control: private, max-age=3600, must-revalidate');
    }

    /**
     * Resolve the file mime type.
     *
     * @param string $filePath
     * @return string
     */
    protected function getMimeType(string $filePath): string
    {
        $mimeType = mime_content_type($filePath);
        return $mimeType ?: 'application/octet-stream';
    }
}
