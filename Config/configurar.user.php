<?php
//Configuración del usuario
date_default_timezone_set("America/Bogota");
//Configuración de acceso a la base de datos
//Local
define("HOST", "localhost");
define("USERNAME", "root");
define("PASSWORD", "");
define("DATABASE", "");
define("LOCALDIR", "Framework_PHP/");

define("LANG", "es");
define("IS_SSL", false);
define("CLAVE_RECAPTCHA_V3", "");

//Nombre del sitio
define("NOMBRESITIO", "");
define("LOGO", (RUTA_URL . ""));
define("LOGOSVG", (RUTA_URL . ""));
define("EMAIL", "");
//Versión del sitio
define("VERSION", "PreAlpha");
//Aqui puedes agregar las configuraciones de la plataforma/proyecto
//Ejemplo
function getEstados()
{
    return array("Inactivo", "Activo");
}
?>