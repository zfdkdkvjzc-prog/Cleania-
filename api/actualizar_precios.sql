-- ==========================================
-- ACTUALIZACIÓN: precio propuesto por el cliente + comisión
-- Ejecútalo UNA SOLA VEZ en phpMyAdmin (pestaña "SQL") SOLO si ya tenías la tabla "reservas" creada
-- con una versión anterior. Si instalas desde cero, no lo necesitas: crear_tablas.sql ya lo incluye.
-- ==========================================

ALTER TABLE reservas
    ADD COLUMN descripcion TEXT NULL AFTER tipo_limpieza,
    ADD COLUMN precio DECIMAL(10,2) NULL AFTER descripcion,
    ADD COLUMN comision DECIMAL(10,2) NULL AFTER precio,
    ADD COLUMN pago_aliada DECIMAL(10,2) NULL AFTER comision;

-- Las reservas que ya existían toman su total anterior como precio, con la comisión del 7%
UPDATE reservas
SET precio = CAST(REPLACE(REPLACE(total, '$', ''), ',', '') AS DECIMAL(10,2))
WHERE precio IS NULL;

UPDATE reservas
SET comision = ROUND(precio * 0.07, 2),
    pago_aliada = precio - ROUND(precio * 0.07, 2)
WHERE pago_aliada IS NULL;
