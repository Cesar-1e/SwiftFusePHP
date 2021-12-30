<?php
 //Configuración de acceso a la base de datos
//Local
define("HOST", "localhost");
define("USERNAME", "root");
define("PASSWORD", "");
define("DATABASE", "");
define("LOCALDIR", "Framework_PHP/");

//Ruta app
define("RUTA_APP", dirname(dirname(__FILE__)) . "/");
//Ruta url
if ($_SERVER["SERVER_PORT"] == 80) { //Puerto por defecto
    $aux =  "http://" . $_SERVER['SERVER_NAME'] . LOCALDIR;
} else { //Puerto personalizado
    $aux = "http://" . $_SERVER['SERVER_NAME'] . ":" . $_SERVER["SERVER_PORT"] . LOCALDIR;
}
define("RUTA_URL", $aux);
//Nombre del sitio
define("NOMBRESITIO", "");
define("LOGO", (RUTA_URL . ""));
//Versión del sitio
define("VERSION", "Alpha");
//Errores del sitio
define("ERROR404", RUTA_URL . "Error/404");
define("ERROR403", RUTA_URL . "Error/403");
define("ERROR400", RUTA_URL . "Error/400");
define("DESARROLLO", RUTA_URL . "Error/desarrollo");


/**
 * Redirecciona a la vista con su código de error
 * y finaliza la ejecución
 * @param  Int $code Código de error; Ejemplo: 404
 * @return void
 */
function error($code){
    $auxUrl = ERROR404;
    switch ($code) {
        case 404:
           $auxUrl = ERROR404;
            break;
        case 400:
            $auxUrl = ERROR400;
            break;
        case 403:
            $auxUrl = ERROR403;
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

function saveImg($img, $relativePath, $nameImg = null){
    //Recibimos los atributos de la imagen
    $tmpImagen = $img['tmp_name'];
    $tipoImagen = explode("/", $img['type']);
    $pesoImagen = $img['size'];

    //Ruta de la carpeta donde se almacena la imagen
    $destino = RUTA_APP . $relativePath;

    //Establecemos el nombre de la imagen
    if($nameImg == null){
        do {
            $nombreImagen = rand(1, 100) * rand(1, 100) . "_thumb." . $tipoImagen[1];
        } while (file_exists($destino . $nombreImagen));
    }else{
        $nombreImagen = explode(".", $nameImg)[0] . "." . $tipoImagen[1];
    }
    //Parámetros optimización, resolución máxima permitida
    $max_ancho = 1280;
    $max_alto = 900;
    //Valida es tipo de archivo a subir, solo permite imagenes jpeg, jpg, png y gif
    if ($tipoImagen[1] == "jpeg" || $tipoImagen[1] == "jpg" || $tipoImagen[1] == "png" || $tipoImagen[1] == "gif") {
        $medidasImagen = getimagesize($tmpImagen);
        if ($medidasImagen[0] < 1280 && $pesoImagen < 100000) {
            move_uploaded_file($tmpImagen, $destino . $nombreImagen);
        } else {
            $rutaOriginal = $tmpImagen;
            if ($tipoImagen[1] == 'jpeg') {
                $original = imagecreatefromjpeg($rutaOriginal);
            } else if ($tipoImagen[1] == 'png') {
                $original = imagecreatefrompng($rutaOriginal);
            } else if ($tipoImagen[1] == 'gif') {
                $original = imagecreatefromgif($rutaOriginal);
            }
            $ancho = imagesx($original);
            $alto = imagesy($original);

            list($ancho, $alto) = getimagesize($rutaOriginal);

            $x_ratio = $max_ancho / $ancho;
            $y_ratio = $max_alto / $alto;

            if (($ancho <= $max_ancho) && ($alto <= $max_alto)) {
                $anchoFinal = $ancho;
                $altoFinal = $alto;
            } else if (($x_ratio * $alto) < $max_alto) {
                $altoFinal = ceil($x_ratio * $alto);
                $anchoFinal = $max_ancho;
            } else {
                $anchoFinal = ceil($y_ratio * $ancho);
                $altoFinal = $max_alto;
            }

            $lienzo = imagecreatetruecolor($anchoFinal, $altoFinal);

            imagecopyresampled($lienzo, $original, 0, 0, 0, 0, $anchoFinal, $altoFinal, $ancho, $alto);
            if ($tipoImagen[1] == 'jpeg') {
                imagejpeg($lienzo, $destino . $nombreImagen);
            } else if ($tipoImagen[1] == 'png') {
                imagepng($lienzo, $destino . $nombreImagen);
            } else if ($tipoImagen[1] == 'gif') {
                imagegif($lienzo, $destino . $nombreImagen);
            }
        }
        return $nombreImagen;
    } else {
        return "Solo se pueden subir imagenes tipo jpeg, jpg, png y gif.";
    }
}



function getDateInSpanish($fecha = null){
    $fecha == null ?: $fecha = date('d-m-Y');
    setlocale(LC_TIME, 'spanish');
    $mes=strftime("%B",mktime(0, 0, 0, date('m',strtotime($fecha)), 1, 2000)); 
    return date('d',strtotime($fecha))." de ".$mes." de ".date('Y',strtotime($fecha));
}

?>