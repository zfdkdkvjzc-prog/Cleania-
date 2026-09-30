# Cleania: cómo instalarlo en GoDaddy (cPanel)

## 1. Base de datos
1. cPanel > **Bases de datos MySQL**: crea una base de datos y un usuario, y dale **todos los privilegios** al usuario sobre esa base.
2. cPanel > **phpMyAdmin**: selecciona la base, abre la pestaña **SQL**, pega el contenido de `api/crear_tablas.sql` y ejecútalo.
   Si ya tenías tablas de la versión anterior, no pasa nada: solo se crean las que faltan (`administradores` e `intentos_login`).

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

## Qué puedes hacer desde el panel
- **Reservas**: ver todas (con filtros), escribir al cliente por WhatsApp, asignar o quitar aliada, y marcar como pendiente, asignada, completada o cancelada.
- **Solicitudes**: ver los documentos de las aspirantes, aprobarlas (se genera el número de aliada y la contraseña, con un botón para enviarlos por WhatsApp) o rechazarlas (sus documentos se borran).
- **Aliadas**: ver sus trabajos, generarles una nueva contraseña, y desactivarlas o reactivarlas.
- **Mi cuenta**: cambiar tu contraseña de administrador.
