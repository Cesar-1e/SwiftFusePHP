<?php
error_reporting(0);
session_start();
require_once "Errores/log.php";
require_once "Config/configurar.php";
require_once "Config/env.php";
if (isset($argv)) {
    handler(E_ERROR, $argv[1], __FILE__, __LINE__);
    die();
}
require_once "Config/configurar.user.php";
//suspend(false);
require_once "Modelo/Conexion_Model.php";
require_once "Libreria/Controlador.php";
require_once "Libreria/Core.php";

$Core = new Core;
?>