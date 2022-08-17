<?php
error_reporting(0);
session_start();
require_once "Errores/log.php";
require_once "Config/configurar.php";
require_once "Config/configurar.user.php";
require_once "Modelo/Conexion_Model.php";
require_once "Libreria/Controlador.php";
require_once "Libreria/Core.php";

$Core = new Core;
?>