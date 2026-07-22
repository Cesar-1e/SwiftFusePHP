<?php
/**
 * File delivery helper for SwiftFusePHP.
 *
 * @deprecated 0.9.9 Replaced by SwiftFuse\Storage\StorageManager, which delivers
 *             private files from outside the web root with authorization, X-Sendfile
 *             support and HTTP Range streaming. Removed in 1.0.
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
}
 