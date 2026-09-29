# Creación con Markdown

**Público:** desarrollador de cursos
**Dónde:** línea de comandos — `python3 creator/build_course.py <file>.md`; publica mediante Admin → Courses → **Rescan drop-in folder** (`/admin/courses`). Alternativa en el navegador: Admin → Courses → **Markdown editor** (`/admin/create`)

## Qué es

El Course Creator convierte un **único archivo Markdown** en una carpeta de curso
completa y lista para ejecutarse: sin importación, sin base de datos, sin
servidor necesario en el momento de la compilación. Escribes un archivo `.md` con
estructura ligera y directivas, ejecutas un comando, y el curso se genera y es
descubierto automáticamente por Sapiqo.

Es totalmente autónomo y sin dependencias: un pequeño renderizador de Markdown
integrado (sin paquetes pip) cubre encabezados, párrafos, listas, citas en
bloque, reglas, negrita/cursiva/código, enlaces e imágenes. Las imágenes
referenciadas por URL se descargan localmente y los archivos locales se copian,
de modo que el curso permanece sin conexión y portátil.

La carpeta `creator/` contiene:

- `build_course.py` — el constructor (Markdown → carpeta de curso)
- `template.md` — un punto de partida anotado para copiar
- `sample-course.md` — un pequeño ejemplo funcional
- `README.md` — la referencia de la sintaxis

## Cómo usarlo

1. Copia `creator/template.md` a `my-course.md` (en cualquier lugar).
2. Edita el **front matter** en la parte superior (entre las líneas `---`):
   `title`, `slug`, `tagline`, y los opcionales `badge`, `tags`, `about`.
3. Estructura el cuerpo con encabezados y directivas (consulta Opciones y
   comportamiento).
4. Compílalo:
   ```bash
   python3 creator/build_course.py my-course.md
   python3 creator/build_course.py my-course.md --transcribe   # + captions for local videos
   ```
5. Publícalo: aparece en el LMS en el siguiente escaneo de cursos, o de inmediato
   cuando haces clic en **Admin → Courses → Rescan drop-in folder**.
6. Para revisar, edita el `.md` y vuelve a ejecutar la compilación: se
   reconstruye en el mismo lugar. La fuente se guarda dentro del curso como
   `source.md` para reconstrucciones futuras.

**¿Sin línea de comandos?** Los administradores que hayan iniciado sesión pueden
crear en el navegador en **Admin → Courses → Markdown editor** (`/admin/create`):
un editor de Markdown de panel dividido con vista previa en vivo y un botón
**Publish** que compila y cataloga el curso al instante, usando la misma
sintaxis. El constructor de línea de comandos sigue siendo la forma de adjuntar
**archivos de video locales + subtítulos automáticos** (`--transcribe`); el
editor en el navegador admite video alojado en plataformas y URLs de
imagen/video.

## Opciones y comportamiento

- **Front matter** (`title`, `slug`, `tagline`, `tags`, `badge`, `about`):
  `slug` se convierte en el nombre de la carpeta y la URL (en minúsculas con
  guiones; derivado del título si se omite). `about` se convierte en la vista
  general de la página de inicio del curso. `badge` apunta a una imagen junto al
  `.md`; sin una, el curso usa el medallón genérico.
- **Estructura por nivel de encabezado:**
  - `# ` → un **módulo**. A los títulos se les dan etiquetas inteligentes:
    *Welcome* / *Start…* → "Start Here"; un título que contenga *Certificate* /
    *Badge* → "Finish"; *Module N* → "Module N".
  - `## ` → una **lección** (página) dentro del módulo actual.
  - `### ` / `#### ` → encabezados **dentro** de una lección.
  - Markdown normal: párrafos, `**bold**`, `*italic*`, `` `code` ``, listas
    `-`/`1.`, `> quotes`, e imágenes `![alt](img)`.
- **Directivas** (cada una en su propia línea):
  - `@video <url>` — una URL de **Vimeo**, una URL de **YouTube**, o
    `@video local:clip.mp4` para un archivo local (copiado; agrega
    `--transcribe` para subtítulos). YouTube/Vimeo se renderizan como iframes
    responsivos respetuosos con la privacidad; los archivos locales renderizan un
    reproductor HTML5.
  - `@embed <url>` — incrustación general para Vimeo/YouTube, Google
    Slides/Docs/Drive, o una URL directa `.mp4`/`.webm` (reproductor/iframe en
    línea); cualquier otra cosa se convierte en un enlace simple.
  - `@resource [Title](https://…) optional note` — agrupado en una lista de
    **Resources** al final de la lección.
  - `@image path-or-url "alt"` — una imagen con texto alternativo.
  - `@quiz` … `@endquiz` — una verificación de conocimientos.
- **Sintaxis de cuestionario** (entre `@quiz` y `@endquiz`):
  - Opciones: `pass: 70` (porcentaje para aprobar), `shuffle: true` (aleatorizar
    el orden de las opciones), `pick: 5` (preguntar un subconjunto aleatorio de N
    preguntas — un banco de preguntas), `attempts: 3` (intentos máximos).
  - Tipos de pregunta:
    - `Q: question?` (agrega `(multiple)` para selección múltiple) con líneas
      `- choice` / `- *correct` (un prefijo `*` marca la opción correcta).
      También puedes marcar las respuestas con `= exact option text`.
    - `TF: statement` seguido de `= true` o `= false`.
    - `SA: question?` seguido de una o más líneas `= accepted answer`
      (coincidencia sin distinguir mayúsculas/espacios).
    - `feedback: explanation` después de una pregunta (se muestra una vez
      respondida).
  - La calificación es **del lado del servidor** en el LMS y las claves de
    respuesta se eliminan de los datos enviados a los navegadores de los
    estudiantes. Un estudiante debe **aprobar** para que el cuestionario cuente
    hacia la finalización / la insignia.
- **`--transcribe`** genera subtítulos en inglés (`.vtt`) para videos locales
  usando OpenAI Whisper (`WHISPER_BIN`, predeterminado
  `whisper`, modelo `turbo`) y adjunta un `<track>` a
  cada incrustación de video local.

## La carpeta de salida

Ejecutar el constructor escribe `courses/<slug>/` (dentro de la carpeta
`content/`, o `SAPIQO_COURSES` si está configurada) que contiene:

- **`course.json`** — el curso compilado: front matter, `stats` (modules,
  lessons, videos, quizzes…), y `modules[] → lessons[]` con `content` HTML,
  `videos`, y cualquier `quiz`.
- **`index.html`** — el contenedor del lector que renderiza el curso en
  `/courses/<slug>/`.
- **`media/`** — imágenes replicadas; **`media/videos/`** — videos locales
  copiados (y subtítulos `.vtt` cuando se transcriben).
- **`badge.png`** — si proporcionaste uno.
- **`source.md`** — una copia de tu Markdown, conservada para las
  reconstrucciones.

La consola imprime la ruta de salida y un resumen por módulo, terminando con:
*"Drop-in ready — it will appear in the LMS on the next course scan."*

## Cómo funciona

- `build_course.py` analiza el front matter, divide el cuerpo en
  módulos/lecciones por encabezado, expande las directivas, replica los medios y
  renderiza el HTML de cada lección con el convertidor de Markdown integrado.
- Los videos se detectan por proveedor (YouTube/Vimeo) o se tratan como locales;
  `@embed` maneja además Google Docs/Drive y URLs de video directas.
- El resultado se escribe como `course.json` más el contenedor del lector, los
  medios replicados y la insignia.
- Sapiqo **descubre automáticamente** cualquier subcarpeta del directorio de
  cursos que contenga un `course.json`. **Rescan drop-in folder** ejecuta
  `scan_courses()`, registrando cursos nuevos, reactivando los restaurados y
  desactivando los eliminados, e informa cuántos de cada uno.
- El **Markdown editor** en el navegador (`/admin/create` → Publish) usa la misma
  sintaxis y compila + cataloga el curso en un solo paso.

## Consejos y trampas

- **Volver a ejecutar reconstruye en el mismo lugar.** Editar el `.md` y ejecutar
  el constructor de nuevo regenera la carpeta; el `source.md` almacenado también
  te permite reeditar un curso desde **Admin → Courses → ⋯ → Edit Markdown
  source**.
- **Mantén los medios locales.** Las imágenes por URL se descargan y las rutas
  locales se copian, de modo que el curso permanece sin conexión/portátil;
  referencia los archivos de forma relativa al `.md`.
- **Markdown frente al editor visual.** Un curso construido aquí también puede
  abrirse en el editor de bloques visual, pero ten en cuenta que guardar allí lo
  cambia a creado con bloques (`course.json` se convierte en la fuente de
  verdad); mezclar flujos de trabajo en el mismo curso puede resultar confuso.
  Elige uno como tu método principal.
- **`--transcribe` necesita Whisper instalado** en `WHISPER_BIN`; sin él, la
  bandera se omite con una advertencia y no se generan subtítulos.
- Si un curso nuevo no aparece, confirma que su carpeta esté dentro del
  directorio de cursos (`content/`, o `SAPIQO_COURSES`) con un `course.json`
  válido, luego haz clic en **Rescan drop-in folder**.
- Para cursos de **LearnDash importados** usa `scripts/build.py` en su lugar;
  este creador es para crear cursos nuevos a mano.

## Relacionado

- [Editor de bloques visual](10-visual-block-editor.md)
- [Centro de recursos](12-resource-center.md)
- [Libro de calificaciones](13-gradebook.md) — resultados de cuestionarios de bloques `@quiz`
- [Configuración del curso](11-course-settings.md)
