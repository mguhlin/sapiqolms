# Descripción general de seguridad

**Público:** administrador / operador
**Dónde:** Integrado en la app (`app/security.php`, `app/helpers.php`); tareas del operador
en `DEPLOYMENT.md` → "Security". Registro de auditoría en `/admin/audit`.

## Qué es

Esta página es un resumen, de cara al operador, de las protecciones que Sapiqo aplica por
defecto y — igual de importante — las pocas cosas que **tú** aún debes hacer para ejecutarlo
de forma segura. Sapiqo fue revisado contra el OWASP Top 10 y no tiene dependencias de tiempo de ejecución
de terceros (sin Composer/npm), lo que mantiene pequeña la superficie de la cadena de suministro.

## Cómo se usa

No hay nada que activar para las protecciones integradas — se aplican en cada
solicitud. Tu trabajo es la lista de verificación de endurecimiento del final: servir sobre HTTPS, cambiar
la contraseña de administrador predeterminada, mantener `sapiqo-data/` fuera de la raíz web, restringir el
rol de Administrador y establecer `trusted_proxies` si estás detrás de un balanceador de carga.

Revisa el **registro de auditoría** (Admin → Account management → Audit log, `/admin/audit`,
con exportación CSV) periódicamente para ver quién hizo qué.

## Opciones y comportamiento

### Qué protecciones existen

**Content-Security-Policy estricta con nonces de script por solicitud.**
`send_security_headers()` emite una CSP cuyo `script-src` es `'self'` más un
nonce por solicitud (`csp_nonce()`) — **sin `'unsafe-inline'` para scripts**, de modo que un
`<script>` inyectado no puede ejecutarse. Todo el comportamiento de la interfaz es JS no intrusivo más bloques
en línea con nonce. También se envían en cada respuesta: `X-Content-Type-Options: nosniff`,
`X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`,
`Permissions-Policy` (geolocalización/micrófono/cámara desactivados) y — solo sobre HTTPS —
`Strict-Transport-Security`. (`style-src` aún permite estilos en línea, lo cual es de bajo
riesgo y no habilita la ejecución de scripts).

**Solicitudes salientes protegidas contra SSRF.** Cada solicitud del lado del servidor de una URL influida por el usuario
(avatares de SSO, replicación de imágenes, insignia-desde-URL, JWKS de LTI) pasa por `safe_fetch()`,
que primero llama a `url_is_safe_public()`. Esa función resuelve el host y rechaza
loopback, rangos privados (10/8, 172.16/12, 192.168/16), link-local (169.254/16, incluida
la metadata de nube `169.254.169.254`) y rangos reservados, y rechaza esquemas que no sean HTTP(S).
`safe_fetch()` además no sigue redirecciones (para que una URL pública
no pueda hacer 302 hacia un host interno) y limita el tamaño de la respuesta.

**Subidas de SVG/HTML en sandbox.** Como un SVG o un HTML/XML subido puede llevar
scripts, `serve.php` sirve esos tipos con `Content-Security-Policy: … sandbox`
para que nada en ellos pueda ejecutarse en el origen del sitio (defensa contra XSS almacenado). Estos
tipos en sandbox deliberadamente **nunca** se descargan al servidor web, de modo que su
encabezado protector siempre se aplica.

**Protección contra archive-slip.** Las importaciones de archivos (`.tar`/`.imscc`/SCORM/OneRoster) y
el actualizador de software validan cada entrada con `archive_entries_safe()` antes de la
extracción — cualquier entrada con una ruta absoluta o travesía `..` aborta la operación.

**CSRF en acciones que cambian estado.** Cada POST que cambia estado valida un
token por sesión mediante `csrf_check()` (`csrf_field()` en los formularios). Los endpoints OIDC de LTI 1.3
en cambio usan state/nonce respaldados por base de datos, porque son entre sitios por diseño.

**Control de acceso basado en roles.** Las rutas sensibles están protegidas: `require_admin()`,
`require_login()`, `require_content_access()` (desarrolladores de cursos),
`require_group_access()` / `require_org_access()` / `require_user_access()`
(gestores delegados). Las insignias, los certificados y los expedientes son solo del propietario o del administrador;
la suplantación no puede apuntar a otro administrador ni alcanzar rutas solo para administradores;
el servidor de archivos de cursos está confinado por ruta (realpath + verificación de prefijo + travesía y
denegación de carpeta privada en `serve.php`).

**Contraseñas hasheadas + limitación de intentos de inicio de sesión.** Las contraseñas se almacenan con
`password_hash()` (bcrypt/Argon según el valor predeterminado de PHP) y se rehashean oportunamente cuando
cambia el costo. La limitación de inicio de sesión (`app/security.php`) aplica un bloqueo de ventana deslizante
por cuenta **y** por IP; ajusta `login_max_per_account` (predeterminado 5),
`login_max_per_ip` (predeterminado 15) y `login_window_seconds` (predeterminado 900). Los tokens de
restablecimiento de contraseña son de un solo uso, de 1 hora, y solo se almacena su hash SHA-256.

**Sesiones.** Las cookies son HttpOnly + `SameSite=Lax`, `Secure` automáticamente sobre
HTTPS, con `session.use_strict_mode` y `use_only_cookies`; el ID de sesión se
regenera al iniciar sesión y al cambiar de privilegio (suplantación).

**Secretos fuera de la raíz web.** La raíz web/de documentos es `public/`. Todos los secretos
y datos — la base de datos, `config.local.php` (credenciales de la base de datos, secretos de cliente de SSO), claves
privadas de LTI, subidas y copias de seguridad — viven en `sapiqo-data/`, fuera de la raíz web.
`serve.php` además rechaza cualquier ruta URL hacia `sapiqo/` o `sapiqo-data/`, de modo que el
código y los datos nunca son accesibles por web aunque compartan la carpeta `courses/`.

**Registro de auditoría.** `/admin/audit` (con exportación CSV) registra inicios de sesión, cambios de usuario/rol/acceso,
inscripciones, publicación/ocultamiento de cursos, importaciones, suplantación, acciones de clave de API y
de actualización de software, y copias de seguridad — actor, IP, objetivo y marca de tiempo. La auditoría
nunca lanza excepciones (una falla de registro no romperá una solicitud).

Valores predeterminados adicionales: toda la salida se escapa en HTML y el texto enriquecido se sanea; el SQL es
100% de sentencias preparadas de PDO; las subidas usan una lista de extensiones permitidas, un límite de tamaño
(`max_upload_mb`) y nombres de archivo aleatorizados, preparadas fuera de la raíz web y servidas
de forma estática (nunca ejecutadas); las claves de respuesta de los cuestionarios se eliminan de los datos de curso del estudiante;
la visualización de errores está desactivada por defecto (`'debug' => false`).

### Qué debe hacer aún el operador

- **Servir sobre HTTPS** y redirigir HTTP→HTTPS — esto es lo que habilita las cookies Secure
  y HSTS.
- **Cambiar la contraseña de administrador predeterminada** creada durante la instalación; usa una fuerte.
- **Mantener `sapiqo-data/` fuera de la raíz web**, propiedad del usuario web y
  no legible por otros (`chmod 750`). Contiene la base de datos, `config.local.php`, las claves
  de LTI y las copias de seguridad.
- **Restringir el rol de Administrador.** El actualizador de software dentro del navegador ejecuta
  código subido por diseño y es solo para administradores; usa Course developer / Group manager
  para el personal delegado en su lugar.
- **Establecer `trusted_proxies`** cuando estés detrás de un proxy/balanceador de carga, para que las IP de cliente (usadas
  para la limitación y la auditoría) se lean de `X-Forwarded-For` solo desde saltos confiables.
- **Dejar `'debug' => false`** en producción.
- **Prueba de humo:** `/courses/sapiqo/...` y `/courses/sapiqo-data/...` devuelven **403**.

## Cómo funciona

Los encabezados de referencia los establece `send_security_headers()` en cada respuesta; el nonce
de script de la CSP proviene de `csp_nonce()` (un valor por solicitud, reutilizado en cada bloque
en línea con nonce). Las solicitudes salientes se canalizan por `safe_fetch()` →
`url_is_safe_public()`. Las subidas/actualizaciones se canalizan por `archive_entries_safe()`.
El CSRF es `csrf_token()`/`csrf_check()`; el RBAC son las protecciones `require_*` en
`app/auth.php`; la limitación y el rastro de auditoría están en `app/security.php`.

## Consejos y trampas

- **HTTPS es fundamental.** Sin él, las cookies no se marcan como Secure y no se envía HSTS
  (esto es intencional para que las instalaciones de LAN/desarrollo en HTTP plano sigan funcionando).
- **Detrás de un proxy, siempre establece `trusted_proxies`.** De lo contrario, las IP de limitación y auditoría
  reflejan el proxy, y una lista de confianza sin establecer significa que `X-Forwarded-For` se ignora.
- **Administrador = control total, incluida la ejecución de código** mediante el actualizador. Otórgalo
  con moderación.
- **El SVG/HTML en sandbox no se descargará por CDN/X-Sendfile** — eso es deliberado;
  no intentes eludirlo para esos tipos.
- **`style-src` permite estilos en línea** por diseño; los nonces de la CSP cubren los scripts, no
  los atributos `style=` en línea. Es una compensación documentada y de bajo riesgo.
- Copia las copias de seguridad fuera del servidor y verifica una restauración de vez en cuando.

## Relacionado

- `44-software-updates.md` — por qué el actualizador es solo para administradores; comprobaciones de slip en los paquetes.
- `41-single-sign-on.md` / `43-api-keys-and-rest-api.md` — dónde viven los secretos/tokens.
- `24-impersonation.md` — las salvaguardas de suplantación mencionadas arriba.
- `DEPLOYMENT.md` → Security — la lista de verificación completa y la guía de proxy inverso/TLS.
