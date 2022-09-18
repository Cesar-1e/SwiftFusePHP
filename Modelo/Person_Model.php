<?php
require_once RUTA_APP . "Modelo/Model_Abstract.php";
require_once RUTA_APP . "Modelo/Entidad/Person_Entity.php";

/**
 * Modelo de Person
 * 
 * Clase de ejemplo
 */
class PersonMode extends Model{

    public function __construct(){
        parent::__construct();

        $this->entidad = new Person;
    }

    public function getAll(){
        $data = array();
        try{
            //$this->Conexion->query("SELECT * FROM people;");
            $this->Conexion->query("CALL PR_getAllPeople();");
            if($this->Conexion->getRecord() == 0){
                $this->mensaje = "Se produjo un error al obtener el listado de las personas";
            }else{
                $data = $this->Conexion->getsArray();
            }
        }catch(PDOException $ex){
            $this->mensaje = $ex->getMessage();
        }
        return $data;
    }
}
?>