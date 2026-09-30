<?php
// Entrega un documento de aliada SOLO al administrador con sesión iniciada
define('RESPUESTA_HTML', true);
require 'db.php';
iniciar_sesion();

if (empty($_SESSION['admin'])) {
    http_response_code(403);
    exit('Acceso denegado.');
}

// basename() impide salir de la carpeta (../); también acepta rutas antiguas "uploads_aliadas/archivo.pdf"
$archivo = basename($_GET['f'] ?? '');
$ruta = UPLOADS_DIR . $archivo;

if ($archivo === '' || $archivo[0] === '.' || !is_file($ruta)) {
    http_response_code(404);
    exit('Documento no encontrado.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($ruta);
if (!in_array($mime, ['application/pdf', 'image/png', 'image/jpeg'], true)) {
    $mime = 'application/octet-stream';
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($ruta));
header('Content-Disposition: inline; filename="' . $archivo . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($ruta);
