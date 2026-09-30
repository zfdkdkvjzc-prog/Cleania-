<?php
// ==========================================
// CONFIGURACIÓN DE CLEANIA
// ==========================================
// Copia este archivo como "config.php" (en esta misma carpeta api/) y rellena los datos.
// config.php NO se sube a GitHub y el servidor bloquea que alguien lo abra desde el navegador.

// 👇 Base de datos MySQL. Los datos están en cPanel > Bases de datos MySQL.
// GoDaddy antepone tu usuario de cPanel, ej: "abc123_cleania". El host casi siempre es "localhost".
define('DB_HOST', 'localhost');
define('DB_NAME', 'TU_USUARIO_cleania');
define('DB_USER', 'TU_USUARIO_cleania');
define('DB_PASS', 'TU_CONTRASEÑA_AQUI');

// 👇 Administrador inicial del panel (api/admin.php).
// Solo se usa la PRIMERA vez que entras: después la contraseña se guarda en la base de datos
// y puedes cambiarla desde el panel (pestaña "Mi cuenta").
// Para generar el hash de otra contraseña: php -r 'echo password_hash("tu-clave", PASSWORD_DEFAULT);'
define('ADMIN_USUARIO', 'admin');
define('ADMIN_PASSWORD_HASH', 'PEGA_AQUI_EL_HASH');

// Carpeta de documentos de aliadas. Si puedes, muévela FUERA de public_html
// (ej: '/home/TU_USUARIO/uploads_aliadas/') para que nunca sea accesible desde la web.
define('UPLOADS_DIR', __DIR__ . '/../uploads_aliadas/');

// Zona horaria del negocio (para validar fechas de reserva).
define('ZONA_HORARIA', 'America/Mexico_City');
