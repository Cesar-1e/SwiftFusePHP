<?php
//Controlador Principal
//Se encarga de poder cargar los modelos y las vistas

/**
 * Clase abstract encarga en estar en todos los controladores del framework.
 * Para su optimo funcionamiento se debe crear el siguiente atributo
 * private $folder = "{El folder donde se encuentran los views}";
 */
abstract class Controlador
{
    private $retorno = array("mensaje" => null, "exito" => false, "data" => null);
    
    /**
     * Metodo por Default
     * Si el archivo no existe se detiene la ejecución y saldra ERROR 404
     * 
     * Este metodo se puede sobreescribir
     * @param String $archivo Nombre del archivo 
     * @param Array $data Parametros desde la URL
     */
    public function cargaVista($archivo, $data){
        if(count($data) == 0 ){
            $this->vista($this->folder . '/' . lcfirst($archivo), $data);
        }else{
            error(404);
        }
    }

    //Cargar Modelo
    public function modelo($modelo)
    {
        require_once "Modelo/" . $modelo . "_Model.php";
        $modelo .= "Mode";
        return new $modelo;
    }

    //Cargar Vista
    public function vista($vista, $parametro = [])
    {
        $vista = lcfirst($vista);
        if (file_exists("Vista/" . $vista . "_View.html")) {
            require_once "Vista/" . $vista . "_View.html";
        } else if(file_exists("Vista/" . $vista . "_View.php")){
            require_once "Vista/" . $vista . "_View.php";
        }else{
            error(404);
        }
    }

    private function retornar()
    {
        echo json_encode($this->retorno);
    }
}
 