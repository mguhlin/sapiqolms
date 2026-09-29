# Actualizaciones de software

**Público:** administrador / operador
**Dónde:** Admin → Settings & integrations → **Software updates** (`/admin/update`)

## Qué es

Un actualizador dentro del navegador al estilo de WordPress para el **código** de Sapiqo. Subes un paquete
de actualización (`.tar.gz`) y Sapiqo reemplaza su propio código en el sitio — tras tomar una
copia de seguridad automática para que puedas revertir. Actualiza **únicamente** el código (la carpeta `sapiqo/`).
La raíz de datos persistente (`sapiqo-data/` — base de datos, subidas, insignias,
personalización de marca, claves LTI, configuración local) y el contenido de los cursos (`content/`) nunca se
tocan, por lo que actualizar es efectivamente "reemplazar la carpeta `sapiqo/`".

La versión actual se define mediante `APP_VERSION` en `app/version.php` (al momento de
escribir esto, `1.11.0`). El esquema de datos se versiona por separado (`DB_SCHEMA_VERSION`);
cualquier migración necesaria se ejecuta automáticamente en la siguiente carga de página tras una actualización.

## Cómo se usa

Abre **Admin → Software updates**. La página tiene cuatro tarjetas.

### This install

Muestra la versión instalada y la versión del esquema de datos. Si la carpeta de código **no es
escribible** por el servidor web, aparece una advertencia y los controles de actualización/reversión quedan
deshabilitados — las actualizaciones no pueden aplicarse desde el navegador hasta que se corrijan los permisos
(o actualices la carpeta manualmente).

### Apply an update

1. Haz clic en **Update package** y elige un paquete `.tar.gz`.
2. (Opcional) Marca **"Apply anyway, even if it isn't newer"** para reinstalar la misma
   versión o degradar intencionalmente.
3. Haz clic en **Upload & apply update** y confirma el aviso.

Sapiqo valida el paquete, respalda el código actual, lo aplica y muestra un
mensaje de resultado (p. ej. "Updated from 1.11.0 to 1.12.0. A backup of the previous code
was saved."). Vuelve a cargar; las migraciones se ejecutan en la siguiente carga de página.

### Create an update package

Haz clic en **Download update package** para construir un `.tar.gz` distribuible a partir del código actual
de *esta* instalación. Entrégalo a otra instalación de Sapiqo y aplícalo allí, o
consérvalo como una instantánea de código. El mismo paquete puede construirse desde la línea de comandos con
`php bin/build-update.php`.

### Backups & rollback

Cada actualización aplicada primero guarda aquí el código anterior. Para deshacer una actualización, haz clic en
**Roll back** junto a una copia de seguridad y confirma; restaura ese código (el más reciente está
marcado). Vuelve a cargar para ejecutar sobre el código restaurado.

## Opciones y comportamiento

**Cómo luce un paquete válido.** Un paquete es un `.tar.gz` (`.tgz`/`.tar` también
aceptados) que contiene un manifiesto `update.json` (`name: sapiqo-update`, una `version`,
etc.) y un árbol `code/` con el código real (verificado por la presencia de
`code/app/config.php` y `code/public/`). Un paquete al que le falte el manifiesto, con el
nombre incorrecto o sin el árbol de código se rechaza con un mensaje claro.

**Verificación de versión.** `apply_update_package()` compara la `version` del paquete con la
`APP_VERSION` instalada mediante `version_compare()`. Si el paquete no es más nuevo, se
rechaza **a menos que** marques "apply anyway" (la opción `force`) — lo que permite
reinstalar o degradar.

**Copia de seguridad automática.** Antes de aplicar, `backup_code()` archiva el código actual en
`sapiqo-data/data/updates/backup-<version>-<timestamp>.tar.gz`. Como las copias de seguridad viven
en la raíz de datos persistente, sobreviven al reemplazo del código en sí. Las copias de seguridad se
enumeran de las más nuevas primero.

**Cómo se reemplaza el código.** El árbol `code/` del paquete se fusiona sobre el código en vivo
(`_copy_tree` — agrega y sobrescribe, nunca elimina). Por lo tanto, los archivos eliminados en una nueva
versión permanecen, pero nada en uso se pierde. La raíz de datos, la carpeta de contenido
y `config.local.php` están fuera de `code/` (y `config.local.php` está explícitamente
excluido de los paquetes), así que ninguno de ellos se ve afectado.

**Rutas excluidas.** `data`, `.git` y `node_modules` nunca se empaquetan, respaldan
ni sobrescriben (`update_excluded_top()`).

**Seguridad en la extracción.** Los archivos subidos se comprueban contra Zip/Tar-Slip
(`archive_entries_safe()`) antes de la extracción — un archivo cuyas entradas escaparían del
directorio de extracción se rechaza.

**Reversión.** `rollback_update()` restaura una copia de seguridad elegida (o la última por
defecto) extrayéndola sobre la raíz del código. Los nombres de archivo de las copias de seguridad se validan contra
un patrón estricto antes de usarse.

**Auditoría.** Aplicar (`app.update`), construir (`app.update.build`) y revertir
(`app.update.rollback`) escriben todos entradas en el registro de auditoría.

## Cómo funciona

La ruta GET `/admin/update` (`require_admin`) renderiza la página con la versión
actual, el esquema, la lista de copias de seguridad y la escribibilidad de la raíz del código. POST `/admin/update`
(`require_admin` + `csrf_check`) entrega el archivo temporal subido a
`apply_update_package()`. El actualizador prepara la subida en un directorio temporal, ejecuta la
comprobación de slip, lee y valida `update.json`, confirma el árbol `code/`, aplica la
regla de versión (a menos que se fuerce), respalda el código actual y luego fusiona el nuevo
código sobre el árbol en vivo. La reversión y la descarga de paquetes tienen sus propias rutas solo para administradores
y protegidas contra CSRF.

## Consejos y trampas

- **La carpeta de código debe ser escribible por el servidor web** para actualizaciones y
  reversiones dentro del navegador. Si no lo es, los controles quedan deshabilitados — corrige los permisos o actualiza la
  carpeta a mano. (Para endurecimiento, puedes preferir mantener el código de solo lectura y actualizar
  fuera de banda; ver más abajo).
- **"Not newer" se rechaza por defecto.** Para reinstalar o degradar, marca "apply
  anyway."
- **Los archivos eliminados permanecen.** La fusión nunca elimina; para un árbol totalmente limpio, reemplaza
  la carpeta `sapiqo/` manualmente en lugar de aplicar un paquete.
- **Las migraciones se ejecutan en la siguiente carga**, no durante la subida — vuelve a cargar la app una vez tras
  actualizar.
- **Haz una copia de seguridad primero.** El actualizador respalda el código automáticamente, pero **no**
  respalda tu base de datos. Toma una instantánea de `sapiqo-data/` antes de actualizaciones importantes.
- **Nota de seguridad:** aplicar un paquete ejecuta código subido por diseño y es solo para administradores.
  Restringe quién tiene el rol de Administrador y copia las copias de seguridad fuera del servidor.
- Mantén al menos la copia de seguridad más reciente disponible para que la reversión siempre sea posible.

## Relacionado

- [Actualizar el software — una guía paso a paso para administradores](47-updating-the-software-plain-language-guide.md) — el recorrido en lenguaje sencillo, sin jerga.
- `45-security.md` — por qué el actualizador es solo para administradores y cómo se comprueban las subidas contra slip.
- `DEPLOYMENT.md` §3a & §10 — disposición de actualización y copias de seguridad.
- `40-branding-and-settings.md` — la configuración sobrevive a las actualizaciones (vive en los datos).
