<?php

declare(strict_types=1);

namespace App\Controllers;

use SwiftFuse\Http\Controller;
use SwiftFuse\Http\HttpException;
use SwiftFuse\Storage\SignedUrl;
use SwiftFuse\Storage\StorageManager;

/**
 * Upload controller.
 *
 * Demonstrates receiving files and storing them in the PRIVATE storage root
 * (storage/app/uploads), outside the web root. Each stored file is then offered
 * back through a short-lived signed URL, so it is only reachable after the
 * signature is validated — never by a direct, guessable path.
 */
final class UploadController extends Controller
{
    /**
     * Maximum accepted file size, in bytes (10 MB).
     */
    private const MAX_BYTES = 10 * 1024 * 1024;

    /**
     * Allowed lowercase file extensions.
     *
     * @var array<int, string>
     */
    private const ALLOWED = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'mp4'];

    /**
     * The view folder for this controller.
     *
     * @var string
     */
    protected string $folder = 'upload';

    /**
     * Show the upload form.
     *
     * @param string $view Unused default view segment.
     * @param string ...$params Unused extra route parameters.
     * @return void
     */
    public function index(string $view = 'index', string ...$params): void
    {
        $this->view('upload.index', ['result' => null]);
    }

    /**
     * Handle the uploaded file(s) and render the result.
     *
     * @return void
     *
     * @throws HttpException With status 405 when not requested via POST.
     */
    public function store(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            throw new HttpException(405, 'Use POST to upload files.');
        }

        $files = $this->normalizeFiles($_FILES['files'] ?? []);
        if ($files === []) {
            $this->renderResult(false, 'No file was uploaded.', []);
            return;
        }

        /** @var StorageManager $storage */
        $storage = app(StorageManager::class);
        $items = [];

        foreach ($files as $file) {
            $items[] = $this->storeOne($storage, $file);
        }

        $ok = array_reduce($items, static fn (bool $carry, array $item): bool => $carry && $item['ok'], true);
        $this->renderResult($ok, $ok ? 'Upload complete.' : 'Some files were rejected.', $items);
    }

    /**
     * Validate and store a single uploaded file.
     *
     * @param StorageManager $storage The storage manager.
     * @param array{name?:string,tmp_name?:string,error?:int,size?:int} $file Normalized upload entry.
     * @return array{name:string,ok:bool,message:string,url?:string}
     */
    private function storeOne(StorageManager $storage, array $file): array
    {
        $name = (string) ($file['name'] ?? 'file');
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (!in_array($extension, self::ALLOWED, true)) {
            return ['name' => $name, 'ok' => false, 'message' => "Type .{$extension} is not allowed."];
        }

        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['name' => $name, 'ok' => false, 'message' => 'File exceeds the 10 MB limit.'];
        }

        try {
            $path = $storage->store($file, 'uploads');

            return [
                'name' => $name,
                'ok' => true,
                'message' => 'Stored privately in storage/app/uploads.',
                'url' => SignedUrl::make('media/file', $path, 300),
            ];
        } catch (HttpException $exception) {
            return ['name' => $name, 'ok' => false, 'message' => $exception->getMessage()];
        }
    }

    /**
     * Normalize the $_FILES entry into a flat list of single-file arrays.
     *
     * Supports both single (`name="files"`) and multiple (`name="files[]"`) inputs.
     *
     * @param array<string, mixed> $input The raw $_FILES entry.
     * @return array<int, array{name:string,tmp_name:string,error:int,size:int}>
     */
    private function normalizeFiles(array $input): array
    {
        if (!isset($input['name'])) {
            return [];
        }

        if (!is_array($input['name'])) {
            return ((int) ($input['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) ? [] : [$input];
        }

        $files = [];
        foreach ($input['name'] as $index => $name) {
            if ((int) ($input['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $files[] = [
                'name' => (string) $name,
                'tmp_name' => (string) ($input['tmp_name'][$index] ?? ''),
                'error' => (int) ($input['error'][$index] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($input['size'][$index] ?? 0),
            ];
        }

        return $files;
    }

    /**
     * Render the upload view with a result summary.
     *
     * @param bool $ok Whether every file was stored successfully.
     * @param string $message Summary message.
     * @param array<int, array{name:string,ok:bool,message:string,url?:string}> $items Per-file results.
     * @return void
     */
    private function renderResult(bool $ok, string $message, array $items): void
    {
        $this->view('upload.index', [
            'result' => ['ok' => $ok, 'message' => $message, 'items' => $items],
        ]);
    }
}
