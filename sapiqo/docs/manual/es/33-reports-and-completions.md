# Informes y finalizaciones

**Audiencia:** administrador
**Dónde:** Informes de capacitación — `/admin/reports`; Finalizaciones — `/admin/completions`

## Qué es
Dos pantallas relacionadas responden "¿cómo va la capacitación?":

- **Informes de capacitación** (`/admin/reports`) es un panel: números principales, un
  gráfico circular de tasa de finalización, un gráfico de actividad de 8 semanas, rendimiento por curso, y
  desgloses por organización, campus, tipo de usuario y grupo — además de un feed de actividad
  reciente. Cada gráfico se dibuja como SVG en línea, así que el panel funciona sin
  servicios externos y sigue siendo apto para uso sin conexión.
- **Finalizaciones** (`/admin/completions`) es la vista a nivel de fila: cada inscripción
  con su estado, fechas y credencial, una barra de herramientas de filtros y una exportación CSV
  que respeta los filtros.

## Cómo usarla

### Informes de capacitación
1. Ve a **Informes de capacitación** (`/admin/reports`).
2. Lee la **franja de KPI** de la parte superior: Total de usuarios, Inscripciones, Finalizaciones,
   En progreso, Estudiantes activos, Insignias emitidas.
3. Usa los dos gráficos: el gráfico circular de **Tasa de finalización general** y el gráfico de barras
   agrupadas de **Actividad (últimas 8 semanas)** (Registrados / Inscritos / Completados).
4. Revisa las tablas: **Rendimiento del curso**, **Por organización**, **Por campus**,
   **Por tipo de usuario**, **Por grupo** (cuando existen grupos) y el feed de **Actividad reciente**.
5. Para exportar, usa la barra de herramientas: **⬇ Exportar resumen CSV** (`/admin/reports.csv`) o
   **⬇ Exportar finalizaciones CSV**.

### Finalizaciones
1. Ve a **Finalizaciones** (`/admin/completions`).
2. Acota la lista con la **barra de herramientas de filtros** (búsqueda, grupo, organización,
   campus, estado). Los menús desplegables de estado, grupo, organización y campus se envían automáticamente;
   la búsqueda usa el botón **Buscar**.
3. Lee la columna de credencial de cada fila: haz clic en la miniatura de la **insignia** para verla,
   **Insignia** para descargar el PNG de la insignia, **Cert** para el PDF del certificado, y
   **Expediente** para el expediente completo del estudiante.
4. Elige **⬇ Exportar CSV** para descargar exactamente las filas mostradas (ver más abajo).
5. Elige **Limpiar** para restablecer todos los filtros.

## Opciones y comportamiento

**Informes de capacitación — qué muestra cada panel**
- **Franja de KPI.** Recuentos de usuarios, inscripciones, finalizaciones, inscripciones
  en progreso, estudiantes activos (cualquiera con progreso registrado) e insignias emitidas.
- **Gráfico circular de tasa de finalización.** Inscripciones completadas como porcentaje de todas las
  inscripciones, con "X de Y inscripciones completadas" debajo.
- **Actividad (últimas 8 semanas).** Cubetas semanales de nuevos registros, nuevas
  inscripciones y finalizaciones.
- **Rendimiento del curso.** Por curso: Inscritos, Completados, Tasa de finalización, y una
  barra de **Progreso promedio** (basada en pasos completados frente a inscritos × total de unidades).
- **Por organización / Por campus / Por tipo de usuario.** Consolidados con recuentos de usuarios,
  finalizaciones y tasas de finalización. Las organizaciones en blanco se muestran como `(none)`; los tipos de
  usuario en blanco se muestran como `(unspecified)`.
- **Por grupo.** Miembros, inscripciones, finalizaciones, tasa de finalización y pasos
  completados, con cada nombre de grupo enlazando a su página de detalle.
- **Actividad reciente.** Un feed combinado y ordenado por tiempo de registros, inscripciones
  y finalizaciones.

**Finalizaciones — la tabla**
Las columnas son **Nombre**, **Correo electrónico**, **Organización**, **Curso**, **Estado**,
**Completado** y **Credencial**. El estado muestra una etiqueta verde de **completado** o una
etiqueta ámbar de **inscrito**. El nombre enlaza a la página de administración del usuario. La celda de Credencial
muestra la insignia y los botones de descarga solo cuando existe un código de insignia para esa
inscripción; el enlace de Expediente siempre está presente.

**Finalizaciones — la barra de herramientas de filtros**
- **Búsqueda** — coincide con nombre, apellido o correo electrónico.
- **Grupo** — limita a los miembros de un grupo.
- **Organización** y **Campus** — se completan a partir de los valores de las cuentas de usuario.
- **Estado** — Inscrito o Completado (o cualquiera).

**Exportación CSV que respeta los filtros.** El enlace **Exportar CSV** lleva tus filtros
actuales en su URL, así que la descarga coincide con lo que ves. Cuando hay algún filtro activo, la
etiqueta del botón dice **Exportar CSV (filtrado)**. Columnas: Nombre, Apellido,
Correo electrónico, Campus, Organización, Tipo de usuario, Curso, Estado, Inscrito, Completado, Código
de insignia. El archivo es `sapiqo-completions.csv`.

**Tope de filas.** La vista de Finalizaciones (y su exportación) devuelve hasta **5.000 filas**;
cuando se alcanza ese tope, la página indica "mostrando las primeras 5000 — acota con filtros".

## Cómo funciona
Los informes ejecutan consultas SQL de agregación y agrupan fechas en PHP para que se comporten igual
en SQLite y MySQL. Los gráficos se generan como SVG en línea (`svg_donut` y
`svg_bar_chart`) sin biblioteca de gráficos. La lista de Finalizaciones es una única consulta
unida sobre inscripciones → usuarios → cursos, con unión externa izquierda a insignias para recoger el
código de insignia, filtrada por los parámetros de la barra de herramientas y ordenada por estado y luego por nombre.
La exportación CSV reutiliza las mismas filas.

## Consejos y trucos
- **Existen dos exportaciones de finalizaciones.** La de la página de Finalizaciones respeta tus
  filtros; la de la barra de herramientas de Informes (y la tarjeta de Exportaciones de administración) es la
  `/admin/completions?export=1` sin filtrar.
- **El tiempo dedicado a las tareas y las puntuaciones de cuestionarios no forman parte de este panel.** El reporte se
  basa en la finalización de pasos, la inscripción y las insignias (ver la nota al pie de
  la página de Informes). Para las puntuaciones de evaluación, usa el Libro de calificaciones.
- **Las opciones de filtro de organización/campus provienen de los campos de la cuenta** — mantenlos
  limpios en la importación (o mediante la edición de usuarios) para que los desgloses y filtros sean significativos.
- **Los botones de Credencial son la forma más rápida de volver a emitir** un PNG de insignia o un
  PDF de certificado para un estudiante que necesita una copia.
- **Las filas de organización en blanco aparecen como `(none)`** en el desglose por organización; esa es una
  cubeta real, no un error.

## Relacionado
- Detalles de la exportación de finalizaciones (`32-exports.md`)
- Gestión de cursos (`30-course-management.md`)
- Insignias, certificados y expediente (`05-badges-certificates-transcript.md`)
- Libro de calificaciones (`13-gradebook.md`)
- Grupos y responsables (`22-groups-and-managers.md`)
