<?php

/**
 * Abstract class encarga en estar en todos los modelos
 * 
 * Establece conexión al motor
 */
abstract class Model{
    protected $Conexion;
    protected $mensaje = null;
    protected $entidad;

    public function __construct(){
        $this->Conexion = new Conexion;
        if($this->Conexion->getError() != null){
            handler(E_ERROR, $this->Conexion->getError(), __FILE__, __LINE__);
        }
    }

    public function getMensaje(){
        return $this->mensaje;
    }

    public function getEntidad(){
        return $this->entidad;
    }
}
?>