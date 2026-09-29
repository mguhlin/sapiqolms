# Copias de seguridad

**Audiencia:** administrador
**Dónde:** Descargar copia de seguridad — `/admin/backup`

## Qué es
**Descargar copia de seguridad** produce una única instantánea de archivo de todos los
**datos persistentes** de Sapiqo — configuración, la base de datos, insignias, avatares y cargas —
para recuperación ante desastres o traslado a un nuevo servidor. Respalda la **raíz de datos**,
no el contenido de los cursos (los cursos son archivos estáticos que se respaldan por separado).

## Cómo usarla
1. Ve a **Descargar copia de seguridad** (`/admin/backup`), accesible desde el área de **Importaciones y
   copias de seguridad** y el grupo de tarjetas **Exportaciones**.
2. El archivo se construye en el servidor y se descarga a tu navegador como un único
   archivo llamado `sapiqo-backup-<timestamp>` (`.zip`, o `.tar.gz` en servidores
   sin la extensión Zip).
3. Guarda el archivo en un lugar seguro y fuera del servidor. Repite de forma programada.

No hay formulario en pantalla — visitar la página inicia la descarga. (Existe un equivalente
de CLI en `bin/backup.php` para copias de seguridad programadas y desatendidas.)

## Opciones y comportamiento
- **Formato.** Un `.zip` cuando la extensión Zip de PHP está presente; de lo contrario, un
  `.tar.gz` portátil. La descarga se sirve con el tipo de contenido correspondiente y luego la
  copia temporal en el servidor se elimina.
- **Qué contiene.** Todo lo que hay bajo la raíz de datos, dispuesto bajo una
  carpeta `data-root/` en el archivo, más un `BACKUP-INFO.txt` que describe cuándo se
  creó y qué controlador de base de datos se usó.
- **Consistencia de la base de datos.** Antes de archivar, Sapiqo hace la base de datos
  consistente: para **SQLite** hace un checkpoint del registro de escritura anticipada; para **MySQL**
  ejecuta `mysqldump` y agrega el resultado como `db/dump.sql` dentro del archivo.
- **Qué se excluye.** Cualquier copia de seguridad anterior (la carpeta `backups/`) y los archivos
  auxiliares transitorios de SQLite (`.sqlite-wal`, `.sqlite-shm`) se dejan fuera para mantener el
  archivo limpio.
- **Auditoría.** Cada descarga se registra en el registro de auditoría como `backup.download`
  con el nombre del archivo.

## Cómo funciona
La rutina de copia de seguridad resuelve la raíz de datos, recorre cada archivo bajo ella (omitiendo
copias de seguridad anteriores y archivos auxiliares de SQLite) y los agrega a un Zip (o, como alternativa,
un `.tar.gz` de PharData). Para SQLite, el archivo de base de datos en vivo se incluye directamente
después de un checkpoint de WAL; para MySQL se incrusta un `mysqldump` para que la copia de seguridad sea
portátil a una base de datos nueva. Un breve `BACKUP-INFO.txt` con la hora de creación, el
controlador y el recuento de archivos se escribe dentro del archivo.

La **raíz de datos** es la carpeta donde Sapiqo mantiene sus datos escribibles. En una implementación
típica es un directorio `sapiqo-data/` junto a la aplicación (su
nombre puede anularse con la variable de entorno `SAPIQO_DATA`); contiene
la base de datos, insignias, avatares,
cargas, preparación de exportaciones y configuración. Esa carpeta — no el código y no
el contenido de los cursos — es el dato real que hay que proteger.

## Restaurar o mover a un nuevo servidor
1. **Levanta Sapiqo** en el servidor de destino (la misma versión o una más nueva).
2. **Restaura la raíz de datos:** extrae el archivo y copia el contenido de
   `data-root/` en el directorio de datos de la nueva instalación (`sapiqo-data/`). Para una
   instalación MySQL, carga `db/dump.sql` en la base de datos de destino; para SQLite, el
   archivo de base de datos ya está en la raíz de datos restaurada.
3. **Restaura el contenido de los cursos por separado:** copia las carpetas de los cursos en el
   directorio de contenido (o vuelve a importar el paquete `.tar` de cada curso), luego abre
   **Gestionar cursos** y elige **Reescanear carpeta drop-in** para que el catálogo coincida
   con las carpetas del disco.
4. **Verifica:** inicia sesión como administrador, revisa el catálogo de cursos, algunas cuentas de
   estudiantes, insignias y configuración.

## Consejos y trucos
- **Respalda dos cosas, no una.** El archivo de `/admin/backup` cubre la raíz de
  datos; **el contenido de los cursos no está en él.** Copia también la carpeta de contenido (o exporta
  cada curso como un `.tar`) para poder reconstruir por completo.
- **Las raíces de datos grandes toman tiempo y memoria.** La descarga construye todo el
  archivo antes de enviarlo; en instalaciones grandes ejecuta la copia de seguridad de CLI (`bin/backup.php`)
  de forma programada en lugar de la ruta del navegador.
- **Mantén las copias de seguridad fuera de la máquina.** El archivo excluye copias de seguridad anteriores por diseño, así que
  no confíes en la propia carpeta `backups/` del servidor como tu única copia.
- **Iguala o supera la versión.** Restaura en la misma versión de Sapiqo o en una
  más nueva; restaurar una copia de seguridad más nueva en código más antiguo no es compatible.
- **Las descargas se auditan.** Espera una entrada `backup.download` en el registro de auditoría
  cada vez.

## Relacionado
- Exportaciones (`32-exports.md`)
- Importar cursos y usuarios (`31-importing-courses-and-users.md`)
- Gestión de cursos (`30-course-management.md`)
- Registro de auditoría (`35-audit-log.md`)
