<?php
require 'db.php';
$aliada = aliada_actual($conexion);

// Las aliadas solo ven lo que ellas ganan (nunca el precio del cliente ni la comisión).
// Trabajos disponibles: sin teléfono del cliente hasta que la aliada toma el trabajo
$result = $conexion->query(
    "SELECT id, direccion, fecha, rango_horario, recamaras, banos, tipo_limpieza, descripcion, pago_aliada
     FROM reservas
     WHERE estado = 'pendiente' AND fecha >= CURDATE()
     ORDER BY fecha ASC, id ASC"
);
$disponibles = $result->fetch_all(MYSQLI_ASSOC);

// Trabajos de esta aliada (con teléfono para que contacte al cliente)
$stmt = $conexion->prepare(
    "SELECT id, direccion, telefono, fecha, rango_horario, recamaras, banos, tipo_limpieza, descripcion, pago_aliada, estado
     FROM reservas
     WHERE asignada_a = ? AND estado IN ('asignada', 'completada') AND fecha >= (CURDATE() - INTERVAL 7 DAY)
     ORDER BY fecha ASC, id ASC"
);
$stmt->bind_param("s", $aliada['numero_aliada']);
$stmt->execute();
$misTrabajos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

responder(["success" => true, "trabajos" => $disponibles, "mis_trabajos" => $misTrabajos]);
