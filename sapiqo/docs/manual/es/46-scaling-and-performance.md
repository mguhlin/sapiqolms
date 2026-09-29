# Escalado y rendimiento

**Público:** administrador / operador
**Dónde:** `sapiqo-data/config.local.php`, tu configuración de PHP/servidor web/base de datos, y
`DEPLOYMENT.md` → "6a. Scaling & performance (large deployments)".

## Qué es

Sapiqo es PHP puro + PDO sin sobrecarga de framework por solicitud, de modo que un solo
servidor bien ajustado (aproximadamente 4–8 vCPU, 8–16 GB de RAM) sirve cómodamente unas 5.000
cuentas con unos cientos de estudiantes activos concurrentemente. Las rutas con muchas escrituras son
las inscripciones, los envíos de cuestionarios y las publicaciones del foro; casi todo lo demás son lecturas
cacheables. Esta página resume las palancas, ordenadas por impacto; la configuración autoritativa
y lista para copiar y pegar vive en `DEPLOYMENT.md` §6a.

## Cómo se usa

Trabaja de arriba a abajo en esta lista — las primeras tres son las más importantes.

### 1. Usa MySQL/MariaDB, nunca SQLite, en producción

SQLite toma un único bloqueo global de escritura, así que cada guardado de progreso, envío de cuestionario y
publicación de foro se serializa en una sola cola y se atasca bajo concurrencia. El
`config.local.php` de ejemplo ya tiene por defecto `db_driver => 'mysql'`; consérvalo. SQLite está
bien solo para una instalación personal/sin conexión. Ajusta la base de datos a tu conjunto de trabajo
(`innodb_buffer_pool_size` ~60–70% de la RAM en una máquina de base de datos dedicada, `max_connections`
un poco por encima del número de trabajadores de PHP-FPM, `innodb_flush_log_at_trx_commit = 2` para
escrituras más rápidas). Los valores iniciales exactos están en `DEPLOYMENT.md` §6a.

### 2. Habilita OPcache

Sin OPcache, PHP vuelve a analizar cada archivo `.php` en cada solicitud; activarlo es
típicamente una ganancia de rendimiento de 3–5× — la mayor palanca individual. Habilita
`opcache.enable=1` con `opcache.memory_consumption` y
`opcache.max_accelerated_files` generosos, más una caché de realpath. En producción puedes establecer
`opcache.validate_timestamps=0` para máxima velocidad, pero entonces debes recargar PHP
tras cada despliegue/actualización para que se recoja el nuevo código.

### 3. Ejecuta PHP-FPM y dimensiona el pool

Sirve mediante PHP-FPM detrás de nginx/Apache — nunca `php -S`, que es de un solo hilo y
solo para desarrollo. Presupuesta aproximadamente 40–60 MB por trabajador y dimensiona `pm.max_children` según la RAM
disponible (≈ RAM-para-PHP ÷ 50 MB). Establece `pm = dynamic` con servidores de reserva
start/min/max sensatos. Consulta `DEPLOYMENT.md` §6a para una plantilla de pool.

### 4. Descarga las transferencias de medios al servidor web

Por defecto, Sapiqo transmite los medios de los cursos a través de un trabajador de PHP (`app/serve.php`), que
ocupa ese trabajador durante toda la descarga — costoso cuando entregas varios GB de
video. Habilita la descarga integrada para que el servidor web empuje los bytes y el trabajador
se libere de inmediato (las verificaciones de autenticación + ruta aún se ejecutan primero en PHP). Establece **exactamente una**
opción en `config.local.php`:

- **nginx:** `'x_accel_redirect' => '/__protected_courses'`, luego agrega una ubicación `internal`
  con alias a tu directorio `courses/`. Sapiqo emite
  `X-Accel-Redirect: /__protected_courses/<rel-path>` y nginx sirve el archivo.
- **Apache / lighttpd:** instala `mod_xsendfile`, establece `'x_sendfile' => true` y
  agrega el directorio de cursos a la lista blanca con `XSendFilePath`. Sapiqo emite
  `X-Sendfile: <absolute-path>`.

Las subidas en sandbox (SVG/HTML) **nunca** se descargan, de modo que sus encabezados protectores
de CSP siempre se aplican. La descarga también se omite para las solicitudes POST.

### 5. Cachea los recursos estáticos + usa una CDN

El shell del lector, el JS, el CSS y las imágenes son altamente cacheables. `serve.php` ya
envía `Cache-Control: public, max-age=3600` (y admite solicitudes de rango HTTP para que el
salto en el video funcione). Agrega gzip/brotli y caché de larga duración para `/assets/` en el
servidor web, o pon toda una CDN al frente del sitio — la descarga de medios de arriba se combina bien
con una CDN de tipo origin-pull.

### 6. Sesiones (gestionadas) y multinodo

PHP bloquea el archivo de sesión durante la duración de una solicitud. Sapiqo ya llama a
`session_write_close()` en `serve.php` **después** de todas las decisiones de autenticación/control pero
**antes** de transmitir medios, para que las muchas solicitudes paralelas de medios/imágenes de un estudiante
no se serialicen unas detrás de otras — una gran ganancia de velocidad percibida a escala. Para un solo
servidor de aplicaciones, eso es todo lo que necesitas.

Para ejecutar más de un servidor de aplicaciones detrás de un balanceador de carga, haz que el nivel sea sin estado:

- **Sesiones** → un almacén compartido (Redis/Memcached) mediante `session.save_handler`, o
  habilita sesiones persistentes (sticky) en el balanceador de carga.
- **`sapiqo-data/`** (subidas, insignias, personalización de marca, claves de LTI) → almacenamiento compartido (NFS o
  un montaje compatible con S3) para que cada nodo vea los mismos archivos.
- **Base de datos** → todos los nodos apuntan a la misma MySQL/MariaDB.

### 7. Índices de base de datos (automáticos)

Las tablas activas están indexadas tanto en la dirección por usuario como en la dirección por curso. Los
índices en dirección por curso que usan los reportes de finalizaciones/libro de calificaciones/lista se
crean automáticamente al actualizar mediante `db_ensure_indexes()` (en `app/db.php`) — agrega
`idx_enroll_course`, `idx_progress_course`, `idx_quiz_course` e `idx_badges_course`
si faltan, tanto en SQLite como en MySQL. No se requiere ningún paso manual. Las listas grandes de administración
también se paginan para que una lista extensa nunca se renderice toda a la vez.

## Opciones y comportamiento

| Config key (`config.local.php`) | Effect |
|--------------------------------|--------|
| `db_driver` | `mysql` (producción) o `sqlite` (solo personal/sin conexión). |
| `x_accel_redirect` | Prefijo de redirección interna de nginx; habilita la descarga de medios. Vacío = desactivado. |
| `x_sendfile` | `true` para la descarga con `mod_xsendfile` de Apache/lighttpd. `false` = desactivado. |
| `trusted_proxies` | Confía en `X-Forwarded-For` desde estos saltos (IP de cliente correctas detrás de un balanceador). |

Habilita solo uno de `x_accel_redirect` / `x_sendfile`, según tu servidor web.

## Cómo funciona

`serve.php` ejecuta primero todas las verificaciones de autenticación y control de contenido, luego llama a
`session_write_close()` para liberar el bloqueo de sesión antes de transmitir. Si se establece una clave de descarga
(y la solicitud no es de un tipo en sandbox ni un POST), envía el
encabezado `X-Accel-Redirect` / `X-Sendfile` apropiado y sale, entregando el empuje de bytes
al servidor web; de lo contrario, transmite el archivo por sí mismo con soporte de rango HTTP.
La creación de índices ocurre mediante `db_ensure_indexes()`, invocado durante el arranque del esquema
para que las actualizaciones recojan los índices en dirección por curso sin acción del operador.

## Consejos y trampas

- **Nunca lances producción sobre SQLite.** El bloqueo global de escritura es el clásico
  atasco de concurrencia.
- **Habilita exactamente una opción de descarga**, y haz que el `alias` de nginx (o
  `XSendFilePath`) apunte a tu directorio `courses/` real con la barra final
  que nginx espera.
- **`opcache.validate_timestamps=0` requiere una recarga de PHP tras cada actualización de código**
  (incluido el actualizador dentro del navegador) — de lo contrario, seguirás ejecutando el código antiguo.
- **El multinodo necesita las tres cosas**: sesiones compartidas, `sapiqo-data/` compartido y una
  base de datos compartida. Si falta alguna, el comportamiento entre nodos será inconsistente.
- **Establece `trusted_proxies`** detrás de un balanceador para que la limitación/auditoría vean las IP de cliente
  reales (consulta la página de seguridad).
- El SVG/HTML en sandbox se excluye intencionalmente de la descarga y la CDN — no intentes
  desviarlo alrededor de PHP.

## Relacionado

- `DEPLOYMENT.md` §6a — la configuración autoritativa y lista para copiar y pegar para todo lo anterior.
- `45-security.md` — endurecimiento de sesión, `trusted_proxies`, subidas en sandbox.
- `44-software-updates.md` — recarga PHP tras las actualizaciones cuando las marcas de tiempo están deshabilitadas.
