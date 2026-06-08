<?php
// User configuration using environment variables and defaults.
// This file defines application constants and platform settings.

date_default_timezone_set(env('APP_TIMEZONE', 'America/Bogota'));
define('APP_ENV', env('APP_ENV', 'production'));
define('APP_DEBUG', env('APP_DEBUG', true));
define('APP_NAME', env('APP_NAME', 'SwiftFusePHP'));
define('APP_VERSION', env('APP_VERSION', '0.1'));
define('APP_LOCALE', env('APP_LOCALE', 'en'));
define('STORAGE_PATH', RUTA_APP . rtrim(env('STORAGE_PATH', 'storage/'), '/') . '/');

define('HOST', env('DB_HOST', 'localhost'));
define('USERNAME', env('DB_USERNAME', 'remoto'));
define('PASSWORD', env('DB_PASSWORD', '123456'));
define('DATABASE', env('DB_DATABASE', 'swiftfusephp_test'));
define('LOCALDIR', env('LOCALDIR', 'SwiftFusePHP/'));
define('SSL_CA', env('DB_SSL_CA', ''));

$isSsl = env('FORCE_SSL', true);
is_ssl($isSsl);

define('RECAPTCHA_KEY', env('RECAPTCHA_KEY', ''));

define('SITE_NAME', env('APP_NAME', ''));
define('LOGO', env('APP_LOGO', ''));
define('LOGOSVG', env('APP_LOGOSVG', ''));
define('EMAIL', env('APP_EMAIL', ''));

define('VERSION', APP_VERSION);

// Application specific configuration can be added here.
function getEstados()
{
    return array('Inactive', 'Active');
}
?>