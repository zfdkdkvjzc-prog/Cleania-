<?php
require 'db.php';
exigir_post();

const MAX_ARCHIVOS = 5;
const MAX_BYTES = 5 * 1024 * 1024; // 5 MB por archivo
const TIPOS_PERMITIDOS = ['application/pdf' => 'pdf', 'image/png' => 'png', 'image/jpeg' => 'jpg'];

// Si el envío supera post_max_size de PHP, $_POST y $_FILES llegan vacíos
if (empty($_POST) && empty($_FILES) && ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    fail("Los archivos son demasiado grandes. Máximo 5 MB por archivo.");
}

if (limite_superado($conexion, 'aliada_registro', 5, 60)) {
    fail("Has enviado demasiadas solicitudes. Intenta más tarde.", 429);
}

$nombre   = trim($_POST['name'] ?? '');
$telefono = normalizar_telefono($_POST['phone'] ?? '');

if (longitud($nombre) < 5 || longitud($nombre) > 255) {
    fail("Escribe tu nombre completo como aparece en tu INE.");
}
if ($telefono === null) {
    fail("El teléfono (WhatsApp) debe tener 10 dígitos.");
}
if (empty($_FILES['documentos']) || !is_array($_FILES['documentos']['name'])) {
    fail("Debes subir al menos un documento.");
}

$archivos = $_FILES['documentos'];
$totalArchivos = count($archivos['name']);
if ($totalArchivos > MAX_ARCHIVOS) {
    fail("Puedes subir máximo " . MAX_ARCHIVOS . " documentos.");
}

if (!is_dir(UPLOADS_DIR) || !is_writable(UPLOADS_DIR)) {
    error_log('[Cleania] La carpeta de documentos no existe o no tiene permisos de escritura: ' . UPLOADS_DIR);
    fail("No pudimos recibir tus documentos en este momento. Intenta más tarde.", 500);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$guardados = [];

for ($i = 0; $i < $totalArchivos; $i++) {
    if ($archivos['error'][$i] === UPLOAD_ERR_INI_SIZE || $archivos['error'][$i] === UPLOAD_ERR_FORM_SIZE
        || $archivos['size'][$i] > MAX_BYTES) {
        fail("El archivo \"" . basename($archivos['name'][$i]) . "\" pesa más de 5 MB.");
    }
    if ($archivos['error'][$i] !== UPLOAD_ERR_OK) {
        continue;
    }
    // Se revisa el contenido real del archivo, no solo su extensión
    $mime = $finfo->file($archivos['tmp_name'][$i]);
    if (!isset(TIPOS_PERMITIDOS[$mime])) {
        continue;
    }
    // Nombre aleatorio: imposible de adivinar y sin datos del archivo original
    $nombreArchivo = bin2hex(random_bytes(16)) . '.' . TIPOS_PERMITIDOS[$mime];
    if (move_uploaded_file($archivos['tmp_name'][$i], UPLOADS_DIR . $nombreArchivo)) {
        $guardados[] = $nombreArchivo;
    }
}

if (count($guardados) === 0) {
    fail("No se pudo subir ningún documento válido (solo se permiten PDF, PNG y JPG).");
}

$documentosTexto = implode(',', $guardados);

try {
    $stmt = $conexion->prepare("INSERT INTO aliadas_pendientes (nombre, telefono, documentos) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $nombre, $telefono, $documentosTexto);
    $stmt->execute();
} catch (Throwable $e) {
    foreach ($guardados as $archivo) {
        @unlink(UPLOADS_DIR . $archivo);
    }
    throw $e;
}
registrar_intento($conexion, 'aliada_registro');

responder(["success" => true]);
