# Centro de recursos

**Público:** desarrollador de cursos
**Dónde:** Admin → Courses (`/admin/courses`) → ✏ Edit → **Resources** (barra superior) → `/admin/resources/<slug>`; también los botones **Pick / upload** y **Upload new…** dentro del selector de recursos del editor

## Qué es

El centro de recursos es una biblioteca de archivos por curso. Cada archivo que
subes vive dentro de la carpeta `media/` de ese curso, mantiene el curso
autónomo y puede reutilizarse en cualquier bloque mediante el selector. Maneja
imágenes, videos, PDFs, documentos de Office y archivos de subtítulos.

Hay dos formas de acceder:

- La página dedicada **Resources** (`/admin/resources/<slug>`): una galería para
  subir en lote, previsualizar, abrir y eliminar archivos.
- El **selector de recursos** dentro del editor: un modal que te permite elegir
  un archivo existente o subir uno nuevo directamente a un bloque Image, Video o
  File.

## Cómo usarlo

**Desde la página Resources:**

1. Abre el curso en el editor y haz clic en **Resources** en la barra superior.
2. Haz clic en **Choose files…** y selecciona uno o más archivos. Se suben uno a
   la vez con un conteo de progreso y luego la página se recarga.
3. Explora las subidas agrupadas por tipo (Images, Videos, PDFs, Documents,
   Captions, Other files). Cada tarjeta muestra una miniatura/icono, la etiqueta
   o el nombre del archivo, el tamaño y su ruta relativa al curso (p. ej.
   `media/worksheet.pdf`).
4. Usa **Open** para ver un archivo en una nueva pestaña, o **Delete** para
   eliminarlo (se te advierte que las páginas que lo referencian mostrarán un
   enlace roto).

**Desde dentro de un bloque (editor):**

1. Agrega un bloque **Image**, **Video / embed** (modo Self-hosted file) o
   **File**.
2. Haz clic en **Pick / upload** junto al campo de URL.
3. En el selector, haz clic en un archivo existente para insertarlo, o haz clic
   en **Upload new…** para subir uno: se inserta en el bloque automáticamente.

## Opciones y comportamiento

- **Tipos de archivo permitidos** (por extensión): `png`, `jpg`, `jpeg`, `gif`,
  `webp`, `svg`, `mp4`, `webm`, `m4v`, `mov`, `vtt`, `srt`, `pdf`, `doc`, `docx`,
  `ppt`, `pptx`, `xls`, `xlsx`, `odt`, `txt`, `csv`. Cualquier otro se rechaza
  con *"That file type is not allowed."*
- **Detección de tipo** (determina los iconos y el filtrado del selector):
  - image → `png/jpg/jpeg/gif/webp/svg`
  - video → `mp4/webm/m4v/mov`
  - caption → `vtt/srt`
  - pdf → `pdf`
  - doc → `doc/docx/ppt/pptx/xls/xlsx/odt/txt/csv`
  - file → cualquier otro permitido
- **Dónde caen los archivos:** todo va dentro del directorio `media/` del curso;
  **los videos se almacenan en `media/videos/`**. El valor almacenado en un
  bloque es la ruta relativa (p. ej. `media/videos/intro.mp4`).
- **Los nombres duplicados** se sufijan automáticamente (`worksheet.pdf`,
  `worksheet-2.pdf`, …) para que una subida nunca sobrescriba un archivo
  existente.
- **Filtrado del selector:** el selector del bloque Image muestra solo imágenes;
  el selector de Video autoalojado muestra solo videos; el selector del bloque
  File muestra **todos** los tipos de recursos. Subir a través del selector
  inserta el archivo en el bloque de inmediato.
- **Etiquetas:** la página Resources muestra una etiqueta legible cuando se ha
  definido una (de lo contrario, el nombre del archivo). Las etiquetas se
  almacenan en `media/resources.json` indexadas por ruta; la subida en línea del
  editor almacena los archivos sin etiqueta de forma predeterminada.
- **Eliminar** quita el archivo del disco y borra sus metadatos de etiqueta. Está
  confinado al árbol `media/` del curso (sin recorrido de rutas); las páginas que
  aún apunten a un archivo eliminado renderizarán un enlace roto.

## Cómo funciona

- Las subidas se envían a `POST /api/editor/<slug>/upload` (multipart, protegido
  con CSRF). El servidor valida la extensión, sanea el nombre base del archivo,
  enruta los videos hacia `media/videos/`, elimina duplicados del nombre y
  devuelve la ruta relativa más una URL completa.
- La lista de recursos proviene de `editor_resources()`, que recorre de forma
  recursiva la carpeta `media/` del curso e informa la ruta, el nombre, el tipo,
  el tamaño y la etiqueta de cada archivo (omitiendo el propio `resources.json`).
- Las eliminaciones se envían a `POST /api/editor/<slug>/resource/delete` y se
  validan para permanecer dentro de `media/` mediante comprobaciones `realpath`.
- Como los archivos viven dentro de la carpeta del curso, viajan con el curso
  cuando se exporta/empaqueta y se sirven desde `/courses/<slug>/media/…`.
- Las subidas y eliminaciones se registran en el registro de auditoría
  (`course.resource_upload`, `course.resource_delete`).

## Consejos y trampas

- **Sube una vez, reutiliza en cualquier lugar.** Un archivo en el centro de
  recursos puede insertarse en tantos bloques (y páginas) como quieras mediante
  el selector.
- **Eliminar un recurso no actualiza las páginas** que lo referencian: esos
  bloques mostrarán una imagen/enlace roto hasta que los arregles.
- Para **subtítulos**, sube el `.vtt`/`.srt` junto (o después) del video; la
  bandera `--transcribe` del constructor de línea de comandos también puede
  generar subtítulos para videos locales automáticamente (consulta Creación con
  Markdown).
- Prefiere subir el video a través del centro de recursos y usar el modo de video
  **Self-hosted file**; para video alojado en plataformas (YouTube, Vimeo, Drive,
  etc.) usa el modo **Embed / link** en su lugar: esos no son archivos que subas
  aquí.
- La **ruta relativa al curso** que se muestra en cada tarjeta (p. ej.
  `media/pic.png`) es exactamente lo que puedes pegar en el campo de URL de un
  bloque si prefieres no usar el selector.
- Las subidas muy grandes están limitadas por los valores `upload_max_filesize` /
  `post_max_size` del servidor; si una subida falla en silencio, revisa esos
  límites de PHP.

## Relacionado

- [Editor de bloques visual](10-visual-block-editor.md) — bloques Image, Video y File
- [Creación con Markdown](14-markdown-authoring.md) — video local + `--transcribe`
- [Configuración del curso](11-course-settings.md)
