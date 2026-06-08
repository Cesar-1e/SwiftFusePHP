<?php
require_once RUTA_APP . "Libreria/Controllers/StorageController.php";

/**
 * Application storage controller.
 *
 * Extend this class to customize storage delivery without modifying framework files.
 */
class StorageControl extends StorageControllerBase
{
    /**
     * Override this method to customize authorization.
     *
     * @param string $filePath Absolute path to the storage file.
     * @return bool
     */
    public function authorize(string $filePath): bool
    {
        // Example default behavior: require authenticated user.
        return parent::authorize($filePath);
    }
}
