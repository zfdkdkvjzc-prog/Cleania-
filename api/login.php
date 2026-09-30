<?php
require 'db.php';
exigir_post();
iniciar_sesion();

if (limite_superado($conexion, 'aliada', 5, 15)) {
    fail("Demasiados intentos fallidos. Espera 15 minutos e intenta de nuevo.", 429);
}

$numero = trim($_POST['numero'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($numero === '' || $password === '') {
    fail("Faltan datos.");
}

$stmt = $conexion->prepare("SELECT numero_aliada, nombre, password_hash FROM aliadas WHERE numero_aliada = ? AND activa = 1");
$stmt->bind_param("s", $numero);
$stmt->execute();
$aliada = $stmt->get_result()->fetch_assoc();

if (!$aliada || !password_verify($password, $aliada['password_hash'])) {
    registrar_intento($conexion, 'aliada');
    fail("Número de aliada o contraseña incorrectos.", 401);
}

limpiar_intentos($conexion, 'aliada');
session_regenerate_id(true);
$_SESSION['aliada'] = $aliada['numero_aliada'];

responder(["success" => true, "numero" => $aliada['numero_aliada'], "nombre" => $aliada['nombre']]);
