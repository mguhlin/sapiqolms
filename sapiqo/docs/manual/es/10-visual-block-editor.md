# Editor de bloques visual

**Público:** desarrollador de cursos
**Dónde:** Admin → Courses (`/admin/courses`) → ✏ Edit en cualquier curso → `/admin/editor/<slug>`

## Qué es

El editor de bloques visual es la herramienta de Sapiqo, dentro del navegador y
al estilo de un procesador de texto, para construir un curso sin tocar Markdown
ni código. Un curso es un conjunto de **módulos**, cada uno con **páginas** (y
opcionalmente **foros de discusión**). Cada página se construye a partir de
**bloques** apilables: texto, encabezados, imágenes, video, archivos y
separadores.

- El curso se sigue almacenando como `course.json`; el editor mantiene un arreglo
  `blocks` editable en cada página y, al guardar, **compila esos bloques en el
  HTML** que ven los estudiantes en el lector.
- Los cursos existentes construidos a partir de Markdown se abren sin problemas:
  una página sin bloques se envuelve como un solo bloque de texto enriquecido que
  contiene su HTML actual, para que puedas empezar a editar de inmediato.
- Los cursos creados aquí quedan marcados como creados con la interfaz gráfica
  (`"editor": "blocks"` en `course.json`).

La pantalla tiene tres partes: una **barra superior** (título, Settings,
Resources, Preview, Save), un **esquema** a la izquierda (módulos, páginas,
foros) y el **editor de páginas** a la derecha, donde construyes los bloques.

## Cómo usarlo

1. Ve a **Admin → Courses** y haz clic en **✏ Edit** en el curso que quieres
   crear. Para empezar un curso completamente nuevo, escribe un título en el
   cuadro **New course title** y haz clic en **Create (visual)**: aterrizas
   directamente en el editor.
2. Edita el **título del curso** en el campo de la barra superior (también sirve
   como el `<title>`).
3. Construye tu esquema en el panel izquierdo:
   - Haz clic en **＋ Add module** al final del esquema para agregar un módulo.
   - Haz clic en el icono **＋** en la fila del encabezado de un módulo para
     agregarle una página.
   - Renombra un módulo directamente en su campo de encabezado; renombra una
     página editando su **Page title** en la parte superior del editor de
     páginas.
4. Agrega contenido a una página haciendo clic en un botón de bloque debajo de la
   página: **＋ Text**, **＋ Markdown**, **＋ Heading**, **＋ Image**,
   **＋ Video / embed**, **＋ File** o **＋ Divider**.
5. Reordena o elimina bloques con los controles de cada bloque: **↑** (subir),
   **↓** (bajar), **🗑** (eliminar bloque).
6. Reordena páginas y módulos arrastrándolos (consulta Opciones y comportamiento).
7. Haz clic en **Save** (arriba a la derecha), o presiona **Ctrl/Cmd + S**, en
   cualquier momento.
8. Haz clic en **Preview ↗** para abrir el lector en vivo del curso en una nueva
   pestaña.

## Opciones y comportamiento

- **Tipos de bloque** (conjunto exacto, en el orden en que aparecen en la barra
  de agregar):
  - **Text** — un bloque de texto enriquecido (`richtext`) con la barra de
    herramientas de formato completa.
  - **Markdown** — un área de texto simple; su Markdown se convierte a HTML al
    guardar.
  - **Heading** — un selector de H2/H3/H4 más el texto del encabezado.
  - **Image** — selector de URL/recurso, texto alternativo, leyenda y alineación.
  - **Video / embed** — ya sea una URL de incrustación/enlace o un archivo
    autoalojado.
  - **File** — un archivo adjunto descargable (texto del enlace + descripción
    opcional).
  - **Divider** — una regla horizontal (`<hr>`).
- **Agregar módulos/páginas:** un curso nuevo siempre conserva al menos un
  módulo; el editor impide eliminar el último. Al agregar una página, esta se
  selecciona de inmediato.
- **Reordenamiento por arrastrar y soltar:**
  - **Páginas** — arrastra la fila de una página por su asa **⠿**; suéltala en
    cualquier lugar dentro de la misma lista de páginas o de la de otro módulo.
    Un contorno punteado muestra el destino de la caída.
  - **Módulos** — arrastra el asa **⠿** del encabezado del módulo para reordenar
    módulos completos (sus páginas viajan con ellos).
- **Barra de herramientas de texto enriquecido** (en cada bloque Text), de
  izquierda a derecha:
  - Menú desplegable **Paragraph**: Normal, Heading, Subheading, Quote.
  - Menú desplegable **Size**: Small, Normal, Large, X-Large, Huge.
  - **B / I / U / S** — negrita, cursiva, subrayado, tachado.
  - Selectores de color de **Text color** y **Highlight**.
  - **Alignment**: alinear a la izquierda, al centro, a la derecha, justificar.
  - **Lists**: con viñetas (•) y numeradas (1.).
  - **Indent**: disminuir (⇤) y aumentar (⇥). La sangría ajusta el margen
    izquierdo del bloque en pasos de 40px en lugar de envolver el texto en una
    cita en bloque.
  - Menú **⊞ Table**: Insert table…, Add row, Add column, Delete row, Delete
    column, Delete table.
  - **🔗** insertar enlace, **⛓** quitar enlace, **⌫** borrar formato.
  - **↶ / ↷** deshacer / rehacer.
- **Pegado limpio:** pegar desde Google Docs o Word conserva la estructura y el
  formato significativos (encabezados, negrita/cursiva, listas, enlaces, tablas)
  pero elimina clases, ids, envoltorios basura, los contenedores de negrita falsa
  de Google y todos los estilos en línea salvo un subconjunto seguro. Si el
  portapapeles no tiene HTML, se inserta texto sin formato.
- **Cambio de tamaño de imágenes:** haz clic en una imagen dentro de un bloque
  Text para mostrar un asa de redimensionamiento azul marino en su esquina
  inferior derecha; arrástrala para fijar el ancho (mínimo 24px, la altura queda
  automática).
- **Cambio de tamaño de columnas de tabla:** pasa el cursor sobre el borde
  derecho de una celda de tabla dentro de un bloque Text (el cursor se convierte
  en una flecha de redimensionamiento de columna) y arrastra para dimensionar esa
  columna en todas las filas.
- **Alineación del bloque Image:** fichas para **Full width**, **Left**,
  **Center**, **Right**, más una casilla **Wrap text around image** que solo se
  habilita para la alineación Left/Right (las imágenes envueltas flotan hasta un
  ancho del 48%).
- **Bloque Video / embed:**
  - El modo **Embed / link** acepta YouTube, Vimeo, Google Drive/Docs,
    OneDrive/SharePoint, Dropbox o una URL directa `.mp4`. La sugerencia del
    campo dice: *"YouTube, Vimeo, Google Drive, OneDrive, Dropbox, or .mp4 URL."*
  - El modo **Self-hosted file** te permite elegir un video subido desde los
    recursos.
  - Una nota te recuerda: *"For OneDrive/Dropbox, use the 'Embed' or share link."*
- **Bloque File:** elige un archivo (o pega una URL); si no defines el texto del
  enlace, se usa el nombre del archivo. Se renderiza como `📎 <name>` con una
  descripción opcional.
- **Verificación de conocimientos (cuestionario):** debajo de los bloques, marca
  **Knowledge check (quiz) on this page** para agregar un cuestionario sencillo:
  define un **Pass %**, agrega preguntas, agrega opciones y marca la(s)
  correcta(s). Una página con un cuestionario guardado muestra una insignia
  **✓ quiz** en el esquema.
- **Eliminar una página:** haz clic en el **🗑** de la fila de la página (aparece
  al pasar el cursor o cuando la página está activa). Se te pide confirmar; la
  nota advierte que la eliminación es permanente una vez que guardas.
- **Eliminar un módulo:** haz clic en la **✗** del encabezado del módulo. Debes
  conservar al menos un módulo; eliminar uno que tiene páginas te pide confirmar
  la cantidad de páginas.

## Cómo funciona

- Al cargar, el editor obtiene `GET /api/editor/<slug>`, que devuelve la
  estructura completa del curso (con `blocks`), la lista de recursos y la
  configuración del curso.
- Cada edición activa una marca de **sin guardar** y muestra *"Unsaved changes."*
  Salir de la página con trabajo sin guardar activa el aviso de confirmación de
  salida del navegador.
- **Save** envía toda la estructura como JSON a `POST /api/editor/<slug>`. El
  servidor compila cada bloque en HTML del lector y escribe `course.json`:
  - `richtext` → HTML saneado (se eliminan scripts, estilos, formularios,
    controladores de eventos en línea y URIs `javascript:`; solo sobreviven los
    iframes `https://`).
  - `markdown` → HTML mediante el renderizador de Markdown integrado.
  - `heading` → `<h2>`–`<h4>`.
  - `image` → un `<figure>` con estilos de alineación/envoltura y una leyenda
    opcional.
  - `video` → una incrustación de proveedor
    (YouTube/Vimeo/Drive/OneDrive/Dropbox/MP4) o un `<video>` autoalojado; cada
    video también se registra en el arreglo `videos` de la página para las
    estadísticas.
  - `file` → un enlace de descarga `📎`; `divider` → `<hr>`.
- Después de escribir `course.json`, el servidor se asegura de que exista un
  contenedor del lector (`index.html`) y vuelve a escanear los cursos para que el
  catálogo, los totales de unidades y el cálculo de finalización se mantengan
  sincronizados.
- Las rutas de medios almacenadas de forma relativa al curso (p. ej.
  `media/pic.png`) se muestran con una URL absoluta mientras editas y se vuelven
  a almacenar de forma relativa al guardar, para que las imágenes se rendericen
  tanto en el editor como en el lector.

## Consejos y trampas

- **Guarda con frecuencia.** Eliminar una página o un módulo solo se vuelve
  permanente cuando guardas, pero no hay deshacer dentro del editor para cambios
  de estructura: los cuadros de confirmación son tu red de seguridad.
- **Ctrl/Cmd + S** guarda sin necesidad de usar el mouse.
- Usa bloques **Text** para la mayoría del contenido; recurre a **Markdown** solo
  si prefieres escribir Markdown en bruto. Ten en cuenta que ambos se compilan de
  manera diferente.
- El bloque **Heading** emite un `<h2>`/`<h3>`/`<h4>` semántico para la
  estructura y la accesibilidad; prefiérelo antes que fingir encabezados con
  texto grande o en negrita.
- Pegar desde Google Docs/Word es seguro y recomendable; el limpiador elimina el
  desorden automáticamente. Si un pegado sigue viéndose mal, selecciónalo y usa
  **⌫ Clear formatting**, luego vuelve a aplicar el formato.
- Las asas de redimensionamiento de imágenes y de columnas de tabla **solo
  aparecen dentro de los bloques Text**, no en el bloque Image independiente (que
  usa fichas de alineación en su lugar).
- Solo se conservan los iframes/incrustaciones **`https://`** al guardar; una URL
  de incrustación `http://` será descartada por el saneador.

## Relacionado

- [Configuración del curso](11-course-settings.md)
- [Centro de recursos](12-resource-center.md)
- [Creación con Markdown](14-markdown-authoring.md)
- [Libro de calificaciones](13-gradebook.md)
