<?php
require_once RUTA_APP . "Libreria/File.php";
/**
 * Clase encargada de gestionar la carga de archivos
 */
class ArchivosControl extends File
{

    public function cargaVista($archivo, $data){
        $ruta = "Archivos/" . $archivo;
        if(count($data) > 0){
            $ruta .= "/" . implode("/", $data);
        }
        $this->load($ruta);
    }
}
 ?>