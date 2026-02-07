<?php

class PersonControl extends Controlador
{

    public function __construct()
    {
    }

    public function img(){
        $files = saveImg($_FILES, "Public/Uploads/Images/");
        if($files !== false){
            $this->retorno["exito"] = true;
            $this->retorno["data"] = $files;
        }else{
            $this->retorno["mensaje"] = "Error al guardar la imagen";
        }
        $this->retornar();
    }
}
 ?>