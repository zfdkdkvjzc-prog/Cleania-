<?php
header('Content-Type: application/json');
require 'db.php';

$result = $conexion->query(
    "SELECT id, direccion, telefono, fecha, rango_horario, recamaras, banos, tipo_limpieza, total
     FROM reservas
     WHERE estado = 'pendiente'
     ORDER BY fecha ASC"
);

$trabajos = [];
while ($fila = $result->fetch_assoc()) {
    $trabajos[] = $fila;
}

echo json_encode(["success" => true, "trabajos" => $trabajos]);

$conexion->close();
