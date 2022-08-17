<?php
//Ruta app
define("RUTA_APP", dirname(dirname(__FILE__)) . "/");
function is_ssl($bool)
{
    define("IS_SSL", $bool);
    //Ruta url
    $http = "http" . (IS_SSL ? "s" : "");
    $aux =  $http . "://" . $_SERVER['SERVER_NAME'];
    if (!($_SERVER["SERVER_PORT"] == 80 || $_SERVER["SERVER_PORT"] == 443)) { //Puerto personalizado
        $aux .= ":" . $_SERVER["SERVER_PORT"];
    }
    $aux .= "/" . LOCALDIR;
    define("RUTA_URL", $aux);
    //Errores del sitio
    define("ERROR404", RUTA_URL . "Error/404");
    define("ERROR403", RUTA_URL . "Error/403");
    define("ERROR400", RUTA_URL . "Error/400");
    define("DESARROLLO", RUTA_URL . "Error/desarrollo");
    define("MANTENIMIENTO", RUTA_URL . "Error/mantenimiento");
}
//Versión del framework
define("VERSION_FRAMEWORK", "Beta");

/**
 * Redirecciona a la vista con su código de error
 * y finaliza la ejecución
 * @param  Int $code Código de error; Ejemplo: 404
 * @return void
 */
function error($code)
{
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
function desarrollo()
{
    die(header("location: " . DESARROLLO));
}

/**
 * Redirecciona a la vista con el aviso, de que
 * se esta en mantenimiento
 * y finaliza la ejecución
 * @return void
 */
function mantenimiento()
{
    die(header("location: " . DESARROLLO));
}

/**
 * Filtra los datos de entrada
 * Verifica, que no tenga etiquetas HTML
 * Además, elimina los espacios de inicio y fin de la cadena de texto
 * En el caso, de que incumpla, se detendra la ejecución y produce error 400
 * @param String $string Input HTML
 * @return String
 */
function filterINPUT($string)
{
    if (preg_match("/^<|.<|>$|;/", $string) >= 1) {
        die(header("location: " . ERROR400));
    }
    return trim($string);
}

/**
 * Guarda la imagen al recibirla mediante $_FILES
 * Si sus dimensiones son mayores de 1280p y el peso es mayor de 100kb, se reduce su peso sin afectar la calidad
 *
 * @param  array $imgs $_FILES
 * @param  string $relativePath Ruta relativa
 * @param  array $nameImgs Nombres del archivo; Por defecto el lo agrega de manera aleatoria sin sobreescribir un archivo existente. Si agregas un nombre y existe, sobrescribira ese archivo
 * @return array | false Retorna los nombres de los archivos, en el caso que falle retorna false
 */
function saveImg($imgs, $relativePath, $nameImgs = array())
{
    $nombreImagenes = array();
    $save = function ($tmp_name, $type, $size, $nameImg) use ($relativePath, &$nombreImagenes) {
        //Recibimos los atributos de la imagen
        $tmpImagen = $tmp_name;
        $tipoImagen = explode("/", $type);
        $pesoImagen = $size;

        //Ruta de la carpeta donde se almacena la imagen
        $destino = RUTA_APP . $relativePath . "/";

        //Establecemos el nombre de la imagen
        if ($nameImg == null) {
            do {
                $nombreImagen = rand(1, 100) * rand(1, 100) . "_thumb." . $tipoImagen[1];
            } while (file_exists($destino . $nombreImagen));
        } else {
            $nombreImagen = explode(".", $nameImg)[0] . "." . $tipoImagen[1];
        }
        //Parámetros optimización, resolución máxima permitida
        $max_ancho = 1280;
        $max_alto = 900;
        //Valida es tipo de archivo a subir, solo permite imagenes jpeg, jpg, png y gif
        if ($tipoImagen[1] == "jpeg" || $tipoImagen[1] == "jpg" || $tipoImagen[1] == "png" || $tipoImagen[1] == "gif" || $tipoImagen[1] == "webp") {
            $medidasImagen = getimagesize($tmpImagen);
            if ($medidasImagen[0] < $max_ancho && $pesoImagen < 100000) {
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
            $nombreImagenes[] = $nombreImagen;
        } else {
            handler(E_ERROR, "Solo se pueden subir imagenes tipo jpeg, jpg, png y gif.", __FILE__, __LINE__);
        }
    };

    $withName = function ($key) use ($nameImgs) {
        return (key_exists($key, $nameImgs) ? $nameImgs[$key] : null);
    };
    //Validamos si en un array de files
    if (is_array($imgs["name"])) {
        for ($i = 0; $i < count($imgs['name']); $i++) {
            $save($imgs["tmp_name"][$i], $imgs["type"][$i], $imgs["size"][$i], $withName($i));
        }
    } else {
        $save($imgs["tmp_name"], $imgs["type"], $imgs["size"], $withName(0));
    }

    return $nombreImagenes;
}

/**
 * Guarda el archivo al recibirla con $_FILES
 *
 * @param  mixed $file
 * @param  mixed $relativePath
 * @param  mixed $name
 * @return void
 */
function saveFile($file, $relativePath, $name = null)
{
    //Recibimos los atributos
    $tmp = $file['tmp_name'];
    $tipo = explode("/", $file['type']);

    //Ruta de la carpeta donde se almacena la imagen
    $destino = RUTA_APP . $relativePath;

    //Establecemos el nombre de la imagen
    if ($name == null) {
        do {
            $nombre = rand(1, 100) * rand(1, 100) . "." . $tipo[1];
        } while (file_exists($destino . $nombre));
    } else {
        $nombre = explode(".", $name)[0] . "." . $tipo[1];
    }
    if (!move_uploaded_file($tmp, $destino . $nombre)) {
        return false;
    }
    return $nombre;
}

/**
 * Suspende el acceso a la plataforma cuando
 * @var bool $isForced true no exista la $_SESSION['User']; false suspende el acceso total
 * @return void
 */
function suspend($isForced = true)
{
    $suspend = function () {
        if (!key_exists("suspend", $_SESSION)) {
            $_SESSION["suspend"] = true;
            mantenimiento();
        } else {
            unset($_SESSION["suspend"]);
        }
    };
    if ($isForced) {
        if (!key_exists("User", $_SESSION)) {
            $suspend();
        }
    } else {
        $suspend();
    }
}


/**
 * Valida el token de recaptcha v3 de Google
 * 
 * Los valores son obtenidos mediante $_POST -> token, action
 *
 * @param  string $typeAction Tipo de la acción
 * @return bool Si es mayor el score 0.5 y es el mismo tipo de acción return true, en el caso contrario false
 */
function validarToken($typeAction)
{
    $token = $_POST["token"];
    $action = $_POST["action"];
    $cu = curl_init();
    curl_setopt($cu, CURLOPT_URL, "https://www.google.com/recaptcha/api/siteverify");
    curl_setopt($cu, CURLOPT_POST, 1);
    curl_setopt($cu, CURLOPT_POSTFIELDS, http_build_query(array("secret" => CLAVE_RECAPTCHA_V3, "response" => $token)));
    curl_setopt($cu, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($cu);
    curl_close($cu);

    $datos = json_decode($response, true);
    if ($datos["success"] && $datos["score"] > 0.5) {
        if ($datos["action"] == $typeAction) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

/**
 * Obtener la fecha en español
 *
 * @param  string $date default 'now'
 * @return string fecha en español
 */
function getDateInSpanish($date = 'now')
{
    $dateTimeObj = new DateTime($date, new DateTimeZone(date_default_timezone_get()));
    $dateFormatted =
        IntlDateFormatter::formatObject(
            $dateTimeObj,
            "d 'de' MMMM 'de' y",
            'es_ES'
        );
    return $dateFormatted;
}

/**
 * Si es null lo convierte en 0
 *
 * @param  int $value
 * @return int
 */
function nullToZero($value)
{
    return ($value == null ? 0 : $value);
}