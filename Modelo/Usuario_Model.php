<?php
require_once RUTA_APP . "Modelo/Model_Abstract.php";
require_once RUTA_APP . "Modelo/Entidad/Usuario_Entity.php";

/**
 * Modelo de Usuario
 * 
 * Clase de ejemplo
 */
class UsuarioMode extends Model{

    public function __construct(){
        parent::__construct();

        $this->entidad = new Usuario;
    }

    public function getAll(){
        $users = array();
        try{
            $this->Conexion->query("SELECT * FROM usuarios;");
            $users = $this->Conexion->getsArray();
        }catch(PDOException $ex){
            $this->mensaje = $ex->getMessage();
        }
        return $users;
    }
}
?>