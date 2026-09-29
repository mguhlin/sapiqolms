# Personalización de marca y configuración

**Público:** administrador / operador
**Dónde:** Admin → Settings & integrations → **Branding & settings** (`/admin/settings`)

## Qué es

La página de personalización de marca y configuración permite adaptar Sapiqo con la marca blanca de tu organización. Todo lo
que estableces aquí se almacena en la tabla `settings` de la base de datos y se superpone a la
configuración de la aplicación en tiempo de ejecución mediante `branding_overlay()` (en `app/settings.php`), de modo que los
valores fluyen por todo el producto sin editar archivos y sobreviven a las
actualizaciones de código. Las claves que superponen la configuración se enumeran en `BRANDING_KEYS` e incluyen:
nombre de la plataforma, eslogan, nombre de la organización, nombre del sitio de cursos, marca gráfica, logotipo,
colores del tema, campos de firmante/proveedor del certificado y el idioma predeterminado de la interfaz.

Como estos valores viven en la base de datos (no en `config.local.php`), cualquier administrador puede
cambiarlos desde el navegador y verlos aplicarse de inmediato en la barra superior,
la página de bienvenida, la página de inicio de sesión, el lector de cursos, las insignias y los PDF de certificados.

## Cómo se usa

Abre **Admin → Branding & settings**. La página es una serie de tarjetas; las ediciones en el
formulario principal se guardan con el botón **Save branding** al final. (Los ajustes preestablecidos de tema
se aplican al hacer clic, como sus propios formularios de un solo clic).

1. **Identidad** — Nombre de la plataforma (barra superior, títulos de página, correos electrónicos), Eslogan,
   Nombre de la organización (certificados + pie de página), Nombre del sitio de cursos (marca mostrada dentro
   del lector de cursos; si se deja en blanco, el valor predeterminado es "Cursos de *Nombre de la plataforma*") y Marca gráfica
   (1–2 letras que se usan cuando no se ha subido ningún logotipo).
2. **Logotipo** — Sube un PNG, JPG, WEBP o SVG. Se recomienda PNG/SVG transparente
   porque el logotipo se renderiza sobre la barra de navegación azul marino oscuro. Aparece una casilla "Remove logo"
   una vez que se ha establecido uno.
3. **Ajustes preestablecidos de tema** — Apariencias de un solo clic (colores + fuente + radio de esquina). Consulta más abajo.
4. **Colores del tema** — Define un par hexadecimal personalizado de color Primario y de Acento para anular los
   colores del ajuste preestablecido.
5. **Idioma** — Idioma predeterminado de la interfaz para visitantes nuevos/no autenticados.
6. **Comportamiento del curso** — Alternador de modo secuencial por defecto e hitos de recordatorio de
   caducidad de acceso (documentados en la página de inscripción/recordatorios).
7. **Certificados** — Nombre y cargo del firmante, línea del proveedor de CPE, sitio web del pie de página.

Haz clic en **Save branding**. Se escribe una entrada `settings.update` en el registro de auditoría.

## Opciones y comportamiento

**Subida del logotipo.** Las extensiones aceptadas son `png`, `jpg`, `jpeg`, `webp`, `svg`.
Las subidas de mapa de bits deben pasar `getimagesize()` (una imagen real); los SVG se aceptan por
extensión. El archivo se almacena en `sapiqo-data/data/branding/` con un nombre
aleatorizado (`logo-xxxxxx.ext`); el archivo del logotipo anterior se elimina cuando lo reemplazas o
lo quitas. El logotipo almacenado se sirve a través de la ruta `/brand/logo` y aparece en:

- la barra de navegación superior (`layout.php`),
- la página de marketing/bienvenida (`splash.php`),
- la página de inicio de sesión (`login.php`),
- y, donde esté configurado, en la salida del certificado.

Cuando no se establece ningún logotipo, se muestra en su lugar la **marca gráfica** (letra/iniciales); si el
campo de marca gráfica también está en blanco, el valor predeterminado es la primera letra del nombre de la plataforma.

**Ajustes preestablecidos y colores del tema.** `theme_presets()` incluye apariencias seleccionadas — Sapiqo
(azul marino y dorado predeterminados), además de estilos que evocan WordPress, Joomla, Moodle, Canvas,
Blackboard, Schoology y originales (Forest, Slate, Rose, Grape, High contrast).
Aplicar un ajuste preestablecido establece `theme_primary`, `theme_accent`, `theme_font` y
`theme_radius`. Los colores se validan como `#rrggbb` (un valor incorrecto se descarta y
recurre al predeterminado del CSS). Dos colores impulsan toda la paleta: el primario
genera la escala de azul marino y el de acento genera la escala de dorado mediante `shade()`.
La fuente y el radio de esquina provienen únicamente de un ajuste preestablecido — no hay entradas directas para
ellos en el formulario.

**Idioma predeterminado / i18n.** El selector de idioma enumera `available_locales()` y
guarda `default_locale`. Los estudiantes aún pueden cambiar de idioma por sí mismos. Agrega más
idiomas colocando un archivo `app/lang/<code>.php` — luego aparece en la lista.

**Firmante del certificado y proveedor de CPE.** Estos se imprimen en el bloque del firmante en la
página 1 del PDF del certificado: `cert_signatory` (nombre), `cert_signatory_title`,
`cert_provider` (la línea del proveedor de CPE, p. ej. un número de proveedor estatal que hace que las horas
de CPE sean reconocidas) y `cert_website` (pie de página). Las horas de crédito de CPE por curso se establecen
por separado en Manage courses, no aquí.

**Foro de discusión / recordatorios de caducidad.** El campo "Access-expiry reminders (days
before)" almacena `expiry_reminder_days` (predeterminado `30,7,1`). La entrada se
depura hasta una lista descendente de enteros positivos sin duplicados; un valor vacío
recurre a `30,7,1`. Los estudiantes con una ventana de acceso al curso reciben un recordatorio dentro de la app
(y por correo electrónico, si el correo está configurado) en cada hito. El barrido de entrega se ejecuta desde
`bin/expire.php` en un cron diario. Consulta la página del manual sobre caducidad de inscripción para más detalle.

## Cómo funciona

La configuración es un almacén de clave/valor (tabla `settings`) que se lee a través de `setting()` /
`all_settings()` con una caché en proceso. Escribir un valor (`set_setting()`) hace un
upsert portable y actualiza la caché. En cada solicitud, `lms_config()` construye
la configuración basada en archivos y luego llama a `branding_overlay()` para reemplazar cualquier clave de configuración
que tenga un valor no vacío en la base de datos — de modo que `lms_config()['app_name']`, `['logo']`, etc.
ya reflejan los cambios del administrador en todos los lugares donde se consumen. Si falta la tabla `settings`
(instalación nueva antes de la migración), la superposición no hace nada y se aplican los valores predeterminados.

El CSS del tema lo emite `brand_theme_style()`, que produce un bloque `<style
id="brand-theme">` que anula la escala de color, la fuente y las variables CSS de
radio. Devuelve una cadena vacía cuando no se establece nada personalizado, de modo que se aplican los valores
predeterminados del CSS de serie.

## Consejos y trampas

- **Los colores deben ser `#rrggbb`.** Una abreviatura de 3 dígitos o un color con nombre se rechaza y
  se borra en silencio. Usa la forma completa de seis dígitos hexadecimales.
- **La fuente y el radio solo provienen de ajustes preestablecidos.** Para cambiar la tipografía o el estilo de esquina, elige el
  ajuste preestablecido cuya apariencia quieras; no hay una entrada independiente de fuente/radio.
- **Los logotipos transparentes se ven mejor.** El logotipo se asienta sobre la barra de navegación oscura; un
  recuadro de fondo blanco sólido se verá.
- **Reemplazar el logotipo elimina el archivo antiguo** de `sapiqo-data/data/branding/`.
  Guarda tu propia copia maestra del arte de origen.
- **Solo los administradores** pueden acceder a `/admin/settings` (`require_admin`), y el
  POST de guardado está protegido contra CSRF.
- La personalización de marca sobrevive a las actualizaciones porque vive en `sapiqo-data/`, no en el
  código reemplazable — no hace falta volver a aplicarla tras una actualización de software.

## Relacionado

- `41-single-sign-on.md` — botones de proveedor en la página de inicio de sesión.
- `23-enrollment-expiry-and-reminders.md` — los hitos de recordatorio de caducidad que se establecen aquí.
- `05-badges-certificates-transcript.md` — dónde aparecen los campos de firmante/CPE
  del certificado.
- `DEPLOYMENT.md` — disposición de datos (`sapiqo-data/`) y actualizaciones.
