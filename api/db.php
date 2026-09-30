<?php
// ==========================================
// NÚCLEO COMPARTIDO: conexión, sesiones, validaciones y utilidades.
// Los datos de conexión y del administrador están en config.php.
// ==========================================

if (!file_exists(__DIR__ . '/config.php')) {
    http_response_code(500);
    die('Falta api/config.php. Copia api/config.example.php como config.php y rellena tus datos.');
}
require __DIR__ . '/config.php';

date_default_timezone_set(ZONA_HORARIA);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Cualquier error inesperado se registra en el log del servidor y el usuario solo ve un mensaje genérico.
set_exception_handler(function ($e) {
    error_log('[Cleania] ' . $e);
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (defined('RESPUESTA_HTML')) {
        echo '<p style="font-family:sans-serif;text-align:center;margin-top:60px">Ocurrió un error en el servidor. Intenta de nuevo en unos minutos.</p>';
    } else {
        echo json_encode(["success" => false, "message" => "Error en el servidor. Intenta de nuevo."]);
    }
    exit;
});

$conexion = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conexion->set_charset('utf8mb4');
$conexion->query("SET time_zone = '" . (new DateTime())->format('P') . "'");

// ------------------------------------------
// Tarifas: el cliente propone el precio y Cleania cobra solo una comisión sobre él.
// Se pueden ajustar en config.php; estos son los valores por defecto.
// ------------------------------------------
if (!defined('COMISION_PORCENTAJE')) define('COMISION_PORCENTAJE', 7);
if (!defined('PRECIO_MINIMO')) define('PRECIO_MINIMO', 300);
if (!defined('PRECIO_MAXIMO')) define('PRECIO_MAXIMO', 20000);

// Catálogo de servicios (debe coincidir con el formulario de index.html)
const TIPOS_LIMPIEZA = ['basica' => 'Limpieza Estándar', 'profunda' => 'Limpieza Profunda'];
const RANGOS_HORARIO = [
    'manana' => 'Mañana (08:00 AM - 12:00 PM)',
    'tarde'  => 'Tarde (12:00 PM - 04:00 PM)',
    'noche'  => 'Fin de Tarde (04:00 PM - 07:00 PM)',
];
const ESTADOS_RESERVA = ['pendiente', 'asignada', 'completada', 'cancelada'];

// Reparte el precio que propuso el cliente: comisión de Cleania y lo que recibe la aliada.
function calcular_reparto($precio) {
    $comision = round($precio * COMISION_PORCENTAJE / 100, 2);
    return ['comision' => $comision, 'pago_aliada' => round($precio - $comision, 2)];
}

function formato_dinero($monto) {
    return '$' . number_format((float)$monto, 2, '.', ',');
}

// ------------------------------------------
// Respuestas JSON
// ------------------------------------------
function responder($datos, $codigo = 200) {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos);
    exit;
}

function fail($msg, $codigo = 400) {
    responder(["success" => false, "message" => $msg], $codigo);
}

function exigir_post() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        fail("Método no permitido.", 405);
    }
}

// ------------------------------------------
// Validaciones
// ------------------------------------------
// Devuelve el celular con 10 dígitos o null si no es válido (acepta espacios, guiones y prefijo +52).
function normalizar_telefono($telefono) {
    $digitos = preg_replace('/\D/', '', $telefono);
    if (strlen($digitos) === 12 && substr($digitos, 0, 2) === '52') {
        $digitos = substr($digitos, 2);
    }
    return strlen($digitos) === 10 ? $digitos : null;
}

function longitud($texto) {
    return function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen($texto);
}

// ------------------------------------------
// Sesiones (cookie solo HTTP, segura en HTTPS y SameSite=Lax contra CSRF)
// ------------------------------------------
function iniciar_sesion() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('cleania_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

// Devuelve la aliada con sesión activa o responde 401. Revisa en cada petición que siga activa.
function aliada_actual($conexion) {
    iniciar_sesion();
    if (empty($_SESSION['aliada'])) {
        fail("Tu sesión expiró. Inicia sesión de nuevo.", 401);
    }
    $stmt = $conexion->prepare("SELECT numero_aliada, nombre FROM aliadas WHERE numero_aliada = ? AND activa = 1");
    $stmt->bind_param("s", $_SESSION['aliada']);
    $stmt->execute();
    $aliada = $stmt->get_result()->fetch_assoc();
    if (!$aliada) {
        unset($_SESSION['aliada']);
        fail("Tu cuenta no está activa. Contacta al administrador.", 401);
    }
    return $aliada;
}

// ------------------------------------------
// Límite de intentos por IP (login y formularios públicos)
// ------------------------------------------
function ip_cliente() {
    return substr($_SERVER['REMOTE_ADDR'] ?? 'desconocida', 0, 45);
}

function limite_superado($conexion, $tipo, $maximo, $minutos) {
    $ip = ip_cliente();
    $stmt = $conexion->prepare(
        "SELECT COUNT(*) AS total FROM intentos_login
         WHERE ip = ? AND tipo = ? AND creado_en > (NOW() - INTERVAL ? MINUTE)"
    );
    $stmt->bind_param("ssi", $ip, $tipo, $minutos);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['total'] >= $maximo;
}

function registrar_intento($conexion, $tipo) {
    $ip = ip_cliente();
    $stmt = $conexion->prepare("INSERT INTO intentos_login (ip, tipo) VALUES (?, ?)");
    $stmt->bind_param("ss", $ip, $tipo);
    $stmt->execute();
    // Limpieza ocasional de registros viejos
    if (random_int(1, 50) === 1) {
        $conexion->query("DELETE FROM intentos_login WHERE creado_en < (NOW() - INTERVAL 1 DAY)");
    }
}

function limpiar_intentos($conexion, $tipo) {
    $ip = ip_cliente();
    $stmt = $conexion->prepare("DELETE FROM intentos_login WHERE ip = ? AND tipo = ?");
    $stmt->bind_param("ss", $ip, $tipo);
    $stmt->execute();
}

// Contraseña aleatoria fácil de dictar por WhatsApp (sin 0/O, 1/l/I).
function generar_password($largo = 10) {
    $abc = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $password = '';
    for ($i = 0; $i < $largo; $i++) {
        $password .= $abc[random_int(0, strlen($abc) - 1)];
    }
    return $password;
}
