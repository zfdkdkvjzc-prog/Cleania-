# Cleania: cómo instalarlo en GoDaddy (cPanel)

## 1. Base de datos
1. cPanel > **Bases de datos MySQL**: crea una base de datos y un usuario, y dale **todos los privilegios** al usuario sobre esa base.
2. cPanel > **phpMyAdmin**: selecciona la base, abre la pestaña **SQL**, pega el contenido de `api/crear_tablas.sql` y ejecútalo.
   Si ya tenías tablas de una versión anterior, no pasa nada: solo se crean las que faltan.
   **Además**, si tu tabla `reservas` ya existía, ejecuta también `api/actualizar_precios.sql` (una sola vez),
   que agrega la descripción, el precio propuesto, la comisión y el pago de la aliada.

## 2. Configuración
Abre `api/config.php` y rellena `DB_NAME`, `DB_USER` y `DB_PASS` con los datos del paso 1.
(Si no tienes `config.php`, copia `api/config.example.php` como `config.php`).

## 3. Subir los archivos
Sube **todo** el contenido del ZIP a `public_html` con el Administrador de archivos de cPanel,
incluyendo los archivos ocultos `.htaccess`. Si ves que no aparecen, en el Administrador de archivos ve a
Configuración > "Mostrar archivos ocultos".

La carpeta `uploads_aliadas` debe tener permisos **755**.

## 4. Primer acceso al panel
Entra a `https://tudominio.com/api/admin.php` (también hay un enlace "Administración" en el pie de la página)
con el usuario y la contraseña de administrador. Después, cámbiala en la pestaña **Mi cuenta**.

## 5. Recomendado
- Activa el SSL gratuito (cPanel > SSL/TLS Status) y descomenta las líneas de HTTPS en el `.htaccess` principal.
- Revisa que al abrir `https://tudominio.com/uploads_aliadas/` y `https://tudominio.com/api/config.php` salga **403 Forbidden**.

## Cómo funcionan los precios
- El cliente describe su hogar y **propone cuánto paga** (mínimo $300, máximo $20,000 MXN).
- Cleania cobra solo una **comisión del 7%** sobre ese precio; la aliada recibe el 93%.
- Las aliadas **solo ven lo que ellas ganan**, nunca el precio del cliente ni tu comisión.
- Puedes cambiar el porcentaje y los límites en `api/config.php` (`COMISION_PORCENTAJE`, `PRECIO_MINIMO`, `PRECIO_MAXIMO`).
  El cambio aplica a las reservas nuevas; las anteriores conservan su reparto.

## Qué puedes hacer desde el panel
- **Resumen**: tus comisiones ganadas (servicios completados) y por cobrar (servicios asignados).
- **Reservas**: ver todas (con filtros), con la descripción del cliente y el desglose de cuánto paga el cliente, cuánto recibe la aliada y tu comisión, escribir al cliente por WhatsApp, asignar o quitar aliada, y marcar como pendiente, asignada, completada o cancelada.
- **Solicitudes**: ver los documentos de las aspirantes, aprobarlas (se genera el número de aliada y la contraseña, con un botón para enviarlos por WhatsApp) o rechazarlas (sus documentos se borran).
- **Aliadas**: ver sus trabajos y cuánto ha ganado cada una, generarles una nueva contraseña, y desactivarlas o reactivarlas.
- **Mi cuenta**: cambiar tu contraseña de administrador.
