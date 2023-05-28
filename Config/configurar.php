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
//Versión del SwiftFusePHP
define("VERSION_SWIFTFUSEPHP", "0.7");

/**
 * Redirecciona a la vista con su código de estado
 * y finaliza la ejecución
 * @param  Int $code Code Status; Ejemplo: 404
 * @param  Boolean $isRedirect true => Redirecciona a la vista, false => Imprime la vista
 * @return void
 */
function error($code, $isRedirect = true)
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
        case 503:
            $auxUrl = MANTENIMIENTO;
            break;
        case 501:
            $auxUrl = DESARROLLO;
            break;
        default:
            $auxUrl = ERROR404;
            break;
    }
    setStatusCode($code);
    if ($isRedirect) {
        header("location: " . $auxUrl);
    } else {
        require_once RUTA_APP . "Vista/error/{$code}_View.php";
    }
    die();
}

/**
 * Redirecciona a la vista con el aviso, de que
 * se esta en desarrollo
 * y finaliza la ejecución
 * @return void
 * @deprecated
 */
function desarrollo()
{
    handler(E_DEPRECATED, "Deprecated function " . debug_backtrace()[0]['function'], __FILE__, __LINE__);
    die(header("location: " . DESARROLLO));
}

/**
 * Redirecciona a la vista con el aviso, de que
 * se esta en mantenimiento
 * y finaliza la ejecución
 * @return void
 * @deprecated
 */
function mantenimiento()
{
    handler(E_DEPRECATED, "Deprecated function " . debug_backtrace()[0]['function'], __FILE__, __LINE__);
    die(header("location: " . DESARROLLO));
}

/**
 * Filtra los datos de entrada
 * Verifica, que no tenga etiquetas HTML
 * Además, elimina los espacios de inicio y fin de la cadena de texto
 * En el caso, de que incumpla, se detendra la ejecución y produce error 400
 * En el caso de que $stirng recbia ''|'null'|'NULL' => Este retornara un NULL
 * @param String $string Input HTML
 * @return String
 */
function filterINPUT($string)
{
    if ($string == "" || strtolower($string) == "null") {
        return null;
    }
    if (preg_match("/^<|.<|>$|;/", $string) >= 1) {
        error(400);
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
            $nombreImagen = $nameImg;
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

    mkdirs($relativePath);

    $withName = function ($key) use ($nameImgs) {
        return (key_exists($key, $nameImgs) ? $nameImgs[$key] : null);
    };
    //Validamos si en un array de files
    if (is_array($imgs["name"])) {
        for ($i = 0; $i < count($imgs['name']); $i++) {
            if ($imgs["error"][$i] != 0) {
                return false;
            }
            $save($imgs["tmp_name"][$i], $imgs["type"][$i], $imgs["size"][$i], $withName($i));
        }
    } else {
        if ($imgs["error"] != 0) {
            return false;
        }
        $save($imgs["tmp_name"], $imgs["type"], $imgs["size"], $withName(0));
    }

    return $nombreImagenes;
}

/**
 * Guarda el archivo al recibirla con $_FILES
 *
 * @param  array $files $_FILES
 * @param  string $relativePath Ruta relativa
 * @param  array $nameFiles Nombres del archivo; Por defecto el lo agrega de manera aleatoria sin sobreescribir un archivo existente. Si agregas un nombre y existe, sobrescribira ese archivo
 * @return array|false Retorna los nombres de los archivos, en el caso que falle retorna false
 */
function saveFile($files, $relativePath, $nameFiles = array())
{
    $nombreArchivos = array();
    $save = function ($tmp_name, $type, $size, $nameFile) use ($relativePath, &$nombreArchivos) {
        //Recibimos los atributos
        $tmp = $tmp_name;
        $tipo = explode("/", $type);

        //Ruta de la carpeta donde se almacena la imagen
        $destino = RUTA_APP . $relativePath . "/";

        //Establecemos el nombre de la imagen
        if ($nameFile == null) {
            do {
                $nombre = rand(1, 100) * rand(1, 100) . "." . $tipo[1];
            } while (file_exists($destino . $nombre));
        } else {
            $nombre = $nameFile;
        }
        if (!move_uploaded_file($tmp, $destino . $nombre)) {
            return false;
        }else{
            if (str_contains($nombre, ".pdf")) {
                exec(RUTA_APP . "Archivos/scripts/compress_pdf.sh '" . RUTA_APP . "' '" . $destino . $nombre . "'");
            }
        }
        $nombreArchivos[] = $nameFile;
    };

    mkdirs($relativePath);

    $withName = function ($key) use ($nameFiles) {
        return (key_exists($key, $nameFiles) ? $nameFiles[$key] : null);
    };
    //Validamos si en un array de files
    if (is_array($files["name"])) {
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files["error"][$i] != 0) {
                return false;
            }
            $save($files["tmp_name"][$i], $files["type"][$i], $files["size"][$i], $withName($i));
        }
    } else {
        if ($files["error"] != 0) {
            return false;
        }
        $save($files["tmp_name"], $files["type"], $files["size"], $withName(0));
    }
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
            error(503);
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


/**
 * Establece el status en el header
 *
 * @param  Int $code Código del estado
 * @return void
 */
function setStatusCode($code)
{
    $statusCodes = array(
        100 => "Continue",
        101 => "Switching Protocols",
        102 => "Processing",
        103 => "Early Hints",
        200 => "OK",
        201 => "Created",
        202 => "Accepted",
        203 => "Non-Authoritative Information",
        204 => "No Content",
        205 => "Reset Content",
        206 => "Partial Content",
        207 => "Multi-Status",
        208 => "Already Reported",
        226 => "IM Used",
        300 => "Multiple Choices",
        301 => "Moved Permanently",
        302 => "Found",
        303 => "See Other",
        304 => "Not Modified",
        305 => "Use Proxy",
        307 => "Temporary Redirect",
        308 => "Permanent Redirect",
        400 => "Bad Request",
        401 => "Unauthorized",
        402 => "Payment Required",
        403 => "Forbidden",
        404 => "Not Found",
        405 => "Method Not Allowed",
        406 => "Not Acceptable",
        407 => "Proxy Authentication Required",
        408 => "Request Timeout",
        409 => "Conflict",
        410 => "Gone",
        411 => "Length Required",
        412 => "Precondition Failed",
        413 => "Payload Too Large",
        414 => "URI Too Long",
        415 => "Unsupported Media Type",
        416 => "Range Not Satisfiable",
        417 => "Expectation Failed",
        418 => "I'm a teapot",
        421 => "Misdirected Request",
        422 => "Unprocessable Entity",
        423 => "Locked",
        424 => "Failed Dependency",
        425 => "Too Early",
        426 => "Upgrade Required",
        428 => "Precondition Required",
        429 => "Too Many Requests",
        431 => "Request Header Fields Too Large",
        451 => "Unavailable For Legal Reasons",
        500 => "Internal Server Error",
        501 => "Not Implemented",
        502 => "Bad Gateway",
        503 => "Service Unavailable",
        504 => "Gateway Timeout",
        505 => "HTTP Version Not Supported",
        506 => "Variant Also Negotiates",
        507 => "Insufficient Storage",
        508 => "Loop Detected",
        510 => "Not Extended",
        511 => "Network Authentication Required"
    );
    if (isset($statusCodes[$code])) {
        $statusText = $statusCodes[$code];
        header("HTTP/1.1 $code $statusText");
    } else {
        handler(E_ERROR, "List status codes returned an unknown status code: {$code}", __FILE__, __LINE__);
    }
}

/**
 * Creación de directorios y subdirectorios
 *
 * @param  mixed $relativePath Ruta relativa
 * @return void
 */
function mkdirs($relativePath)
{
    $folders = explode("/", $relativePath);
    $path = RUTA_APP;
    foreach ($folders as $folder) {
        $path .= "/" . $folder;
        if (!is_dir($path)) mkdir($path);
    }
}

/**
 * Obtiene el texto del mes
 *
 * @param  Int $index El index del array month
 * @return String
 */
function getMonthInSpanish($index)
{
    $months = array("Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre");
    return $months[$index];
}

/**
 * Sí el string es diferente a NULL este le retorna el string agegandole comillas al inicio y el fin de la cadena
 * Si es NULL le retorna en texto la palabra null
 * Ejemplo: 
 * Hola mundo => 'Hola mundo'
 * NULL => 'null'
 *
 * @param  String $string Cadena de texto
 * @return String
 */
function stringWithQuotationMark($string)
{
    if ($string != null) {
        $string = "'{$string}'";
    } else {
        $string = "null";
    }
    return $string;
}

/**
 * Retorna en formato de moneda
 *
 * @param  Int $number
 * @return String
 */
function formatCurrency($number) {
    return "💲 " . number_format($number, 0, ",", ".");
}

/**
 * Eliminar directorios recursivos
 * Si el directorio cuenta con archivos o subdirectorios, este tambien sera eliminado
 *
 * @param  String $relativePath Ruta relativa
 * @return void
 */
function rmdir_r($relativePath)
{
    $folder = RUTA_APP . $relativePath;
    $it = new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator(
        $it,
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
    rmdir($folder);
}

/**
 * $_FILES => CURLFile
 *
 * @return Array
 */
function filesToCURLFiles()
{
    $files = array();
    foreach ($_FILES as $key => $file) {
        if ($file['tmp_name'] !== "") {
            $files[$key] = new CURLFile($file['tmp_name'], $file['type'], $file['name']);
        }
    }
    return $files;
}