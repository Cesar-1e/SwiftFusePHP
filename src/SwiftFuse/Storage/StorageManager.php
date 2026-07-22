<?php

declare(strict_types=1);

namespace SwiftFuse\Storage;

use SwiftFuse\Http\HttpException;

/**
 * Private file storage and protected delivery manager.
 *
 * Files live under a storage root that is OUTSIDE the public web root, so they
 * can never be requested directly. Delivery happens only after an authorization
 * callback succeeds, and is performed efficiently:
 *
 *   1. X-Sendfile (Apache mod_xsendfile) or X-Accel-Redirect (Nginx) when
 *      configured — the web server streams the file, so PHP uses no memory and
 *      HTTP Range/resume is handled natively. This is the recommended mode.
 *   2. Otherwise, a chunked PHP stream with HTTP Range support, so large media
 *      (e.g. video) can be seeked without loading the whole file into memory.
 *
 * This evolves the legacy StorageService into a configurable, performant manager.
 */
class StorageManager
{
    /**
     * Number of bytes read per chunk during PHP streaming.
     */
    private const CHUNK_SIZE = 8192;

    /**
     * Absolute, normalized storage root (with trailing slash).
     *
     * @var string
     */
    protected string $root;

    /**
     * Delivery acceleration mode: "none", "apache" or "nginx".
     *
     * @var string
     */
    protected string $accel;

    /**
     * Nginx internal location prefix mapped to the storage root.
     *
     * @var string
     */
    protected string $nginxInternal;

    /**
     * @param string|null $root Storage root path; defaults to config('storage.root').
     */
    public function __construct(?string $root = null)
    {
        $root ??= (string) config('storage.root', storage_path('app'));
        $this->root = rtrim(realpath($root) ?: $root, '/') . '/';
        $this->accel = (string) config('storage.accel', 'none');
        $this->nginxInternal = (string) config('storage.nginx_internal', '/protected/');
    }

    /**
     * Resolve a relative path to a safe absolute path inside the storage root.
     *
     * Guards against path traversal: the resolved file must exist and remain
     * within the storage root.
     *
     * @param string $relativePath Path relative to the storage root.
     * @return string The absolute, validated file path.
     *
     * @throws HttpException With status 404 when the path is invalid or missing.
     */
    public function path(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $target = realpath($this->root . $relativePath);

        if ($target === false || !str_starts_with($target, $this->root) || is_dir($target)) {
            throw new HttpException(404, "Storage file [{$relativePath}] not found.");
        }

        return $target;
    }

    /**
     * Store an uploaded file inside the private storage root.
     *
     * @param array{name?:string,tmp_name?:string,error?:int,size?:int} $file
     *        A single normalized entry from the $_FILES superglobal.
     * @param string $directory Sub-directory under the storage root.
     * @param string|null $name Optional file name; a unique one is generated when null.
     * @return string The stored path, relative to the storage root.
     *
     * @throws HttpException With status 422 when the upload is invalid, or 500 on I/O failure.
     */
    public function store(array $file, string $directory = 'uploads', ?string $name = null): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['tmp_name'] ?? '') === '') {
            throw new HttpException(422, 'The uploaded file is invalid.');
        }

        $directory = trim(str_replace('\\', '/', $directory), '/');
        $targetDir = $this->root . $directory;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new HttpException(500, 'Unable to create the storage directory.');
        }

        $name = basename($name ?? bin2hex(random_bytes(8)) . $this->extension((string) ($file['name'] ?? '')));
        $absolute = $targetDir . '/' . $name;

        $moved = move_uploaded_file((string) $file['tmp_name'], $absolute)
            || (!is_uploaded_file((string) $file['tmp_name']) && rename((string) $file['tmp_name'], $absolute));

        if (!$moved) {
            throw new HttpException(500, 'Unable to store the uploaded file.');
        }

        return $directory . '/' . $name;
    }

    /**
     * Extract a safe, lowercased file extension (with leading dot) from a name.
     *
     * @param string $filename Original file name.
     * @return string Extension including the dot, or an empty string.
     */
    private function extension(string $filename): string
    {
        $extension = preg_replace('/[^a-z0-9]/', '', strtolower(pathinfo($filename, PATHINFO_EXTENSION))) ?? '';

        return $extension === '' ? '' : '.' . $extension;
    }

    /**
     * Stream a protected storage file after an authorization check passes.
     *
     * @param string $relativePath Path relative to the storage root.
     * @param callable(string):bool $authorize Receives the absolute path; return true to allow.
     * @return never
     *
     * @throws HttpException With status 403 when authorization fails.
     */
    public function stream(string $relativePath, callable $authorize): never
    {
        $file = $this->path($relativePath);

        if ($authorize($file) !== true) {
            throw new HttpException(403, 'Not authorized to access this file.');
        }

        $this->deliver($file);
        exit;
    }

    /**
     * Deliver the file using the most efficient available transport.
     *
     * @param string $file Absolute path to an existing, authorized file.
     * @return void
     */
    protected function deliver(string $file): void
    {
        $mime = mime_content_type($file) ?: 'application/octet-stream';

        if ($this->accel === 'apache') {
            header('Content-Type: ' . $mime);
            header('X-Sendfile: ' . $file);
            return;
        }

        if ($this->accel === 'nginx') {
            $internal = rtrim($this->nginxInternal, '/') . '/' . ltrim(substr($file, strlen($this->root)), '/');
            header('Content-Type: ' . $mime);
            header('X-Accel-Redirect: ' . $internal);
            return;
        }

        $this->streamWithRange($file, $mime);
    }

    /**
     * Stream a file in chunks with HTTP Range support (enables video seeking).
     *
     * @param string $file Absolute path to the file.
     * @param string $mime Resolved MIME type.
     * @return void
     */
    protected function streamWithRange(string $file, string $mime): void
    {
        $size = filesize($file);
        $start = 0;
        $end = $size - 1;

        header('Content-Type: ' . $mime);
        header('Accept-Ranges: bytes');
        header('Cache-Control: private, max-age=3600, must-revalidate');

        $range = $_SERVER['HTTP_RANGE'] ?? '';
        if ($range !== '' && preg_match('/bytes=(\d*)-(\d*)/', $range, $matches) === 1) {
            if ($matches[1] !== '') {
                $start = (int) $matches[1];
            }
            if ($matches[2] !== '') {
                $end = (int) $matches[2];
            }

            if ($start > $end || $end >= $size) {
                header('Content-Range: bytes */' . $size);
                http_response_code(416);
                return;
            }

            $status = 206;
            http_response_code(206);
            header(sprintf('Content-Range: bytes %d-%d/%d', $start, $end, $size));
        }

        header('Content-Length: ' . ($end - $start + 1));

        $handle = fopen($file, 'rb');
        if ($handle === false) {
            return;
        }

        fseek($handle, $start);
        $remaining = $end - $start + 1;

        while ($remaining > 0 && !feof($handle)) {
            $read = (int) min(self::CHUNK_SIZE, $remaining);
            echo fread($handle, $read);
            $remaining -= $read;
            flush();
        }

        fclose($handle);
        unset($status);
    }
}
