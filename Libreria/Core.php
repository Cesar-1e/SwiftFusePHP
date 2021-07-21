<?php
 //Mapear URL

class Core
{
    protected $controladorActual = "Inicio";
    protected $metodoActual = "cargaVista";
    protected $vista = "index";
    protected $parametros = [];

    //Constrcutor sin parametros
    public function __construct()
    {
        $url = $this->getUrl();
        if(is_array($url)){
            if (file_exists("Controlador/" . ucwords($url[0] . "_Controller.php"))) {
                $this->controladorActual = ucwords($url[0]);
                //unset al indice 0 de $url
                unset($url[0]);
            }else if(isset($url[0])){
                error(404);
            }
        }

        //Requerir controlador
        require_once "Controlador/" . $this->controladorActual . "_Controller.php";
        $this->controladorActual .= "Control";
        $this->controladorActual = new $this->controladorActual;
        //Método(si lo hay)
        if(isset($url[1])){
            if (method_exists($this->controladorActual, $url[1])) { //Si el metodo existe
                $this->metodoActual = $url[1];
            }else{ //Vista
                $this->vista = $url[1];
            }
            //unset al indice 1 de $url
            unset($url[1]);
        }
        //Parametros(si lo hay)
        $this->parametros = $url ? array_values($url) : [];
        //callback con parametros array
        call_user_func_array([$this->controladorActual, $this->metodoActual], [$this->vista, $this->parametros]);

    }

    /*
    0 -> Controlador
    1 -> Metodo
    2>= -> Parametro(s)
    */
    private function getUrl()
    {
        if (isset($_GET["url"])) {
            $url = rtrim($_GET["url"], '/');
            //$url = filter_var($url, FILTER_SANITIZE_URL);
            $url = explode('/', $url);
            return $url;
        }
    }
}
 