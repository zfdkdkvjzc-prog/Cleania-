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
$descripcion = trim($_POST['description'] ?? '');
// Acepta "1200", "1,200.50" o "$1200"
$precioTexto = str_replace(['$', ',', ' '], '', $_POST['price'] ?? '');

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

if (longitud($descripcion) > 1000) {
    fail("La descripción puede tener máximo 1000 caracteres.");
}
if (!is_numeric($precioTexto)) {
    fail("Escribe cuánto ofreces pagar por el servicio.");
}
$precio = round((float)$precioTexto, 2);
if ($precio < PRECIO_MINIMO || $precio > PRECIO_MAXIMO) {
    fail("El precio debe estar entre " . formato_dinero(PRECIO_MINIMO) . " y " . formato_dinero(PRECIO_MAXIMO) . ".");
}

$fechaServicio = DateTime::createFromFormat('!Y-m-d', $fecha);
$hoy = new DateTime('today');
if (!$fechaServicio || $fechaServicio->format('Y-m-d') !== $fecha) {
    fail("La fecha no es válida.");
}
if ($fechaServicio < $hoy || $fechaServicio > (clone $hoy)->modify('+90 days')) {
    fail("Elige una fecha entre hoy y los próximos 90 días.");
}

// El cliente propone el precio; la comisión y el pago de la aliada se calculan en el servidor
$reparto = calcular_reparto($precio);
$total = formato_dinero($precio);

$stmt = $conexion->prepare(
    "INSERT INTO reservas (direccion, telefono, fecha, rango_horario, recamaras, banos, tipo_limpieza, descripcion,
                           precio, comision, pago_aliada, total)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param("ssssiissddds", $direccion, $telefono, $fecha, $rango, $recamaras, $banos, $tipo, $descripcion,
                  $precio, $reparto['comision'], $reparto['pago_aliada'], $total);
$stmt->execute();
registrar_intento($conexion, 'reserva');

responder(["success" => true, "total" => $total]);
