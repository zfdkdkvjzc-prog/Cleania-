<?php
header('Content-Type: application/json');
require 'db.php';

function fail($msg) {
    echo json_encode(["success" => false, "message" => $msg]);
    exit;
}

$nombre   = trim($_POST['name'] ?? '');
$telefono = trim($_POST['phone'] ?? '');

if ($nombre === '' || $telefono === '') {
    fail("Faltan datos obligatorios.");
}

if (empty($_FILES['documentos']) || count($_FILES['documentos']['name']) === 0) {
    fail("Debes subir al menos un documento.");
}

// Carpeta donde se guardan los documentos (debe existir y tener permisos de escritura, 755)
$carpetaDestino = __DIR__ . '/../uploads_aliadas/';
$extensionesPermitidas = ['pdf', 'png', 'jpg', 'jpeg'];
$rutasGuardadas = [];

$archivos = $_FILES['documentos'];
$totalArchivos = count($archivos['name']);

for ($i = 0; $i < $totalArchivos; $i++) {
    if ($archivos['error'][$i] !== UPLOAD_ERR_OK) {
        continue;
    }

    $nombreOriginal = basename($archivos['name'][$i]);
    $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

    if (!in_array($extension, $extensionesPermitidas)) {
        continue; // Ignora tipos de archivo no permitidos
    }

    $nombreUnico = time() . '_' . $i . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombreOriginal);
    $rutaDestino = $carpetaDestino . $nombreUnico;

    if (move_uploaded_file($archivos['tmp_name'][$i], $rutaDestino)) {
        $rutasGuardadas[] = 'uploads_aliadas/' . $nombreUnico;
    }
}

if (count($rutasGuardadas) === 0) {
    fail("No se pudo subir ningún documento válido (solo se permiten PDF, PNG, JPG).");
}

$documentosTexto = implode(',', $rutasGuardadas);

$stmt = $conexion->prepare(
    "INSERT INTO aliadas_pendientes (nombre, telefono, documentos) VALUES (?, ?, ?)"
);
$stmt->bind_param("sss", $nombre, $telefono, $documentosTexto);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    fail("No se pudo guardar el registro.");
}

$stmt->close();
$conexion->close();
