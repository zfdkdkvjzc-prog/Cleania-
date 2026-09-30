<?php
header('Content-Type: application/json');
require 'db.php';

function fail($msg) {
    echo json_encode(["success" => false, "message" => $msg]);
    exit;
}

$direccion = trim($_POST['address'] ?? '');
$telefono  = trim($_POST['phone'] ?? '');
$fecha     = trim($_POST['date'] ?? '');
$rango     = trim($_POST['timeRange'] ?? '');
$recamaras = intval($_POST['rooms'] ?? 0);
$banos     = intval($_POST['baths'] ?? 0);
$tipo      = trim($_POST['type'] ?? '');
$total     = trim($_POST['total'] ?? '');

if ($direccion === '' || $telefono === '' || $fecha === '' || $rango === '') {
    fail("Faltan datos obligatorios.");
}

$stmt = $conexion->prepare(
    "INSERT INTO reservas (direccion, telefono, fecha, rango_horario, recamaras, banos, tipo_limpieza, total)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param("ssssiiss", $direccion, $telefono, $fecha, $rango, $recamaras, $banos, $tipo, $total);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    fail("No se pudo guardar la reserva.");
}

$stmt->close();
$conexion->close();
