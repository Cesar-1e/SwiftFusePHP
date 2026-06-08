<?php

/**
 * Request router and bootstrapping class for SwiftFusePHP.
 *
 * The router prefers custom app controllers in App/Controllers over
 * framework controllers in Controlador/.
 */
class Core
{
    protected $controladorActual = "Inicio";
    protected $metodoActual = "cargaVista";
    protected $vista = "index";
    protected $parametros = [];

    public function __construct()
    {
        $url = $this->getUrl();
        if (is_array($url) && isset($url[0]) && $url[0] !== '') {
            $controllerName = ucwords($url[0]);
            if (file_exists("App/Controllers/{$controllerName}_Controller.php")) {
                $this->controladorActual = $controllerName;
                unset($url[0]);
            } elseif (file_exists("Controlador/{$controllerName}_Controller.php")) {
                $this->controladorActual = $controllerName;
                unset($url[0]);
            } elseif (isset($url[0])) {
                error(404);
            }
        }

        $controllerPath = file_exists("App/Controllers/{$this->controladorActual}_Controller.php")
            ? "App/Controllers/{$this->controladorActual}_Controller.php"
            : "Controlador/{$this->controladorActual}_Controller.php";

        require_once $controllerPath;
        $this->controladorActual .= "Control";
        $this->controladorActual = new $this->controladorActual;

        if (isset($url[1])) {
            if (method_exists($this->controladorActual, $url[1])) {
                $this->metodoActual = $url[1];
            } else {
                $this->vista = $url[1];
            }
            unset($url[1]);
        }

        $this->parametros = $url ? array_values($url) : [];

        if (method_exists($this->controladorActual, 'beforeAction')) {
            $allowed = $this->controladorActual->beforeAction($this->metodoActual, $this->parametros);
            if ($allowed === false) {
                error(403);
            }
        }

        call_user_func_array([$this->controladorActual, $this->metodoActual], [$this->vista, $this->parametros]);

        if (method_exists($this->controladorActual, 'afterAction')) {
            $this->controladorActual->afterAction($this->metodoActual, $this->parametros);
        }
    }

    /**
     * Get the request URL segments.
     *
     * [0] -> controller
     * [1] -> action or view
     * [2]>= -> parameters
     *
     * @return array
     */
    private function getUrl()
    {
        if (isset($_GET["url"])) {
            $url = rtrim($_GET["url"], '/');
            return explode('/', $url);
        }
        return [];
    }
}
 