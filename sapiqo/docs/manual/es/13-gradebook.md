# Libro de calificaciones

**Público:** desarrollador de cursos
**Dónde:** Admin → Gradebook (`/admin/gradebook`); la cuadrícula de puntajes de un solo curso en `/admin/gradebook/<slug>`

## Qué es

El libro de calificaciones tiene dos partes:

- Una **cuadrícula de puntajes personalizada** por curso (estudiantes ×
  evaluaciones) donde creas tus propias evaluaciones —título, categoría, puntos,
  fecha de entrega— e ingresas los puntajes a mano. Sapiqo calcula el porcentaje
  general de cada estudiante y una calificación con letra, y exporta la
  cuadrícula a CSV.
- Una tabla de **resultados de cuestionarios calificados automáticamente** que
  lista el puntaje, el aprobado/reprobado y los intentos de cada estudiante en
  los cuestionarios de verificación de conocimientos integrados en las páginas.

Las dos son complementarias: los cuestionarios los califica automáticamente el
LMS; la cuadrícula de puntajes es para todo lo que calificas tú (tareas orales,
proyectos, participación, etc.).

## Cómo usarlo

**Abrir la cuadrícula de puntajes de un curso:**

1. Ve a **Admin → Gradebook** (`/admin/gradebook`).
2. En **Scores grids**, haz clic en la tarjeta del curso (**Open the scores
   grid →**).

**Crear o editar una evaluación (una columna):**

1. En la tarjeta **Add / edit an assessment**, rellena:
   - **Title** (obligatorio, p. ej. *Speaking Task*)
   - **Category** (opcional, p. ej. *Participation*)
   - **Points** (obligatorio, predeterminado `100`, paso `0.5`)
   - Fecha de **Due (optional)**
2. Haz clic en **Save**. Para editar una evaluación existente, haz clic en el
   **✎** del encabezado de su columna —el formulario se rellena previamente y se
   desplaza a la vista— y luego Save.
3. Elimina una evaluación con el **🗑** de su encabezado (confirma; también quita
   sus puntajes).

**Ingresar puntajes:**

1. Escribe un número en cualquier celda de la tabla **Scores**.
2. Se **guarda automáticamente** cuando sales de la celda: un borde verde
   confirma el guardado, el rojo significa que falló. Deja una celda **en blanco
   para borrar** ese puntaje.
3. El **Overall %** del estudiante y la calificación con letra se actualizan en
   vivo en la última columna.

**Exportar:**

- Haz clic en **Export CSV** en la cuadrícula de ese curso, o en la página
  principal del Gradebook para la tabla de resultados de cuestionarios (con un
  filtro de curso opcional).

## Opciones y comportamiento

- **Las filas de la cuadrícula** son los estudiantes inscritos en el curso
  (excluidos los administradores), ordenados por apellido y luego por nombre;
  cada fila muestra el nombre y el correo electrónico.
- **Las columnas de la cuadrícula** son tus evaluaciones en el orden guardado,
  cada una mostrando su título, `/max points` y categoría.
- **Las celdas de puntaje** son entradas numéricas limitadas a `0 … max_points`
  (paso `0.5`). Guardar envía a `POST /api/gradebook/score`; un valor en blanco
  elimina el puntaje.
- **Overall %** = total de puntos obtenidos ÷ total posible, **contando solo las
  evaluaciones en las que el estudiante tiene un puntaje**. Las evaluaciones sin
  puntaje no bajan el promedio. Si no hay nada puntuado, muestra **—**.
- **La calificación con letra** proviene de la escala de calificaciones,
  predeterminada `A ≥ 90, B ≥ 80, C ≥ 70, D ≥ 60`, de lo contrario **F**. Los
  administradores pueden anularla con la configuración del sitio `grade_scale`
  (formato `A:90,B:80,C:70,D:60`). La escala activa se imprime encima de la
  cuadrícula.
- **Exportación CSV (cuadrícula):** las columnas son Last name, First name,
  Email, una columna por evaluación (encabezada `Title (/max)`), Overall % y
  Grade. Nombre de archivo `gradebook-<slug>.csv`.
- **Estados vacíos:** si nadie está inscrito, o aún no existen evaluaciones, la
  cuadrícula muestra un aviso en lugar de una tabla.
- **Tabla de resultados de cuestionarios calificados automáticamente** (página
  principal del Gradebook): Course, Learner, Quiz, Score (`score/total (%)`),
  Result (Passed / Not yet), Attempts y Updated (UTC). Filtra por curso con el
  menú desplegable; **Export CSV** descarga `sapiqo-gradebook.csv` (hasta 5000
  filas).

## Cómo funciona

- Las evaluaciones y los puntajes se almacenan en las tablas `assessments` y
  `assessment_scores` (consulta `app/gradebook.php`), separadas del contenido del
  curso basado en archivos, de modo que volver a ejecutar una compilación de
  Markdown nunca toca las calificaciones.
- `gb_add_assessment()` / `gb_update_assessment()` gestionan las columnas;
  `gb_set_score()` inserta o actualiza (o, para un valor nulo, elimina) el
  puntaje de un estudiante.
- `gb_overall()` suma los puntos/posibles solo sobre los ítems puntuados;
  `gb_letter()` asigna un porcentaje a través de `gb_grade_scale()`.
- Las ediciones de puntaje son AJAX (`/api/gradebook/score`), protegidas con CSRF
  y restringidas a administradores; la respuesta devuelve el general recalculado
  + la letra, para que la fila se actualice sin recargar.
- La tabla de **resultados de cuestionarios** es una lectura separada sobre la
  tabla `quiz_results` (score, total, passed, attempts) unida a usuarios y
  cursos, poblada automáticamente cuando los estudiantes envían los
  cuestionarios de página.
- Las acciones de agregar/editar/eliminar evaluaciones se registran en el
  registro de auditoría (`gradebook.assessment`,
  `gradebook.assessment_delete`).

## Consejos y trampas

- **La cuadrícula de puntajes y los resultados de cuestionarios son
  independientes.** El aprobado/reprobado de un cuestionario de página vive en la
  tabla de resultados de cuestionarios e impulsa la finalización/las insignias;
  **no** aparece como columna en la cuadrícula de puntajes personalizada, y los
  puntajes de la cuadrícula no afectan la finalización de los cuestionarios.
- **Overall % ignora las evaluaciones sin puntaje**: un estudiante con un solo
  ítem calificado al 100% muestra 100% en general aunque otras columnas estén en
  blanco. Ingresa cada puntaje que quieras contar.
- **Solo aparecen los estudiantes inscritos** como filas. Inscribe primero a las
  personas y luego califica.
- **En blanco borra, no pone cero.** Para contar un ítem faltante como cero,
  escribe `0`.
- El acceso a la cuadrícula es **solo para administradores** (`require_admin`);
  un desarrollador de cursos que edita contenido sin derechos de administrador
  puede no ver la cuadrícula; coordínate con un administrador.
- Las calificaciones con letra siguen la `grade_scale` de **todo el sitio**; no
  hay anulación de escala por curso.

## Relacionado

- [Editor de bloques visual](10-visual-block-editor.md) — cuestionarios de verificación de conocimientos en páginas
- [Creación con Markdown](14-markdown-authoring.md) — bloques `@quiz`
- [Configuración del curso](11-course-settings.md) — horas CPE al completar
