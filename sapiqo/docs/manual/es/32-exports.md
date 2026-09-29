# Exportaciones

**Audiencia:** administrador
**Dónde:** el grupo de tarjetas **Exportaciones** en la navegación de Cuenta/Administración, más el
menú ⋯ de cada curso en `/admin/courses`

## Qué es
Sapiqo puede exportar casi todo lo que contiene — personas, resultados, contenido de cursos,
el rastro de auditoría y una instantánea completa de datos — como archivos descargables. La sección
**Exportaciones** del hub de administración reúne los atajos; las descargas reales las sirven
rutas dedicadas y, para el contenido de cursos, el menú **⋯** de cada curso.

Las exportaciones disponibles son:

- **Lista de usuarios (CSV)** — cada cuenta con sus campos de perfil.
- **Finalizaciones (CSV)** — inscripciones, estado, fechas y códigos de insignia
  (según filtros).
- **Libro de calificaciones / calificaciones** — una cuadrícula de puntuaciones de cursos, cada una con su propia exportación CSV.
- **Contenido de cursos** — exporta cualquier curso a otro LMS (Common Cartridge), un
  paquete `.tar`, o `.json` sin procesar, desde el menú ⋯ del curso.
- **Registro de auditoría (CSV)** — un registro de las acciones de administración.
- **Copia de seguridad completa de datos** — todo como un único archivo.

## Cómo usarla

### Lista de usuarios (CSV)
1. Abre **Lista de usuarios (CSV)** (`/admin/users.csv`).
2. El archivo se descarga como `sapiqo-users.csv`. Opcionalmente agrega un término de búsqueda con el
   parámetro `?q=` para exportar solo los usuarios coincidentes (coincide con correo electrónico, nombre,
   organización o campus).

### Finalizaciones (CSV)
1. Ve a **Ver finalizaciones** (`/admin/completions`) y establece los filtros que quieras.
2. Elige **⬇ Exportar CSV**. La exportación respeta los filtros actuales — el botón
   incluso dice **Exportar CSV (filtrado)** cuando hay filtros activos. El archivo es
   `sapiqo-completions.csv`.
3. También puedes obtener una exportación de finalizaciones sin filtrar directamente desde
   `/admin/completions?export=1` o desde la barra de herramientas de los informes de capacitación.

### Libro de calificaciones / calificaciones
1. Abre **Libro de calificaciones y calificaciones** (`/admin/gradebook`) y selecciona un curso.
2. Cada cuadrícula de puntuaciones de curso tiene su propia exportación CSV para puntos, categorías, calificaciones
   con letra y resultados de cuestionarios.

### Contenido de cursos (desde el menú ⋯)
1. En **Gestionar cursos** (`/admin/courses`), abre el menú **⋯** de un curso.
2. Bajo **Exportar**, elige uno:
   - **A otro LMS (Common Cartridge)** — abre una página guiada y descarga un
     `.imscc` para Canvas / Blackboard / Moodle / Sakai.
   - **Paquete (.tar)** — el curso completo (contenido + medios) para mover entre
     servidores Sapiqo o archivar.
   - **Datos del curso (.json)** — el `course.json` sin procesar (estructura, lecciones,
     cuestionarios), sin medios.
   - **Dividir (cargas grandes)** — el `.tar` dividido en partes numeradas pequeñas, en una
     página de descarga, para servidores con límites de carga estrictos.

### Registro de auditoría (CSV)
1. Ve al **Registro de auditoría** (`/admin/audit`), opcionalmente filtra por término de búsqueda o
   acción.
2. Elige **Exportar CSV** (o usa `/admin/audit?export=1`). La exportación conserva tus
   filtros actuales; el archivo es `sapiqo-audit.csv`.

### Copia de seguridad completa de datos
1. Elige **Copia de seguridad completa de datos** / **Descargar copia de seguridad** (`/admin/backup`).
2. Se descarga un único archivo con todos los datos persistentes. Consulta la página de Copias de seguridad para
   ver qué contiene y cómo restaurar.

También hay un **CSV de resumen del informe** en la barra de herramientas de los informes de capacitación
(`/admin/reports.csv`, descarga `sapiqo-report-summary.csv`) con los KPI principales,
el rendimiento por curso y filas por organización.

## Opciones y comportamiento

**Columnas de la lista de usuarios.** `sapiqo-users.csv` contiene: ID, Nombre, Apellido,
Correo electrónico, Teléfono, Rol, Tipo de usuario, Campus, Organización, Proveedor de autenticación, Creado,
Actualizado. Exportar la lista se registra en el registro de auditoría como `users.export`.

**Columnas de finalizaciones.** `sapiqo-completions.csv` contiene: Nombre, Apellido,
Correo electrónico, Campus, Organización, Tipo de usuario, Curso, Estado, Inscrito, Completado, Código
de insignia. Las filas coinciden exactamente con lo que muestra la tabla en pantalla bajo los mismos
filtros.

**Formatos de exportación de cursos de un vistazo:**

| Formato | Extensión | Úsalo para… | Acción de auditoría |
|---|---|---|---|
| Paquete | `.tar` | mover un curso completo (contenido + medios) entre servidores Sapiqo o archivarlo | `course.export` |
| Common Cartridge | `.imscc` | importar a Canvas / Blackboard / Moodle / Sakai | `course.export_cc` |
| Datos del curso | `.json` | obtener la estructura sin procesar (lecciones, cuestionarios) sin medios | `course.export_json` |
| Dividir | `.tar` en partes | volver a importar en un servidor con un límite de carga pequeño | `course.export_split` |

El `.tar` no usa compresión a propósito: los medios de los cursos (mp4/png/jpg) ya están
comprimidos, así que gzip agregaría tiempo sin reducir el archivo.

**Exportación dividida.** La página de división enumera cada parte con un enlace de descarga y muestra
el tamaño de fragmento elegido. Las partes se sirven desde una URL con alcance de token solo para administradores.
El tamaño de fragmento se ajusta automáticamente bajo el límite de carga del servidor (90 % del menor de
`upload_max_filesize` / `post_max_size`), opcionalmente limitado por el valor de configuración
`export_chunk_mb`, y nunca por debajo de 256 KB.

## Cómo funciona
Las exportaciones CSV se transmiten directamente al navegador con un encabezado `Content-Disposition:
attachment` y se generan sobre la marcha desde la base de datos actual — las exportaciones de
finalizaciones y auditoría reutilizan la misma consulta (y filtros) que producen las
tablas en pantalla. Las exportaciones de archivos de cursos se construyen con PharData en un directorio
temporal bajo la raíz de datos, se te transmiten y luego se eliminan. Las exportaciones de larga
duración elevan el límite de tiempo del script para que los cursos con muchos medios puedan terminar de empaquetarse.

La exportación/importación de contenido de cursos es simétrica: un paquete `.tar` producido aquí importa
limpiamente en cualquier otra instalación de Sapiqo (ver Importar cursos y usuarios).

## Consejos y trucos
- **La exportación de finalizaciones tiene un tope de 5.000 filas** (el mismo tope que la tabla). Si
  tienes más, acota con filtros y exporta por lotes.
- **La exportación `.json` no contiene medios** — usa el paquete `.tar` si también necesitas las
  imágenes y videos.
- **Common Cartridge es el formato interoperable** para otros LMS; el paquete `.tar`
  es solo de Sapiqo a Sapiqo.
- **Las partes divididas se preparan en el servidor** bajo el directorio de exportación; descarga
  todas, ya que una parte faltante no puede reensamblarse en la importación.
- **La copia de seguridad completa de datos es independiente del contenido de cursos** — los cursos son archivos
  estáticos; respáldalos copiando la carpeta de contenido o exportando cada curso.
  Consulta la página de Copias de seguridad.

## Relacionado
- Gestión de cursos (`30-course-management.md`)
- Importar cursos y usuarios (`31-importing-courses-and-users.md`)
- Informes y finalizaciones (`33-reports-and-completions.md`)
- Copias de seguridad (`34-backups.md`)
- Registro de auditoría (`35-audit-log.md`)
- Libro de calificaciones (`13-gradebook.md`)
