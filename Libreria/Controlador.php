<?php
//Controlador Principal
//Se encarga de poder cargar los modelos y las vistas
class Controlador
{
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
}
 