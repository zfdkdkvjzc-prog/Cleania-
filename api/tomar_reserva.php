<?php
require 'db.php';
exigir_post();
$aliada = aliada_actual($conexion);

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    fail("Faltan datos.");
}

// Solo toma el trabajo si SIGUE pendiente (evita que dos aliadas tomen el mismo).
// El número de aliada sale de la sesión, nunca del navegador.
$upd = $conexion->prepare(
    "UPDATE reservas SET estado = 'asignada', asignada_a = ?
     WHERE id = ? AND estado = 'pendiente' AND fecha >= CURDATE()"
);
$upd->bind_param("si", $aliada['numero_aliada'], $id);
$upd->execute();

if ($upd->affected_rows > 0) {
    responder(["success" => true]);
}
fail("Este trabajo ya fue tomado por otra aliada.", 409);
