<?php
// User configuration using environment variables and defaults.
// This file defines application constants and platform settings.
//
// @deprecated 0.9.9 Replaced by the config/*.php files (config/app.php,
// config/database.php, config/storage.php, config/queue.php), read via the
// config() helper. Kept for the legacy entry point; removed in 1.0.

//Configuración del usuario
date_default_timezone_set("America/Bogota");
//Configuración de acceso a la base de datos
//Local
define("HOST", "localhost");
define("USERNAME", "remoto");
define("PASSWORD", "123456");
define("DATABASE", "swiftfusephp_test");
define("LOCALDIR", "SwiftFusePHP/");
define("SSL_CA", "");

define("LANG", "es");
is_ssl(true);
define("CLAVE_RECAPTCHA_V3", "");

//Nombre del sitio
define("NOMBRESITIO", "");
//define("LOGO", (RUTA_URL . ""));
define("LOGO", (""));
//define("LOGOSVG", (RUTA_URL . ""));
define("LOGOSVG", (""));
define("EMAIL", "");
//Versión del sitio
define("VERSION", "0.1");
//Aqui puedes agregar las configuraciones de la plataforma/proyecto
//Ejemplo
function getEstados()
{
    return array("Inactivo", "Activo");
}
?>