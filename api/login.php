<?php
header('Content-Type: application/json');
require 'db.php';

$numero = trim($_POST['numero'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($numero === '' || $password === '') {
    echo json_encode(["success" => false, "message" => "Faltan datos."]);
    exit;
}

$stmt = $conexion->prepare("SELECT password_hash FROM aliadas WHERE numero_aliada = ? AND activa = 1");
$stmt->bind_param("s", $numero);
$stmt->execute();
$res = $stmt->get_result();

if ($fila = $res->fetch_assoc()) {
    if (password_verify($password, $fila['password_hash'])) {
        echo json_encode(["success" => true, "numero" => $numero]);
        exit;
    }
}

echo json_encode(["success" => false, "message" => "Número de aliada o contraseña incorrectos."]);

$stmt->close();
$conexion->close();
