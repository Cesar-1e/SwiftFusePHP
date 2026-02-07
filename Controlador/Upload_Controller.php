<?php

class UploadControl extends Controlador
{

    public function __construct()
    {
    }

    public function img(){
        if(count($_FILES) == 0){
            $this->retorno["mensaje"] = "No se ha enviado ningún archivo";
            $this->retornar();
        }
        $file = saveImg($_FILES["imageFile"], "Public/Uploads/Images/");
        $files = saveImg($_FILES["imageFiles"], "Public/Uploads/Images/");
        if($file !== false || $files !== false){
            $this->retorno["exito"] = true;
            $this->retorno["data"] = array_merge(is_array($file) ? $file : [$file], is_array($files) ? $files : [$files]);
        }else{
            $this->retorno["mensaje"] = "Error al guardar la imagen";
        }
        $this->retornar();
    }
}
 ?>