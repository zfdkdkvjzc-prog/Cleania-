-- ==========================================
-- Ejecuta esto UNA SOLA VEZ en phpMyAdmin (dentro de tu cPanel de GoDaddy)
-- Selecciona tu base de datos, entra a la pestaña "SQL" y pega este código.
-- ==========================================

-- 👇 Si ya habías ejecutado este archivo antes (tabla "reservas" ya existe),
-- ejecuta SOLO esta línea para agregar la columna nueva. Si es la primera vez,
-- ignórala, el CREATE TABLE de abajo ya la incluye.
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
    total VARCHAR(20) NOT NULL,
    estado VARCHAR(20) DEFAULT 'pendiente',
    asignada_a VARCHAR(10) DEFAULT NULL,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS aliadas_pendientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    documentos TEXT NOT NULL COMMENT 'Rutas de los archivos, separadas por coma',
    estado VARCHAR(20) DEFAULT 'en_revision',
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS aliadas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_aliada VARCHAR(10) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    activa TINYINT(1) DEFAULT 1,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
);
