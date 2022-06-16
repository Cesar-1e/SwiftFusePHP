<?php
/**
 * Se encarga en visualizar el file
 */
class File
{
    /**
     * Cargar archivo
     */
    public function load($file)
    {
        $file = RUTA_APP . $file;
        if(is_dir($file)){
            error(403);
        }
        if(file_exists($file)){
            header('Content-Type: ' . mime_content_type($file));
            readfile($file);
        }else{
            error(404);
        }
    }
}
 