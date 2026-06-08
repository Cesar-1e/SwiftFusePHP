<?php
require_once RUTA_APP . "Libreria/Controlador.user.php";
/**
 * Base controller for SwiftFusePHP.
 *
 * Custom application controllers can extend this class or override
 * framework controllers in App/Controllers.
 * For proper view resolution, set:
 *     protected $folder = "{folder-name}";
 */
abstract class Controlador extends ControladorUser
{
    protected $retorno = array("mensaje" => null, "exito" => false, "data" => null);
    protected $folder = "";
    
    /**
     * Default action.
     * If the view is missing, execution stops and a 404 is returned.
     *
     * @param string $archivo
     * @param array $data
     */
    public function cargaVista($archivo, $data)
    {
        if (count($data) === 0) {
            $this->vista($this->folder . '/' . lcfirst($archivo), $data);
        } else {
            error(404);
        }
    }

    /**
     * Load a model class from App/Models first, then fallback to Modelo/.
     *
     * @param string $modelo
     * @return object
     */
    public function modelo($modelo)
    {
        $customModel = "App/Models/" . $modelo . "_Model.php";
        if (file_exists($customModel)) {
            require_once $customModel;
        } else {
            require_once "Modelo/" . $modelo . "_Model.php";
        }

        $parts = explode("/", $modelo);
        $modelo = $parts[count($parts) - 1] . "Mode";
        return new $modelo;
    }

    /**
     * Render a view by checking HTML first, then PHP.
     *
     * @param string $vista
     * @param array $parametro
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
     * Output the standard JSON response.
     *
     * @return void
     */
    protected function retornar()
    {
        echo json_encode($this->retorno);
        die();
    }
}
 