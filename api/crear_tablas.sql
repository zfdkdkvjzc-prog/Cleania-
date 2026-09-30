-- ==========================================
-- Ejecuta esto en phpMyAdmin (dentro de tu cPanel de GoDaddy):
-- selecciona tu base de datos, entra a la pestaña "SQL" y pega este código.
-- Es seguro ejecutarlo aunque ya tengas tablas creadas: solo crea las que faltan.
-- ⚠️ Si YA habías creado la tabla "reservas" antes, ejecuta también actualizar_precios.sql
-- ==========================================

-- 👇 SOLO si ya tenías la tabla "reservas" de una versión MUY antigua (sin la columna asignada_a),
-- quita los dos guiones del inicio de esta línea y ejecútala:
-- ALTER TABLE reservas ADD COLUMN asignada_a VARCHAR(10) DEFAULT NULL;


CREATE TABLE IF NOT EXISTS reservas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    direccion VARCHAR(255) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    fecha DATE NOT NULL,
    rango_horario VARCHAR(20) NOT NULL,
    recamaras INT NOT NULL,
    banos INT NOT NULL,
    tipo_limpieza VARCHAR(20) NOT NULL,
    descripcion TEXT NULL COMMENT 'Lo que el cliente describe de su hogar y del servicio',
    precio DECIMAL(10,2) NULL COMMENT 'Precio que propone y paga el cliente',
    comision DECIMAL(10,2) NULL COMMENT 'Comisión de Cleania sobre el precio',
    pago_aliada DECIMAL(10,2) NULL COMMENT 'Lo que recibe la aliada (precio - comisión)',
    total VARCHAR(20) NOT NULL COMMENT 'Precio con formato, ej. $650.00',
    estado VARCHAR(20) DEFAULT 'pendiente',
    asignada_a VARCHAR(10) DEFAULT NULL,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_estado_fecha (estado, fecha),
    INDEX idx_asignada (asignada_a)
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS aliadas_pendientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    documentos TEXT NOT NULL COMMENT 'Nombres de archivo en uploads_aliadas, separados por coma',
    estado VARCHAR(20) DEFAULT 'en_revision' COMMENT 'en_revision, aprobada o rechazada',
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS aliadas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_aliada VARCHAR(10) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    activa TINYINT(1) DEFAULT 1,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
) DEFAULT CHARSET=utf8mb4;

-- Administradores del panel. Se llena solo la primera vez que entras con el usuario de config.php.
CREATE TABLE IF NOT EXISTS administradores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
) DEFAULT CHARSET=utf8mb4;

-- Registro de intentos para frenar ataques de fuerza bruta y spam de formularios.
CREATE TABLE IF NOT EXISTS intentos_login (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    tipo VARCHAR(20) NOT NULL,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_tipo (ip, tipo, creado_en)
) DEFAULT CHARSET=utf8mb4;
