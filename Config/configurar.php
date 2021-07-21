<?php
 //Configuración de acceso a la base de datos
//Local
define("HOST", "localhost");
define("USERNAME", "root");
define("PASSWORD", "");
define("DATABASE", "");

//Ruta app
define("RUTA_APP", dirname(dirname(__FILE__)) . "/");
//Ruta url
if ($_SERVER["SERVER_PORT"] == 80) { //Puerto por defecto
    $aux =  "http://" . $_SERVER['SERVER_NAME'] . "/FRAMEWORK_PHP/";
} else { //Puerto personalizado
    $aux = "http://" . $_SERVER['SERVER_NAME'] . ":" . $_SERVER["SERVER_PORT"] . "/FRAMEWORK_PHP/";
}
define("RUTA_URL", $aux);
//Nombre del sitio
define("NOMBRESITIO", "");
define("LOGO", (RUTA_URL . ""));
//Versión del sitio
define("VERSION", "Alpha");
//Errores del sitio
define("ERROR404", RUTA_URL . "Error/404");
define("ERROR400", RUTA_URL . "Error/400");
define("DESARROLLO", RUTA_URL . "Error/desarrollo");


/**
 * Redirecciona a la vista con su código de error
 * y finaliza la ejecución
 * @param  Int $code Código de error; Ejemplo: 404
 * @return void
 */
function error($code){
    $auxUrl;
    switch ($code) {
        case 404:
           $auxUrl = ERROR404;
            break;
        case 400:
            $auxUrl = ERROR400;
            break;
        default:
            $auxUrl = ERROR404;
            break;
    }
    die(header("location: " . $auxUrl));
}

/**
 * Redirecciona a la vista con el aviso, de que
 * se esta en desarrollo
 * y finaliza la ejecución
 * @return void
 */
function desarrollo(){
    die(header("location: " . DESARROLLO));
}

//Datos de correo
define("NOMBRECORREO", "");

//Datos de la aplicación

/**
 * Retorna todos los generos
 * @return Array
 */
function getGeneros(){
    return array("Masculino", "Femenino");
}

define("MAXNACIMIENTO", date("Y-m-d", strtotime(date("Y-m-d")."- 10 year")));

/**
 * Filtra los datos de entrada
 * Verifica, que no tenga etiquetas HTML
 * Además, elimina los espacios de inicio y fin de la cadena de texto
 * En el caso, de que incumpla, se detendra la ejecución y produce error 400
 * @param String $string Input HTML
 * @return String
 */
function filterINPUT($string){
    if(preg_match("/^<|.<|>$|;/", $string) >= 1){
        die(header("location: " . ERROR400));
    }
    return trim($string);
}
?>