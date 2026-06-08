<?php
/**
 * File delivery helper for SwiftFusePHP.
 */
class File
{
    /**
     * Load a file directly via path.
     *
     * @param string $file
     * @return void
     */
    public function load($file)
    {
        $file = RUTA_APP . ltrim($file, '/');
        if (is_dir($file)) {
            error(403);
        }
        if (file_exists($file)) {
            header('Content-Type: ' . mime_content_type($file));
            readfile($file);
        } else {
            error(404);
        }
    }

    /**
     * Load a protected file after authorization.
     *
     * @param string $relativePath Path relative to the application root.
     * @param callable $authorize Callable that receives the file path and returns true when access is allowed.
     * @return void
     */
    public function loadProtected(string $relativePath, callable $authorize)
    {
        $file = RUTA_APP . ltrim($relativePath, '/');
        if (!file_exists($file) || is_dir($file)) {
            error(404);
        }
        if (!$authorize($file)) {
            error(403);
        }
        header('Content-Type: ' . mime_content_type($file));
        readfile($file);
    }
}
 