<?php
// ==========================================
// PANEL DE ADMINISTRACIÓN DE CLEANIA
// Reservas, solicitudes de aliadas, aliadas y cuenta del administrador.
// ==========================================
define('RESPUESTA_HTML', true);
require 'db.php';
iniciar_sesion();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

const INACTIVIDAD_MAX = 2 * 60 * 60; // 2 horas

function e($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

function redirigir($tab, $extra = '') {
    header('Location: admin.php?tab=' . urlencode($tab) . $extra);
    exit;
}

function avisar($tipo, $mensaje, $credenciales = null) {
    $_SESSION['flash'] = ['tipo' => $tipo, 'mensaje' => $mensaje, 'credenciales' => $credenciales];
}

function enlace_whatsapp($telefono, $texto = '') {
    return 'https://wa.me/52' . preg_replace('/\D/', '', $telefono) . ($texto !== '' ? '?text=' . rawurlencode($texto) : '');
}

function mensaje_credenciales($nombre, $numero, $password) {
    return "¡Hola $nombre! Bienvenida a Cleania ✨\n\nTus datos para entrar al Área Profesional son:\n"
        . "Número de Aliada: $numero\nContraseña: $password\n\nTe recomendamos no compartirlos con nadie.";
}

// Cierra la sesión de admin si estuvo inactiva demasiado tiempo
if (!empty($_SESSION['admin']) && (time() - ($_SESSION['admin_actividad'] ?? 0)) > INACTIVIDAD_MAX) {
    unset($_SESSION['admin'], $_SESSION['admin_actividad']);
    avisar('error', 'Tu sesión se cerró por inactividad.');
}
if (!empty($_SESSION['admin'])) {
    $_SESSION['admin_actividad'] = time();
}
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$accion = $_POST['accion'] ?? '';
$tab = $_POST['tab'] ?? ($_GET['tab'] ?? 'reservas');

// ------------------------------------------
// ACCIONES (todas por POST, con token CSRF)
// ------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        avisar('error', 'La página expiró. Intenta de nuevo.');
        redirigir($tab);
    }

    // ---- Login ----
    if ($accion === 'login') {
        if (limite_superado($conexion, 'admin', 5, 15)) {
            avisar('error', 'Demasiados intentos fallidos. Espera 15 minutos.');
            redirigir('reservas');
        }
        $usuario = trim($_POST['usuario'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $conexion->prepare("SELECT id, usuario, password_hash FROM administradores WHERE usuario = ?");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();

        // Primera vez: si no hay administradores en la base, se crea el de config.php
        if (!$admin && $usuario === ADMIN_USUARIO
            && (int)$conexion->query("SELECT COUNT(*) AS n FROM administradores")->fetch_assoc()['n'] === 0
            && password_verify($password, ADMIN_PASSWORD_HASH)) {
            $ins = $conexion->prepare("INSERT INTO administradores (usuario, password_hash) VALUES (?, ?)");
            $hashInicial = ADMIN_PASSWORD_HASH;
            $ins->bind_param("ss", $usuario, $hashInicial);
            $ins->execute();
            $admin = ['id' => $conexion->insert_id, 'usuario' => $usuario, 'password_hash' => ADMIN_PASSWORD_HASH];
        }

        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            registrar_intento($conexion, 'admin');
            avisar('error', 'Usuario o contraseña incorrectos.');
            redirigir('reservas');
        }

        limpiar_intentos($conexion, 'admin');
        session_regenerate_id(true);
        $_SESSION['admin'] = (int)$admin['id'];
        $_SESSION['admin_usuario'] = $admin['usuario'];
        $_SESSION['admin_actividad'] = time();
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        redirigir('reservas');
    }

    // Todo lo demás requiere sesión de administrador
    if (empty($_SESSION['admin'])) {
        redirigir('reservas');
    }

    switch ($accion) {
        case 'logout':
            unset($_SESSION['admin'], $_SESSION['admin_usuario'], $_SESSION['admin_actividad']);
            session_regenerate_id(true);
            redirigir('reservas');

        // ---- Reservas ----
        case 'reserva_estado':
            $id = intval($_POST['id'] ?? 0);
            $estado = $_POST['estado'] ?? '';
            if (!in_array($estado, ESTADOS_RESERVA, true)) {
                avisar('error', 'Estado no válido.');
                break;
            }
            if ($estado === 'pendiente') {
                // Volver a pendiente libera el trabajo para que otra aliada lo tome
                $stmt = $conexion->prepare("UPDATE reservas SET estado = 'pendiente', asignada_a = NULL WHERE id = ?");
                $stmt->bind_param("i", $id);
            } elseif ($estado === 'asignada') {
                $stmt = $conexion->prepare("UPDATE reservas SET estado = 'asignada' WHERE id = ? AND asignada_a IS NOT NULL");
                $stmt->bind_param("i", $id);
            } else {
                $stmt = $conexion->prepare("UPDATE reservas SET estado = ? WHERE id = ?");
                $stmt->bind_param("si", $estado, $id);
            }
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                avisar('ok', "Reserva #$id actualizada a \"$estado\".");
            } else {
                avisar('error', $estado === 'asignada'
                    ? "Para marcar la reserva #$id como asignada, primero elige una aliada."
                    : "La reserva #$id no cambió.");
            }
            break;

        case 'reserva_asignar':
            $id = intval($_POST['id'] ?? 0);
            $numero = trim($_POST['numero_aliada'] ?? '');
            if ($numero === '') {
                $stmt = $conexion->prepare("UPDATE reservas SET estado = 'pendiente', asignada_a = NULL WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                avisar('ok', "Reserva #$id liberada: vuelve a estar disponible para las aliadas.");
                break;
            }
            $stmt = $conexion->prepare("SELECT nombre FROM aliadas WHERE numero_aliada = ? AND activa = 1");
            $stmt->bind_param("s", $numero);
            $stmt->execute();
            $aliada = $stmt->get_result()->fetch_assoc();
            if (!$aliada) {
                avisar('error', 'La aliada no existe o está desactivada.');
                break;
            }
            $stmt = $conexion->prepare("UPDATE reservas SET estado = 'asignada', asignada_a = ? WHERE id = ?");
            $stmt->bind_param("si", $numero, $id);
            $stmt->execute();
            avisar('ok', "Reserva #$id asignada a {$aliada['nombre']} ($numero).");
            break;

        // ---- Solicitudes ----
        case 'solicitud_aprobar':
            $id = intval($_POST['id'] ?? 0);
            $stmt = $conexion->prepare("SELECT nombre, telefono FROM aliadas_pendientes WHERE id = ? AND estado = 'en_revision'");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $solicitud = $stmt->get_result()->fetch_assoc();
            if (!$solicitud) {
                avisar('error', 'Esa solicitud ya fue procesada.');
                break;
            }

            $password = generar_password();
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Número consecutivo desde 1001; si dos aprobaciones chocan, se reintenta con el siguiente
            for ($intento = 0; $intento < 5; $intento++) {
                $maximo = $conexion->query("SELECT MAX(CAST(numero_aliada AS UNSIGNED)) AS m FROM aliadas")->fetch_assoc()['m'];
                $numero = (string)($maximo ? $maximo + 1 : 1001);
                try {
                    $ins = $conexion->prepare("INSERT INTO aliadas (numero_aliada, password_hash, nombre, telefono) VALUES (?, ?, ?, ?)");
                    $ins->bind_param("ssss", $numero, $hash, $solicitud['nombre'], $solicitud['telefono']);
                    $ins->execute();
                    break;
                } catch (mysqli_sql_exception $ex) {
                    if ($ex->getCode() !== 1062 || $intento === 4) {
                        throw $ex;
                    }
                }
            }

            $upd = $conexion->prepare("UPDATE aliadas_pendientes SET estado = 'aprobada' WHERE id = ?");
            $upd->bind_param("i", $id);
            $upd->execute();

            avisar('ok', "¡{$solicitud['nombre']} fue aprobada!", [
                'nombre' => $solicitud['nombre'], 'telefono' => $solicitud['telefono'],
                'numero' => $numero, 'password' => $password,
            ]);
            $tab = 'aliadas';
            break;

        case 'solicitud_rechazar':
            $id = intval($_POST['id'] ?? 0);
            $stmt = $conexion->prepare("SELECT nombre, documentos FROM aliadas_pendientes WHERE id = ? AND estado = 'en_revision'");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $solicitud = $stmt->get_result()->fetch_assoc();
            if (!$solicitud) {
                avisar('error', 'Esa solicitud ya fue procesada.');
                break;
            }
            // Se borran los documentos: no hay motivo para conservar datos personales de una solicitud rechazada
            foreach (array_filter(explode(',', $solicitud['documentos'])) as $doc) {
                $ruta = UPLOADS_DIR . basename($doc);
                if (is_file($ruta)) {
                    @unlink($ruta);
                }
            }
            $upd = $conexion->prepare("UPDATE aliadas_pendientes SET estado = 'rechazada', documentos = '' WHERE id = ?");
            $upd->bind_param("i", $id);
            $upd->execute();
            avisar('ok', "Solicitud de {$solicitud['nombre']} rechazada y sus documentos eliminados.");
            break;

        // ---- Aliadas ----
        case 'aliada_activar':
        case 'aliada_desactivar':
            $numero = trim($_POST['numero_aliada'] ?? '');
            $activa = $accion === 'aliada_activar' ? 1 : 0;
            $stmt = $conexion->prepare("UPDATE aliadas SET activa = ? WHERE numero_aliada = ?");
            $stmt->bind_param("is", $activa, $numero);
            $stmt->execute();
            avisar('ok', "Aliada $numero " . ($activa ? 'reactivada.' : 'desactivada: ya no puede entrar ni tomar trabajos.'));
            break;

        case 'aliada_reset':
            $numero = trim($_POST['numero_aliada'] ?? '');
            $stmt = $conexion->prepare("SELECT nombre, telefono FROM aliadas WHERE numero_aliada = ?");
            $stmt->bind_param("s", $numero);
            $stmt->execute();
            $aliada = $stmt->get_result()->fetch_assoc();
            if (!$aliada) {
                avisar('error', 'Aliada no encontrada.');
                break;
            }
            $password = generar_password();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conexion->prepare("UPDATE aliadas SET password_hash = ? WHERE numero_aliada = ?");
            $stmt->bind_param("ss", $hash, $numero);
            $stmt->execute();
            avisar('ok', "Nueva contraseña generada para {$aliada['nombre']}.", [
                'nombre' => $aliada['nombre'], 'telefono' => $aliada['telefono'],
                'numero' => $numero, 'password' => $password,
            ]);
            break;

        // ---- Cuenta del administrador ----
        case 'cambiar_password':
            $actual = $_POST['actual'] ?? '';
            $nueva = $_POST['nueva'] ?? '';
            $confirmar = $_POST['confirmar'] ?? '';
            $stmt = $conexion->prepare("SELECT password_hash FROM administradores WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['admin']);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            if (!$admin || !password_verify($actual, $admin['password_hash'])) {
                avisar('error', 'La contraseña actual no es correcta.');
            } elseif (strlen($nueva) < 12) {
                avisar('error', 'La nueva contraseña debe tener al menos 12 caracteres.');
            } elseif ($nueva !== $confirmar) {
                avisar('error', 'La confirmación no coincide con la nueva contraseña.');
            } else {
                $hash = password_hash($nueva, PASSWORD_DEFAULT);
                $stmt = $conexion->prepare("UPDATE administradores SET password_hash = ? WHERE id = ?");
                $stmt->bind_param("si", $hash, $_SESSION['admin']);
                $stmt->execute();
                avisar('ok', 'Contraseña actualizada. Úsala la próxima vez que entres.');
            }
            break;

        default:
            avisar('error', 'Acción no reconocida.');
    }

    $filtro = isset($_POST['filtro']) ? '&estado=' . urlencode($_POST['filtro']) : '';
    redirigir($tab, $filtro);
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$csrf = $_SESSION['csrf'];

// ------------------------------------------
// VISTA
// ------------------------------------------
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Panel Cleania</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
        header { background: #0f172a; color: white; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        header .logo { font-size: 20px; font-weight: 800; }
        header .logo span { color: #00b4d8; }
        main { max-width: 1200px; margin: 0 auto; padding: 20px 16px 60px; }
        nav.tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 20px; }
        nav.tabs a { padding: 9px 16px; border-radius: 999px; text-decoration: none; color: #334155; background: #e2e8f0; font-weight: 600; font-size: 14px; }
        nav.tabs a.activa { background: #00b4d8; color: white; }
        .contador { display: inline-block; min-width: 20px; padding: 0 6px; margin-left: 4px; border-radius: 999px; background: #ef4444; color: white; font-size: 12px; text-align: center; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .stat { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; }
        .stat b { display: block; font-size: 26px; }
        .stat small { color: #64748b; }
        .tarjeta { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 12px; }
        .fila { display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        .muted { color: #64748b; font-size: 14px; }
        .acciones { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        form.inline { display: inline-flex; gap: 6px; align-items: center; margin: 0; }
        button, .boton { background: #00b4d8; color: white; border: none; padding: 8px 14px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-block; }
        button.secundario, .boton.secundario { background: #e2e8f0; color: #0f172a; }
        button.peligro { background: #ef4444; }
        .boton.wa { background: #16a34a; }
        select, input[type=text], input[type=password] { padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; background: white; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .b-pendiente { background: #fef3c7; color: #92400e; }
        .b-asignada { background: #dbeafe; color: #1e40af; }
        .b-completada { background: #dcfce7; color: #166534; }
        .b-cancelada { background: #f1f5f9; color: #64748b; }
        .b-inactiva { background: #fee2e2; color: #991b1b; }
        .aviso { padding: 14px 16px; border-radius: 10px; margin-bottom: 16px; }
        .aviso.ok { background: #dcfce7; border: 1px solid #16a34a; }
        .aviso.error { background: #fee2e2; border: 1px solid #ef4444; }
        .credenciales { background: white; border: 1px dashed #16a34a; border-radius: 8px; padding: 12px; margin-top: 10px; font-family: ui-monospace, monospace; }
        .filtros { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 14px; }
        .filtros a { font-size: 13px; padding: 5px 12px; border-radius: 999px; border: 1px solid #cbd5e1; color: #334155; text-decoration: none; }
        .filtros a.activa { background: #0f172a; color: white; border-color: #0f172a; }
        .vacio { text-align: center; color: #64748b; padding: 40px; background: white; border-radius: 12px; border: 1px solid #e2e8f0; }
        .login { max-width: 380px; margin: 80px auto; background: white; padding: 28px; border-radius: 16px; border: 1px solid #e2e8f0; }
        .login input { width: 100%; margin-top: 10px; padding: 12px; }
        .login button { width: 100%; margin-top: 14px; padding: 12px; }
        .cuenta { max-width: 420px; }
        .descripcion { margin-top: 6px; padding: 8px 10px; background: #f1f5f9; border-radius: 8px; font-size: 14px; color: #334155; max-width: 640px; white-space: normal; overflow-wrap: anywhere; }
        .reparto { text-align: right; font-size: 14px; }
        .reparto div { display: flex; justify-content: flex-end; gap: 10px; align-items: baseline; }
        .reparto span { color: #64748b; }
        .cuenta label { display: block; margin-top: 12px; font-size: 14px; font-weight: 600; }
        .cuenta input { width: 100%; margin-top: 4px; }
    </style>
</head>
<body>

<?php if (empty($_SESSION['admin'])): ?>
    <div class="login">
        <h2 style="margin-top:0">Panel <span style="color:#00b4d8">Cleania</span></h2>
        <?php if ($flash): ?><div class="aviso <?= e($flash['tipo']) ?>"><?= e($flash['mensaje']) ?></div><?php endif; ?>
        <form method="post" action="admin.php">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="accion" value="login">
            <input type="text" name="usuario" placeholder="Usuario" autocomplete="username" required autofocus>
            <input type="password" name="password" placeholder="Contraseña" autocomplete="current-password" required>
            <button type="submit">Entrar</button>
        </form>
        <p class="muted" style="text-align:center;margin-bottom:0"><a href="../index.html" style="color:#00b4d8">← Volver al sitio</a></p>
    </div>
</body></html>
<?php exit; endif; ?>

<?php
// Datos para el panel
$stats = $conexion->query(
    "SELECT
        (SELECT COUNT(*) FROM reservas WHERE estado = 'pendiente') AS pendientes,
        (SELECT COUNT(*) FROM reservas WHERE estado = 'asignada') AS asignadas,
        (SELECT COUNT(*) FROM aliadas_pendientes WHERE estado = 'en_revision') AS solicitudes,
        (SELECT COUNT(*) FROM aliadas WHERE activa = 1) AS aliadas_activas,
        (SELECT COALESCE(SUM(comision), 0) FROM reservas WHERE estado = 'completada') AS comisiones,
        (SELECT COALESCE(SUM(comision), 0) FROM reservas WHERE estado = 'asignada') AS comisiones_por_cobrar"
)->fetch_assoc();

$aliadasActivas = $conexion->query("SELECT numero_aliada, nombre FROM aliadas WHERE activa = 1 ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
$nombresAliadas = $conexion->query("SELECT numero_aliada, nombre FROM aliadas")->fetch_all(MYSQLI_ASSOC);
$nombresAliadas = array_column($nombresAliadas, 'nombre', 'numero_aliada');

$tabs = ['reservas' => 'Reservas', 'solicitudes' => 'Solicitudes', 'aliadas' => 'Aliadas', 'cuenta' => 'Mi cuenta'];
if (!isset($tabs[$tab])) {
    $tab = 'reservas';
}
?>
<header>
    <div class="logo">C<span>✨</span> cleania · Panel</div>
    <div class="acciones">
        <span class="muted" style="color:#cbd5e1"><?= e($_SESSION['admin_usuario'] ?? '') ?></span>
        <a class="boton secundario" href="../index.html">Ver sitio</a>
        <form method="post" class="inline">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="accion" value="logout">
            <button class="peligro" type="submit">Cerrar sesión</button>
        </form>
    </div>
</header>

<main>
    <?php if ($flash): ?>
        <div class="aviso <?= e($flash['tipo']) ?>">
            <strong><?= e($flash['mensaje']) ?></strong>
            <?php if (!empty($flash['credenciales'])): $c = $flash['credenciales']; ?>
                <div class="credenciales">
                    Número de Aliada: <b><?= e($c['numero']) ?></b><br>
                    Contraseña: <b><?= e($c['password']) ?></b>
                </div>
                <p class="muted">Esta contraseña solo se muestra una vez. Envíala ahora:</p>
                <a class="boton wa" target="_blank" rel="noopener"
                   href="<?= e(enlace_whatsapp($c['telefono'], mensaje_credenciales($c['nombre'], $c['numero'], $c['password']))) ?>">
                    Enviar por WhatsApp a <?= e($c['telefono']) ?>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat"><b><?= (int)$stats['pendientes'] ?></b><small>Reservas sin aliada</small></div>
        <div class="stat"><b><?= (int)$stats['asignadas'] ?></b><small>Reservas asignadas</small></div>
        <div class="stat"><b><?= (int)$stats['solicitudes'] ?></b><small>Solicitudes por revisar</small></div>
        <div class="stat"><b><?= (int)$stats['aliadas_activas'] ?></b><small>Aliadas activas</small></div>
        <div class="stat"><b style="color:#16a34a"><?= e(formato_dinero($stats['comisiones'])) ?></b><small>Tus comisiones (<?= e(COMISION_PORCENTAJE) ?>%) de servicios completados</small></div>
        <div class="stat"><b><?= e(formato_dinero($stats['comisiones_por_cobrar'])) ?></b><small>Comisiones de servicios asignados</small></div>
    </div>

    <nav class="tabs">
        <?php foreach ($tabs as $clave => $titulo): ?>
            <a href="admin.php?tab=<?= $clave ?>" class="<?= $tab === $clave ? 'activa' : '' ?>">
                <?= $titulo ?>
                <?php if ($clave === 'solicitudes' && $stats['solicitudes'] > 0): ?><span class="contador"><?= (int)$stats['solicitudes'] ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

<?php if ($tab === 'reservas'):
    $filtro = $_GET['estado'] ?? 'activas';
    $filtros = ['activas' => 'Pendientes y asignadas', 'pendiente' => 'Pendientes', 'asignada' => 'Asignadas',
                'completada' => 'Completadas', 'cancelada' => 'Canceladas', 'todas' => 'Todas'];
    if (!isset($filtros[$filtro])) {
        $filtro = 'activas';
    }
    if ($filtro === 'activas') {
        $reservas = $conexion->query("SELECT * FROM reservas WHERE estado IN ('pendiente','asignada') ORDER BY fecha ASC, id ASC LIMIT 300");
    } elseif ($filtro === 'todas') {
        $reservas = $conexion->query("SELECT * FROM reservas ORDER BY fecha DESC, id DESC LIMIT 300");
    } else {
        $stmt = $conexion->prepare("SELECT * FROM reservas WHERE estado = ? ORDER BY fecha DESC, id DESC LIMIT 300");
        $stmt->bind_param("s", $filtro);
        $stmt->execute();
        $reservas = $stmt->get_result();
    }
    $reservas = $reservas->fetch_all(MYSQLI_ASSOC);
?>
    <div class="filtros">
        <?php foreach ($filtros as $clave => $titulo): ?>
            <a href="admin.php?tab=reservas&estado=<?= $clave ?>" class="<?= $filtro === $clave ? 'activa' : '' ?>"><?= $titulo ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (!$reservas): ?>
        <div class="vacio">No hay reservas en esta vista.</div>
    <?php endif; ?>

    <?php foreach ($reservas as $r):
        $vencida = $r['estado'] === 'pendiente' && $r['fecha'] < date('Y-m-d'); ?>
        <div class="tarjeta">
            <div class="fila">
                <div>
                    <strong>#<?= (int)$r['id'] ?> · <?= e(TIPOS_LIMPIEZA[$r['tipo_limpieza']] ?? $r['tipo_limpieza']) ?></strong>
                    — <?= (int)$r['recamaras'] ?> rec., <?= (int)$r['banos'] ?> baño(s)
                    <span class="badge b-<?= e($r['estado']) ?>"><?= e($r['estado']) ?></span>
                    <?php if ($vencida): ?><span class="badge b-inactiva">fecha pasada</span><?php endif; ?>
                    <div class="muted">📅 <?= e($r['fecha']) ?> · <?= e(RANGOS_HORARIO[$r['rango_horario']] ?? $r['rango_horario']) ?></div>
                    <div class="muted">📍 <?= e($r['direccion']) ?></div>
                    <?php if (!empty($r['descripcion'])): ?>
                        <div class="descripcion">📝 <?= nl2br(e($r['descripcion'])) ?></div>
                    <?php endif; ?>
                    <div class="muted">📱 <?= e($r['telefono']) ?>
                        · <a href="<?= e(enlace_whatsapp($r['telefono'])) ?>" target="_blank" rel="noopener" style="color:#16a34a">WhatsApp</a>
                    </div>
                    <?php if ($r['asignada_a']): ?>
                        <div class="muted">🧹 Aliada: <b><?= e($nombresAliadas[$r['asignada_a']] ?? '¿?') ?></b> (<?= e($r['asignada_a']) ?>)</div>
                    <?php endif; ?>
                    <div class="muted">Solicitada: <?= e($r['creado_en']) ?></div>
                </div>
                <div class="reparto">
                    <?php if ($r['precio'] !== null): ?>
                        <div><span>Cliente paga</span> <b style="font-size:20px;color:#00b4d8"><?= e(formato_dinero($r['precio'])) ?></b></div>
                        <div><span>Aliada recibe</span> <b><?= e(formato_dinero($r['pago_aliada'])) ?></b></div>
                        <div><span>Tu comisión</span> <b style="color:#16a34a"><?= e(formato_dinero($r['comision'])) ?></b></div>
                    <?php else: ?>
                        <div><span>Total</span> <b style="font-size:20px;color:#00b4d8"><?= e($r['total']) ?></b></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="acciones" style="margin-top:12px">
                <form method="post" class="inline">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="tab" value="reservas">
                    <input type="hidden" name="filtro" value="<?= e($filtro) ?>">
                    <input type="hidden" name="accion" value="reserva_asignar">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <select name="numero_aliada">
                        <option value="">— Sin aliada (disponible) —</option>
                        <?php foreach ($aliadasActivas as $a): ?>
                            <option value="<?= e($a['numero_aliada']) ?>" <?= $a['numero_aliada'] === $r['asignada_a'] ? 'selected' : '' ?>>
                                <?= e($a['numero_aliada'] . ' · ' . $a['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="secundario">Asignar</button>
                </form>
                <form method="post" class="inline">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="tab" value="reservas">
                    <input type="hidden" name="filtro" value="<?= e($filtro) ?>">
                    <input type="hidden" name="accion" value="reserva_estado">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <select name="estado">
                        <?php foreach (ESTADOS_RESERVA as $estado): ?>
                            <option value="<?= $estado ?>" <?= $estado === $r['estado'] ? 'selected' : '' ?>><?= ucfirst($estado) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="secundario">Cambiar estado</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>

<?php elseif ($tab === 'solicitudes'):
    $solicitudes = $conexion->query("SELECT * FROM aliadas_pendientes WHERE estado = 'en_revision' ORDER BY creado_en ASC")->fetch_all(MYSQLI_ASSOC);
?>
    <?php if (!$solicitudes): ?>
        <div class="vacio">No hay solicitudes pendientes de revisión.</div>
    <?php endif; ?>

    <?php foreach ($solicitudes as $s): ?>
        <div class="tarjeta">
            <div class="fila">
                <div>
                    <strong><?= e($s['nombre']) ?></strong>
                    <div class="muted">📱 <?= e($s['telefono']) ?>
                        · <a href="<?= e(enlace_whatsapp($s['telefono'])) ?>" target="_blank" rel="noopener" style="color:#16a34a">WhatsApp</a>
                    </div>
                    <div class="muted">Recibida: <?= e($s['creado_en']) ?></div>
                    <div style="margin-top:8px">
                        <?php foreach (array_values(array_filter(explode(',', $s['documentos']))) as $i => $doc): ?>
                            <a class="boton secundario" target="_blank" rel="noopener"
                               href="documento.php?f=<?= e(rawurlencode(basename($doc))) ?>">📄 Documento <?= $i + 1 ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="acciones">
                    <form method="post" class="inline">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="tab" value="solicitudes">
                        <input type="hidden" name="accion" value="solicitud_aprobar">
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                        <button type="submit">Aprobar</button>
                    </form>
                    <form method="post" class="inline" onsubmit="return confirm('¿Rechazar esta solicitud? Sus documentos se borrarán definitivamente.');">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="tab" value="solicitudes">
                        <input type="hidden" name="accion" value="solicitud_rechazar">
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                        <button type="submit" class="peligro">Rechazar</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

<?php elseif ($tab === 'aliadas'):
    $aliadas = $conexion->query(
        "SELECT a.numero_aliada, a.nombre, a.telefono, a.activa, a.creado_en,
                (SELECT COUNT(*) FROM reservas r WHERE r.asignada_a = a.numero_aliada AND r.estado = 'asignada') AS asignadas,
                (SELECT COUNT(*) FROM reservas r WHERE r.asignada_a = a.numero_aliada AND r.estado = 'completada') AS completadas,
                (SELECT COALESCE(SUM(r.pago_aliada), 0) FROM reservas r WHERE r.asignada_a = a.numero_aliada AND r.estado = 'completada') AS ganado
         FROM aliadas a ORDER BY a.activa DESC, CAST(a.numero_aliada AS UNSIGNED) ASC"
    )->fetch_all(MYSQLI_ASSOC);
?>
    <?php if (!$aliadas): ?>
        <div class="vacio">Aún no hay aliadas aprobadas. Revisa la pestaña Solicitudes.</div>
    <?php endif; ?>

    <?php foreach ($aliadas as $a): ?>
        <div class="tarjeta">
            <div class="fila">
                <div>
                    <strong><?= e($a['numero_aliada']) ?> · <?= e($a['nombre']) ?></strong>
                    <?php if (!$a['activa']): ?><span class="badge b-inactiva">desactivada</span><?php endif; ?>
                    <div class="muted">📱 <?= e($a['telefono']) ?>
                        · <a href="<?= e(enlace_whatsapp($a['telefono'])) ?>" target="_blank" rel="noopener" style="color:#16a34a">WhatsApp</a>
                    </div>
                    <div class="muted">Trabajos asignados: <?= (int)$a['asignadas'] ?> · Completados: <?= (int)$a['completadas'] ?> · Ha ganado: <?= e(formato_dinero($a['ganado'])) ?> · Desde: <?= e(substr($a['creado_en'], 0, 10)) ?></div>
                </div>
                <div class="acciones">
                    <form method="post" class="inline" onsubmit="return confirm('¿Generar una nueva contraseña? La anterior dejará de funcionar.');">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="tab" value="aliadas">
                        <input type="hidden" name="accion" value="aliada_reset">
                        <input type="hidden" name="numero_aliada" value="<?= e($a['numero_aliada']) ?>">
                        <button type="submit" class="secundario">Nueva contraseña</button>
                    </form>
                    <form method="post" class="inline">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="tab" value="aliadas">
                        <input type="hidden" name="accion" value="<?= $a['activa'] ? 'aliada_desactivar' : 'aliada_activar' ?>">
                        <input type="hidden" name="numero_aliada" value="<?= e($a['numero_aliada']) ?>">
                        <button type="submit" class="<?= $a['activa'] ? 'peligro' : '' ?>"><?= $a['activa'] ? 'Desactivar' : 'Reactivar' ?></button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

<?php elseif ($tab === 'cuenta'): ?>
    <div class="tarjeta cuenta">
        <h3 style="margin-top:0">Cambiar mi contraseña</h3>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="tab" value="cuenta">
            <input type="hidden" name="accion" value="cambiar_password">
            <label>Contraseña actual <input type="password" name="actual" autocomplete="current-password" required></label>
            <label>Nueva contraseña (mínimo 12 caracteres) <input type="password" name="nueva" autocomplete="new-password" minlength="12" required></label>
            <label>Confirmar nueva contraseña <input type="password" name="confirmar" autocomplete="new-password" minlength="12" required></label>
            <button type="submit" style="margin-top:16px">Guardar</button>
        </form>
    </div>
<?php endif; ?>
</main>
</body>
</html>
