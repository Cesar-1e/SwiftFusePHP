<?php
require_once RUTA_APP . "Libreria/File.php";
/**
 * @deprecated This controller is deprecated as part of the new SwiftFusePHP structure.
 * Use the secure file delivery services and protected storage instead.
 */
class ArchivosControl extends File
{
    /**
     * @deprecated Use secure file loading and authorization before delivering files.
     *
     * @param string $archivo
     * @param array $data
     * @return void
     */
    public function cargaVista($archivo, $data)
    {
        $ruta = "/" . $archivo;
        if (count($data) > 0) {
            $ruta .= "/" . implode("/", $data);
        }
        $this->load($ruta);
    }
}
?>
