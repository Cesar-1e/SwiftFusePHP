<?php
/**
 * Ruta de la aplicación.
 * 
 * Esta constante define la ruta absoluta de la aplicación.
 * 
 * @var string RUTA_APP
 */
define("RUTA_APP", dirname(dirname(__FILE__)) . "/");

/**
 * Configura la configuración de SSL y define las rutas URL del sitio.
 *
 * Esta función toma un parámetro booleano para indicar si se utiliza SSL (HTTPS) o no.
 * Configura la constante IS_SSL con el valor proporcionado y define las rutas URL del sitio
 * basadas en el protocolo HTTP o HTTPS según el valor de IS_SSL.
 *
 * @param bool $bool Indica si se utiliza SSL (HTTPS) o no.
 * @return void
 */
function is_ssl($bool)
{
    define("IS_SSL", $bool);
    
    // Ruta URL
    $http = "http" . (IS_SSL ? "s" : "");
    $aux = $http . "://" . $_SERVER['SERVER_NAME'];
    
    if (!($_SERVER["SERVER_PORT"] == 80 || $_SERVER["SERVER_PORT"] == 443)) {
        // Puerto personalizado
        $aux .= ":" . $_SERVER["SERVER_PORT"];
    }
    
    $aux .= "/" . LOCALDIR;
    define("RUTA_URL", $aux);
    
    // Errores del sitio
    define("ERROR404", RUTA_URL . "Error/404");
    define("ERROR403", RUTA_URL . "Error/403");
    define("ERROR400", RUTA_URL . "Error/400");
    define("DESARROLLO", RUTA_URL . "Error/desarrollo");
    define("MANTENIMIENTO", RUTA_URL . "Error/mantenimiento");
}

/**
 * Versión del SwiftFusePHP.
 *
 * Esta constante define la versión actual del SwiftFusePHP.
 *
 * @var string
 */
define("VERSION_SWIFTFUSEPHP", "0.9");

/**
 * Redirecciona a la vista con su código de estado y finaliza la ejecución.
 *
 * Esta función redirecciona al usuario a una vista específica según el código de estado proporcionado.
 * Si el parámetro $isRedirect es true, se realiza una redirección HTTP a la vista correspondiente.
 * Si $isRedirect es false, se imprime directamente la vista en el navegador.
 *
 * @param int $code Código de estado (ejemplo: 404)
 * @param bool $isRedirect Indica si se realiza una redirección o se imprime la vista directamente
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
 * Filtra los datos de entrada y verifica que no contengan etiquetas HTML.
 *
 * Esta función recibe una cadena de texto como entrada y realiza las siguientes operaciones:
 * - Elimina los espacios de inicio y fin de la cadena de texto.
 * - Verifica que la cadena de texto no contenga etiquetas HTML.
 * - Si la cadena de texto es una cadena vacía (''), 'null' o 'NULL', retorna null.
 * - Si la cadena de texto contiene etiquetas HTML, detiene la ejecución y produce un error 400.
 *
 * @param string $string Entrada de texto HTML
 * @return string|null Cadena de texto filtrada o null si la entrada es vacía o igual a 'null' o 'NULL'
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
 * Guarda la imagen recibida a través de $_FILES y realiza compresión si es necesario.
 *
 * Esta función recibe los siguientes parámetros:
 * - $imgs: Un arreglo $_FILES que contiene la información de la imagen a guardar.
 * - $relativePath: La ruta relativa donde se almacenará la imagen.
 * - $nameImgs (opcional): Un arreglo de nombres de archivo personalizados. Por defecto, se generan nombres aleatorios.
 *
 * La función realiza las siguientes operaciones:
 * - Verifica las dimensiones y el peso de la imagen.
 * - Si las dimensiones son mayores de 1280p y el peso es mayor de 100kb, se reduce el peso de la imagen sin afectar la calidad.
 * - Almacena la imagen en la carpeta especificada en $relativePath.
 * - Retorna un arreglo con los nombres de los archivos guardados. En caso de fallo, retorna false.
 *
 * @param array $imgs Arreglo $_FILES con la información de la imagen.
 * @param string $relativePath Ruta relativa donde se almacenará la imagen.
 * @param array $nameImgs (opcional) Nombres de archivo personalizados.
 * @return array|false Nombres de los archivos guardados o false en caso de fallo.
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
 * Guarda el archivo recibido a través de $_FILES.
 *
 * Esta función recibe los siguientes parámetros:
 * - $files: Un arreglo $_FILES que contiene la información del archivo a guardar.
 * - $relativePath: La ruta relativa donde se almacenará el archivo.
 * - $nameFiles (opcional): Un arreglo de nombres de archivo personalizados. Por defecto, se generan nombres aleatorios.
 *
 * La función realiza las siguientes operaciones:
 * - Almacena el archivo en la carpeta especificada en $relativePath.
 * - En caso de archivos PDF, se realiza una compresión utilizando un script externo.
 * - Retorna un arreglo con los nombres de los archivos guardados. En caso de fallo, retorna false.
 *
 * @param array $files Arreglo $_FILES con la información del archivo.
 * @param string $relativePath Ruta relativa donde se almacenará el archivo.
 * @param array $nameFiles (opcional) Nombres de archivo personalizados.
 * @return array|false Nombres de los archivos guardados o false en caso de fallo.
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
 * Suspende el acceso a la plataforma.
 *
 * Esta función suspende el acceso a la plataforma en función de los parámetros proporcionados:
 * - $isForced: Un valor booleano que indica si la suspensión es forzada o no. Si es true y no existe la variable $_SESSION['User'], se suspende el acceso total. Si es false, se suspende el acceso independientemente de la existencia de $_SESSION['User'].
 *
 * La función realiza las siguientes operaciones:
 * - Si $isForced es true y no existe $_SESSION['User'], se establece $_SESSION['suspend'] en true y se genera un error 503 (Servicio no disponible).
 * - Si $isForced es false, se establece $_SESSION['suspend'] en true o se elimina si ya existe.
 *
 * @param bool $isForced Indica si la suspensión es forzada o no.
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
 * Valida el token de reCAPTCHA v3 de Google.
 *
 * Esta función valida el token de reCAPTCHA v3 proporcionado mediante $_POST. Los valores esperados son:
 * - token: El token de reCAPTCHA v3.
 * - action: El tipo de acción asociada al token.
 *
 * La función realiza una solicitud a la API de reCAPTCHA para verificar el token y compara los resultados con los parámetros proporcionados. Devuelve true si el puntaje (score) es mayor que 0.5 y la acción es la misma que la proporcionada. Devuelve false en caso contrario.
 *
 * @param string $typeAction Tipo de acción asociada al token.
 * @return bool Retorna true si el token es válido y la acción coincide, false en caso contrario.
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
 * Obtener la fecha en español.
 *
 * Esta función devuelve la fecha formateada en español. El parámetro opcional $date especifica la fecha que se desea formatear. Si no se proporciona, se utiliza la fecha actual.
 *
 * @param string $date Fecha a formatear. Por defecto es 'now' (fecha actual).
 * @return string La fecha formateada en español.
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
 * Si el valor es null, lo convierte en 0.
 *
 * Esta función toma un valor como argumento y verifica si es null. Si es así, devuelve 0; de lo contrario, devuelve el valor original.
 *
 * @param int $value El valor a verificar.
 * @return int El valor convertido en 0 si era null, de lo contrario, el valor original.
 */
function nullToZero($value)
{
    return ($value == null ? 0 : $value);
}


/**
 * Establece el estado en el encabezado HTTP.
 *
 * Esta función toma un código de estado como argumento y establece el encabezado HTTP correspondiente en función del código proporcionado.
 *
 * @param int $code El código de estado.
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
 * Crea directorios y subdirectorios.
 *
 * Esta función toma una ruta relativa como argumento y crea los directorios y subdirectorios correspondientes en esa ruta.
 *
 * @param mixed $relativePath La ruta relativa.
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
 * Obtiene el nombre del mes en español.
 *
 * Esta función toma un índice como argumento y devuelve el nombre del mes correspondiente en español.
 *
 * @param int $index El índice del array de meses.
 * @return string El nombre del mes en español.
 */
function getMonthInSpanish($index)
{
    $months = array("Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre");
    return $months[$index];
}

/**
 * Agrega comillas al inicio y al final de una cadena de texto.
 *
 * Esta función toma una cadena de texto como argumento y agrega comillas simples al inicio y al final de la cadena.
 * Si la cadena es NULL, devuelve la palabra "null" como texto.
 *
 * @param string $string La cadena de texto.
 * @return string La cadena de texto con comillas al inicio y al final, o "null" si la cadena es NULL.
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
 * Formatea un número como una cadena en formato de moneda.
 *
 * Esta función toma un número entero como argumento y devuelve una cadena que representa el número en formato de moneda.
 * La cadena incluye un símbolo de moneda (💲) seguido del número formateado con separadores de miles y punto decimal.
 *
 * @param int $number El número entero a formatear.
 * @return string La cadena formateada en formato de moneda.
 */
function formatCurrency($number) {
    return "💲 " . number_format($number, 0, ",", ".");
}

/**
 * Elimina un directorio y sus contenidos de forma recursiva.
 *
 * Esta función toma una ruta relativa como argumento y elimina el directorio y todos sus archivos y subdirectorios de forma recursiva.
 *
 * @param string $relativePath La ruta relativa del directorio a eliminar.
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
 * Convierte los archivos recibidos en $_FILES a objetos CURLFile.
 *
 * Esta función recorre los archivos recibidos en $_FILES y los convierte a objetos CURLFile. 
 * Los objetos CURLFile se utilizan para realizar transferencias de archivos con cURL.
 *
 * @return array Un arreglo de objetos CURLFile.
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