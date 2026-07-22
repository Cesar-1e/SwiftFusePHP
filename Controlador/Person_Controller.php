<?php

/**
 * @deprecated 0.9.9 Migrate to App\Controllers (extends SwiftFuse\Http\Controller)
 *             and App\Models\Person. Removed in 1.0.
 */
class PersonControl extends Controlador
{
    private $Person;

    public function __construct()
    {
        $this->Person = $this->modelo("Person");
    }

    public function list(){
        $data = $this->Person->getAll();
        if(count($data) > 0){
            $this->retorno["exito"] = true;
            $this->retorno["data"] = $data;
        }else{
            $this->retorno["mensaje"] = $this->Person->getMensaje();
        }
        $this->retornar();
    }
}
 ?>