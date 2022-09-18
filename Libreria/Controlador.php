<?php
require_once RUTA_APP . "Libreria/Controlador.user.php";
/**
 * Controlador Principal
 * 
 * Clase abstract encargada en estar en todos los controladores del framework.
 * Para su optimo funcionamiento se debe crear el siguiente atributo
 * protected $folder = "{El folder donde se encuentran los views}";
 */
abstract class Controlador extends ControladorUser
{
    protected $retorno = array("mensaje" => null, "exito" => false, "data" => null);
    protected $folder = "";
    
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

    /**
     * Require el modelo y return instanciado el object
     * 
     * @var $modelo El modelo a requerir e instanciar
     * 
     * @return Object
     */
    public function modelo($modelo)
    {
        require_once "Modelo/" . $modelo . "_Model.php";
        $modelo .= "Mode";
        return new $modelo;
    }

        
    /**
     * Require vista, Se le da prioriodad el .html, luego el .php
     *
     * En el caso de que la vista no existe, se redirreciona al error 404
     * @param  string $vista Vista a visualizar
     * @param  array $parametro parametros para enviar a la vista; Opcional
     * @return void
     */
    public function vista($vista, $parametro = [])
    {
        $vista = lcfirst($vista);
        $extensions = array("html", "php");
        $isExist = false;
        foreach ($extensions as $extension) {
            $file = "Vista/" . $vista . "_View." . $extension;
            if (file_exists($file)) {
                $isExist = true;
                require_once $file;
                break;
            }
        }
        ($isExist ?: error(404));
    }
    
    /**
     * Realiza un echo con el contenido del atributo $retorno en String JSON
     *
     * @return void
     */
    protected function retornar()
    {
        echo json_encode($this->retorno);
        die();
    }
}
 