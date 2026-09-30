<?php
// ==========================================
// CONEXIÓN A LA BASE DE DATOS MYSQL (GoDaddy cPanel)
// ==========================================
// 👇 Rellena estos 4 datos. Los encuentras en cPanel > Bases de datos MySQL
// (o en el correo que te llegó al crear la base de datos).
// El host casi siempre es "localhost" en GoDaddy cPanel.

$DB_HOST = "localhost";
$DB_NAME = "TU_USUARIO_cleania";   // GoDaddy antepone tu usuario de cPanel, ej: "abc123_cleania"
$DB_USER = "TU_USUARIO_cleania";   // igual aquí, el usuario que creaste para la base
$DB_PASS = "TU_CONTRASEÑA_AQUI";

$conexion = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conexion->connect_error) {
    http_response_code(500);
    die(json_encode(["success" => false, "message" => "Error de conexión a la base de datos."]));
}

$conexion->set_charset("utf8mb4");

// 👇 Contraseña para entrar al panel de administración (api/admin.php)
// Cámbiala por algo único y no la compartas.
define('ADMIN_PASSWORD', 'CAMBIA_ESTA_CLAVE_2026');

