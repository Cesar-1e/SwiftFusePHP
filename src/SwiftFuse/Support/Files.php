<?php

declare(strict_types=1);

namespace SwiftFuse\Support;

use CURLFile;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Filesystem utility helpers.
 *
 * English home for the legacy directory/file global functions (mkdirs,
 * rmdir_r, filesToCURLFiles) previously declared in Config/configurar.php.
 * Paths are resolved against the project root for safety.
 */
final class Files
{
    /**
     * Recursively create a directory (and any missing parents).
     *
     * @param string $path Absolute path, or path relative to the project root.
     * @param int $permissions Octal permission mask for created directories.
     * @return bool True when the directory exists or was created.
     */
    public static function makeDirectory(string $path, int $permissions = 0755): bool
    {
        $path = self::absolute($path);
        if (is_dir($path)) {
            return true;
        }

        return mkdir($path, $permissions, true) || is_dir($path);
    }

    /**
     * Recursively delete a directory and all of its contents.
     *
     * @param string $path Absolute path, or path relative to the project root.
     * @return bool True when the directory was removed, false when it was missing.
     */
    public static function removeDirectory(string $path): bool
    {
        $path = self::absolute($path);
        if (!is_dir($path)) {
            return false;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }

        return rmdir($path);
    }

    /**
     * Convert the current $_FILES uploads into CURLFile objects for proxying.
     *
     * @return array<string, CURLFile> Map of field name to CURLFile.
     */
    public static function toCurlFiles(): array
    {
        $files = [];
        foreach ($_FILES as $field => $file) {
            if (($file['tmp_name'] ?? '') !== '') {
                $files[$field] = new CURLFile($file['tmp_name'], $file['type'], $file['name']);
            }
        }

        return $files;
    }

    /**
     * Resolve a path to an absolute one rooted at the project base.
     *
     * @param string $path Absolute path, or path relative to the project root.
     * @return string The absolute path.
     */
    private static function absolute(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
