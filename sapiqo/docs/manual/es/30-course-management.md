# Gestión de cursos

**Audiencia:** administrador
**Dónde:** Gestionar cursos — `/admin/courses`

## Qué es
La pantalla de **Cursos** es donde gestionas todo el catálogo de cursos. Muestra una
fila por curso con su estado, cantidad de unidades y cantidad de inscripciones, además de las herramientas
para crear cursos, editarlos, ocultarlos o restaurarlos, clonarlos y exportarlos, y
volver a sincronizar el catálogo con las carpetas de cursos del disco.

Dos hechos determinan todo en esta página:

- **Los cursos son carpetas en el disco.** Cada curso vive en su propia carpeta dentro del
  directorio de contenido de cursos (la carpeta "drop-in"), y cada carpeta contiene un
  `course.json` que describe el curso. La tabla del catálogo se mantiene sincronizada con
  esas carpetas.
- **Ocultar no es eliminar.** Ocultar un curso lo quita del catálogo del estudiante
  pero conserva su carpeta, sus insignias, certificados, inscripciones y progreso. La
  única manera de eliminar permanentemente un curso es borrar su carpeta del disco — e
  incluso entonces, las insignias y el progreso del estudiante permanecen en la base de datos.

## Cómo usarla
1. Ve a **Gestionar cursos** (`/admin/courses`). La barra de herramientas de la parte superior contiene las
   acciones de crear/importar/escanear; la tabla de abajo enumera todos los cursos.
2. **Crear un curso visualmente:** escribe un título en el cuadro **Título del nuevo curso** y
   elige **Crear (visual)**. Esto abre el editor visual de bloques en un curso nuevo y
   vacío.
3. **Crear un curso en Markdown:** elige **Editor de Markdown** para crear con el
   creador basado en Markdown en su lugar (`/admin/create`).
4. **Editar un curso existente:** en su fila, elige **✏ Editar** para abrir el editor
   visual de bloques (páginas, bloques, medios, cuestionarios y configuración del curso). Si el
   curso está disponible, **Abrir** lanza la vista en vivo del estudiante en una pestaña nueva.
5. **Haz más desde el menú ⋯:** cada fila tiene un menú kebab **⋯** ("Más acciones")
   con configuración del curso, edición de la fuente, clonación, opciones de exportación y ocultar/restaurar
   (ver más abajo).
6. **Volver a sincronizar carpetas:** después de copiar una carpeta de curso en el servidor (o
   quitar una), elige **Reescanear carpeta drop-in** para actualizar el catálogo.
7. **Importar en lugar de crear:** elige **Importar cursos / usuarios** para ir a la
   pantalla de Importaciones para paquetes, Common Cartridge, SCORM, LearnDash y usuarios.

## Opciones y comportamiento

**La tabla de cursos**
Las columnas son **Curso** (título + slug), **Estado**, **Unidades**, **Inscritos** y
**Acciones**. El estado muestra una etiqueta verde de **Disponible** para los cursos activos o una
etiqueta ámbar de **Oculto** para los desactivados. **Unidades** es el número total de
pasos descubiertos en el curso; **Inscritos** es el número de estudiantes inscritos.

**El menú ⋯ (kebab)**
El kebab abre un menú con estos elementos:

- **⚙ Configuración del curso** — salta a la pestaña Configuración del editor visual
  (`/admin/editor/<slug>#settings`).
- **Editar fuente Markdown** — abre el creador de Markdown en este curso. Se muestra
  solo cuando el curso tiene una fuente Markdown editable.
- **Clonar** — abre el creador de Markdown precargado con una copia de este curso;
  revisas el slug/título y lo publicas como un curso completamente nuevo. Se muestra solo cuando
  el curso tiene una fuente editable.
- **Exportar ▸ A otro LMS (Common Cartridge)** — una página guiada de exportación `.imscc`.
- **Exportar ▸ Paquete (.tar)** — el paquete completo del curso (contenido + medios).
- **Exportar ▸ Datos del curso (.json)** — el `course.json` sin procesar.
- **Exportar ▸ Dividir (cargas grandes)** — el `.tar` dividido en partes pequeñas para
  servidores con límites de carga estrictos.
- **Ocultar del catálogo / Restaurar al catálogo** — alterna la visibilidad del curso.
  La confirmación te recuerda que las insignias y el progreso del estudiante se conservan.

(Los formatos de exportación se cubren en detalle en la página de Exportaciones.)

**Reescanear carpeta drop-in**
Elige **Reescanear carpeta drop-in** (o la acción **Reescanear cursos** en el hub de
administración) para reconciliar el catálogo con las carpetas del disco. En cada escaneo Sapiqo:

- **Agrega** cualquier carpeta nueva que contenga un `course.json` válido (con un `slug`).
- **Actualiza** el título, la ruta, la cantidad de unidades y la imagen de insignia de los cursos existentes.
- **Desactiva** (elimina de forma suave) un curso cuya carpeta ha desaparecido — la fila
  y todos los datos del estudiante se conservan, así que restaurar la carpeta lo trae de vuelta.
- **Reactiva** un curso cuya carpeta ha reaparecido.

El mensaje de resultado informa cuántos se agregaron, reactivaron y desactivaron (y
nombra lo que se haya quitado). Una carpeta que contiene un archivo marcador `.disabled` se escanea
como **Oculto**; ese marcador sobrevive a los reescaneos.

**Configuración por curso**
Cada curso lleva varias configuraciones que los administradores (y los desarrolladores de cursos) pueden establecer.
La mayoría se alcanzan desde **⚙ Configuración del curso** en el editor; cada una se guarda con su
propia acción y se registra en el registro de auditoría:

- **Prerrequisito** (`/admin/courses/<slug>/prereq`) — requiere que otro curso se
  complete primero. Sapiqo impide que un curso sea su propio prerrequisito y
  rechaza un cambio que crearía un prerrequisito circular directo.
- **Horas CPE** (`/admin/courses/<slug>/cpe`) — horas de crédito de educación continua
  impresas en el certificado. Acepta un decimal (coma o punto); los negativos se ajustan
  a 0.
- **Expiración de la inscripción** (`/admin/courses/<slug>/expiry`) — días hasta que una
  inscripción expira; **0** significa sin expiración.
- **Modo secuencial** (`/admin/courses/<slug>/sequential`) — si las lecciones deben
  completarse en orden. Tres opciones: **heredar** el valor global predeterminado, **activado**
  (orden bloqueado) o **desactivado** (cualquier orden).
- **Modo de foro** (`/admin/courses/<slug>/forum`) — **desactivado**, **activado** (discusión
  abierta) o **restringido** ("publica antes de ver" — los participantes deben publicar
  antes de poder leer las publicaciones de otros).

## Cómo funciona
La tabla del catálogo se deriva del disco mediante el escáner de cursos. Para cada carpeta
bajo el directorio de contenido lee `course.json`, requiere un `slug` no vacío,
e inserta/actualiza una fila (título, ruta, total de unidades, imagen de insignia, indicador de activo). Una carpeta
de curso con un archivo `.disabled` se registra como inactiva. Los cursos cuya carpeta ha
desaparecido se marcan como inactivos en lugar de eliminarse, por lo cual ocultar, eliminar y
restaurar nunca pierden insignias ni progreso.

La cantidad de unidades proviene de las estadísticas del curso: para los cursos SCORM es la cantidad
de unidades del paquete (al menos 1); para los cursos nativos es la suma de
lecciones + temas + cuestionarios.

La resolución de insignias durante un escaneo busca primero un `badge.png/.jpg/.jpeg/.svg` en
la carpeta del curso (autocontenido), luego una coincidencia de nombre en la biblioteca compartida
de insignias, y de lo contrario recurre a un marcador de posición.

Ocultar un curso escribe/quita el marcador `.disabled` y vuelve a escanear; por eso un
curso oculto permanece oculto en futuros reescaneos hasta que lo restaures.

## Consejos y trucos
- **Para eliminar permanentemente un curso, quita su carpeta** del directorio de
  contenido, luego reescanea. Las insignias y el progreso del estudiante permanecen en la base de datos.
- **Prefiere "Ocultar" antes que eliminar** cuando solo quieras sacar un curso del catálogo
  temporalmente — es totalmente reversible y mantiene la carpeta en su lugar.
- **Clonar / Editar fuente Markdown solo aparecen para los cursos que tienen una fuente
  Markdown.** Los cursos importados como SCORM o Common Cartridge, o creados puramente en el
  editor visual, pueden no mostrar estos elementos.
- **Después de copiar una carpeta en el servidor, debes Reescanear** para que aparezca —
  las carpetas nuevas no se detectan automáticamente en cada carga de página.
- **Los cursos muy grandes** (cientos de MB de video) pueden exceder el límite de carga
  web. Usa la exportación/importación **Dividir**, coloca la carpeta directamente y
  reescanea, o aumenta `upload_max_filesize` / `post_max_size`.
- El número de la columna **Unidades** impulsa el cálculo del progreso promedio en
  los informes de capacitación, así que cambia cuando editas un curso y reescaneas.

## Relacionado
- Importar cursos y usuarios (`31-importing-courses-and-users.md`)
- Exportaciones (`32-exports.md`)
- Informes y finalizaciones (`33-reports-and-completions.md`)
- Editor visual de bloques (`10-visual-block-editor.md`)
- Configuración del curso (`11-course-settings.md`)
