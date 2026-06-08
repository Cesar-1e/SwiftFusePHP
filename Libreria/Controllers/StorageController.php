<?php
require_once RUTA_APP . "Libreria/Services/StorageService.php";

/**
 * Framework storage controller base.
 *
 * This class is provided by the framework and can be extended by app-level
 * controllers without modifying framework files.
 */
abstract class StorageControllerBase extends Controlador
{
    /**
     * Serve a protected file from storage.
     *
     * @param mixed $unused Ignored route segment from the framework.
     * @param array $segments Path segments representing the file within storage.
     * @return void
     */
    public function serve($unused = null, array $segments = [])
    {
        if (empty($segments)) {
            error(404);
        }

        $relativePath = implode('/', $segments);
        $service = new StorageService();
        $service->streamProtectedFile($relativePath, [$this, 'authorize']);
    }

    /**
     * Authorize access to the storage file.
     *
     * Override this method in App/Controllers/Storage_Controller.php if needed.
     *
     * @param string $filePath Absolute path to the file.
     * @return bool
     */
    public function authorize(string $filePath): bool
    {
        return true; // By default, allow access to all files. Override for custom logic.
    }
}
