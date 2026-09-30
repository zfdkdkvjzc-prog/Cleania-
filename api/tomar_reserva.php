<?php
header('Content-Type: application/json');
require 'db.php';

$id = intval($_POST['id'] ?? 0);
$numeroAliada = trim($_POST['numero_aliada'] ?? '');

if ($id === 0 || $numeroAliada === '') {
    echo json_encode(["success" => false, "message" => "Faltan datos."]);
    exit;
}

// Verifica que la aliada exista y esté activa
$stmt = $conexion->prepare("SELECT id FROM aliadas WHERE numero_aliada = ? AND activa = 1");
$stmt->bind_param("s", $numeroAliada);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Aliada no válida."]);
    exit;
}

// Solo toma el trabajo si SIGUE pendiente (evita que dos aliadas tomen el mismo)
$upd = $conexion->prepare("UPDATE reservas SET estado = 'asignada', asignada_a = ? WHERE id = ? AND estado = 'pendiente'");
$upd->bind_param("si", $numeroAliada, $id);
$upd->execute();

if ($upd->affected_rows > 0) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => "Este trabajo ya fue tomado por otra aliada."]);
}

$upd->close();
$conexion->close();
