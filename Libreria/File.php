<?php
//Se encarga de abrir el archivo
class File
{
    //Cargar archivo
    public function load($file)
    {
        $file = RUTA_APP . $file;
        if(file_exists($file)){
            include $file;
        }else{
            error(404);
        }
    }
}
 