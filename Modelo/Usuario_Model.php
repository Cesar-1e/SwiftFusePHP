<?php
require_once(RUTA_APP . "Modelo/Entidad/Usuario_Entity.php");

//Clase de muestra
class UsuarioMode{
    private $conexion;
    private $mensaje = null;
    private $entidad;

    public function __construct(){
        $this->conexion = new Conexion;
        if($this->conexion->getError() != null){
            handler(E_ERROR, $this->conexion->getError(), __FILE__, __LINE__);
        }

        $this->entidad = new Usuario;
    }

    public function getMensaje(){
        return $this->mensaje;
    }

    public function getEntidad(){
        return $this->entidad;
    }

    public function save(){
        $exito = false;
        try{
            
        }catch(PDOException $ex){
            $this->mensaje = $ex->getMessage();
        }
        return $exito;
    }
}
?>