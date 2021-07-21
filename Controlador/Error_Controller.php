<?php

/**
 * Clase encargada de gestionar las vistas del modulo de los errores
 */
class ErrorControl extends Controlador
{

    /**
     * Metodo por Default
     * Si el archivo no existe se detiene la ejecución y saldra ERROR 404
     * @param String $archivo Nombre del archivo 
     * @param Array $data Parametros desde la URL
     */
    public function cargaVista($archivo, $data){
        if(count($data) == 0 ){
            $this->vista('error/' . lcfirst($archivo), $data);
        }else{
            error(404);
        }
    }
}
 ?>