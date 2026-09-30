<?php
// Indica al navegador si hay una aliada con sesión iniciada
require 'db.php';
iniciar_sesion();

if (empty($_SESSION['aliada'])) {
    responder(["success" => true, "logged" => false]);
}
$aliada = aliada_actual($conexion);
responder(["success" => true, "logged" => true, "numero" => $aliada['numero_aliada'], "nombre" => $aliada['nombre']]);
