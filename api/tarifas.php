<?php
// Datos públicos de tarifas para el formulario de reserva (sin conexión a la base)
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/config.php';
if (!defined('COMISION_PORCENTAJE')) define('COMISION_PORCENTAJE', 7);
if (!defined('PRECIO_MINIMO')) define('PRECIO_MINIMO', 300);
if (!defined('PRECIO_MAXIMO')) define('PRECIO_MAXIMO', 20000);

echo json_encode([
    "success"  => true,
    "comision" => COMISION_PORCENTAJE,
    "minimo"   => PRECIO_MINIMO,
    "maximo"   => PRECIO_MAXIMO,
]);
