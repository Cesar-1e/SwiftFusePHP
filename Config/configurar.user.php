<?php
//Configuración del usuario
date_default_timezone_set("America/Bogota");
//Configuración de acceso a la base de datos
//Local
define("HOST", "localhost");
define("USERNAME", "remoto");
define("PASSWORD", "123456");
define("DATABASE", "framework_test");
define("LOCALDIR", "Framework_PHP/");

define("LANG", "es");
is_ssl(false);
define("CLAVE_RECAPTCHA_V3", "");

//Nombre del sitio
define("NOMBRESITIO", "");
//define("LOGO", (RUTA_URL . ""));
define("LOGO", (""));
//define("LOGOSVG", (RUTA_URL . ""));
define("LOGOSVG", (""));
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