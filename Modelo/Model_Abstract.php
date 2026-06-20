<?php

/**
 * Abstract class encarga en estar en todos los modelos
 *
 * Establece conexión al motor
 *
 * @deprecated 0.9.9 Replaced by SwiftFuse\Database\Model. New models live in
 *             app/Models, extend the namespaced base class and use
 *             SwiftFuse\Database\Connection. Removed in 1.0.
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