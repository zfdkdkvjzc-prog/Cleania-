<?php
require 'db.php';
exigir_post();

if (limite_superado($conexion, 'reserva', 10, 60)) {
    fail("Has enviado demasiadas solicitudes. Intenta más tarde o contáctanos por WhatsApp.", 429);
}

$direccion = trim($_POST['address'] ?? '');
$telefono  = normalizar_telefono($_POST['phone'] ?? '');
$fecha     = trim($_POST['date'] ?? '');
$rango     = trim($_POST['timeRange'] ?? '');
$recamaras = intval($_POST['rooms'] ?? 0);
$banos     = intval($_POST['baths'] ?? 0);
$tipo      = trim($_POST['type'] ?? '');

if ($direccion === '' || $fecha === '' || $rango === '') {
    fail("Faltan datos obligatorios.");
}
if (longitud($direccion) < 10 || longitud($direccion) > 255) {
    fail("Escribe tu dirección completa (calle, número, colonia y código postal).");
}
if ($telefono === null) {
    fail("El celular debe tener 10 dígitos.");
}
if (!isset(RANGOS_HORARIO[$rango]) || !isset(TIPOS_LIMPIEZA[$tipo])) {
    fail("Selecciona un horario y tipo de limpieza válidos.");
}
if ($recamaras < 1 || $recamaras > 4 || $banos < 1 || $banos > 3) {
    fail("Número de recámaras o baños no válido.");
}

$fechaServicio = DateTime::createFromFormat('!Y-m-d', $fecha);
$hoy = new DateTime('today');
if (!$fechaServicio || $fechaServicio->format('Y-m-d') !== $fecha) {
    fail("La fecha no es válida.");
}
if ($fechaServicio < $hoy || $fechaServicio > (clone $hoy)->modify('+90 days')) {
    fail("Elige una fecha entre hoy y los próximos 90 días.");
}

// El precio SIEMPRE se calcula en el servidor
$total = '$' . number_format(calcular_total($recamaras, $banos, $tipo), 2, '.', '');

$stmt = $conexion->prepare(
    "INSERT INTO reservas (direccion, telefono, fecha, rango_horario, recamaras, banos, tipo_limpieza, total)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param("ssssiiss", $direccion, $telefono, $fecha, $rango, $recamaras, $banos, $tipo, $total);
$stmt->execute();
registrar_intento($conexion, 'reserva');

responder(["success" => true, "total" => $total]);
