<?php
require 'db.php';

$clave = $_GET['clave'] ?? $_POST['clave'] ?? '';
$autenticado = ($clave === ADMIN_PASSWORD);

$credencialesGeneradas = null;

// Procesar aprobación
if ($autenticado && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aprobar_id'])) {
    $id = intval($_POST['aprobar_id']);

    $stmt = $conexion->prepare("SELECT nombre, telefono FROM aliadas_pendientes WHERE id = ? AND estado = 'en_revision'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($fila = $res->fetch_assoc()) {
        // Generar siguiente número de aliada (empieza en 1001)
        $r = $conexion->query("SELECT MAX(CAST(numero_aliada AS UNSIGNED)) AS max_num FROM aliadas");
        $maxNum = $r->fetch_assoc()['max_num'];
        $siguienteNumero = $maxNum ? $maxNum + 1 : 1001;

        // Generar contraseña aleatoria de 8 caracteres
        $passwordPlano = substr(bin2hex(random_bytes(5)), 0, 8);
        $passwordHash = password_hash($passwordPlano, PASSWORD_DEFAULT);

        $ins = $conexion->prepare("INSERT INTO aliadas (numero_aliada, password_hash, nombre, telefono) VALUES (?, ?, ?, ?)");
        $ins->bind_param("ssss", $siguienteNumero, $passwordHash, $fila['nombre'], $fila['telefono']);
        $ins->execute();

        $upd = $conexion->prepare("UPDATE aliadas_pendientes SET estado = 'aprobada' WHERE id = ?");
        $upd->bind_param("i", $id);
        $upd->execute();

        $credencialesGeneradas = [
            'nombre' => $fila['nombre'],
            'telefono' => $fila['telefono'],
            'numero' => $siguienteNumero,
            'password' => $passwordPlano
        ];
    }
}

if (!$autenticado) {
    ?>
    <!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Admin Cleania</title></head>
    <body style="font-family: sans-serif; max-width:400px; margin:60px auto;">
        <h2>Panel Cleania</h2>
        <form method="get">
            <input type="password" name="clave" placeholder="Contraseña de administrador" style="padding:10px; width:100%; box-sizing:border-box;">
            <button type="submit" style="margin-top:10px; padding:10px 20px;">Entrar</button>
        </form>
    </body></html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Cleania - Aliadas</title>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 40px auto; padding: 0 20px; color:#0f172a; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { text-align: left; padding: 10px; border-bottom: 1px solid #e2e8f0; }
        button { background:#00b4d8; color:white; border:none; padding:8px 14px; border-radius:6px; cursor:pointer; }
        .credenciales { background:#dcfce7; border:1px solid #16a34a; padding:16px; border-radius:8px; margin-top:20px; }
        a.doc { color:#00b4d8; margin-right:8px; }
    </style>
</head>
<body>
    <h2>Solicitudes de Aliadas Pendientes</h2>

    <?php if ($credencialesGeneradas): ?>
        <div class="credenciales">
            <strong>¡Aprobada!</strong> Envíale estos datos a <?= htmlspecialchars($credencialesGeneradas['nombre']) ?>
            (<?= htmlspecialchars($credencialesGeneradas['telefono']) ?>) por WhatsApp:<br><br>
            Número de Aliada: <strong><?= $credencialesGeneradas['numero'] ?></strong><br>
            Contraseña: <strong><?= htmlspecialchars($credencialesGeneradas['password']) ?></strong>
        </div>
    <?php endif; ?>

    <table>
        <tr><th>Nombre</th><th>Teléfono</th><th>Documentos</th><th>Fecha</th><th></th></tr>
        <?php
        $result = $conexion->query("SELECT * FROM aliadas_pendientes WHERE estado = 'en_revision' ORDER BY creado_en DESC");
        while ($fila = $result->fetch_assoc()):
        ?>
        <tr>
            <td><?= htmlspecialchars($fila['nombre']) ?></td>
            <td><?= htmlspecialchars($fila['telefono']) ?></td>
            <td>
                <?php foreach (explode(',', $fila['documentos']) as $doc): ?>
                    <a class="doc" href="../<?= htmlspecialchars($doc) ?>" target="_blank">Ver</a>
                <?php endforeach; ?>
            </td>
            <td><?= htmlspecialchars($fila['creado_en']) ?></td>
            <td>
                <form method="post" style="margin:0;">
                    <input type="hidden" name="clave" value="<?= htmlspecialchars($clave) ?>">
                    <input type="hidden" name="aprobar_id" value="<?= $fila['id'] ?>">
                    <button type="submit">Aprobar</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
