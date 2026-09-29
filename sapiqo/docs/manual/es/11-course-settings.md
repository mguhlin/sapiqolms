# Configuración del curso

**Público:** desarrollador de cursos
**Dónde:** Admin → Courses (`/admin/courses`) → ✏ Edit → **⚙ Settings** (barra superior), o el menú ⋯ → **⚙ Course settings** (`/admin/editor/<slug>#settings`)

## Qué es

La configuración del curso son las opciones por curso que controlan la
inscripción, el control de finalización y la discusión, mantenidas por separado
del contenido de las páginas que construyes con bloques. Se abren en un cuadro de
diálogo modal dentro del editor visual.

El panel de configuración cubre:

- **Tagline** — una breve descripción de una línea que se muestra con el curso.
- **CPE hours** — crédito de desarrollo profesional otorgado al completar.
- **Access expires after (days)** — la vigencia de una inscripción.
- **Prerequisite course** — otro curso que un estudiante debe terminar primero.
- **Sequential mode** — obligar a que las lecciones se hagan en orden.
- **Discussion forum** — habilitar foros, opcionalmente con un control de
  publicar primero.

## Cómo usarlo

1. Abre el curso en el editor visual (**Admin → Courses → ✏ Edit**).
2. Haz clic en **⚙ Settings** en la barra superior. (Abrir el editor con la URL
   terminada en `#settings`, o el enlace **⚙ Course settings** del menú ⋯, lo
   abre automáticamente.)
3. Rellena los campos que necesites:
   - **Tagline** — una descripción de una línea.
   - **CPE hours** — un número (admite pasos de cuarto de hora, p. ej. `1.5`).
   - **Access expires after (days)** — `0` para que no haya vencimiento.
   - **Prerequisite course** — elige del menú desplegable, o **— none —**.
   - **Sequential mode** — Default, On u Off.
   - **Discussion forum** — On, On + post-first u Off.
4. Haz clic en **Save settings**. Al tener éxito verás **Saved ✓** y el diálogo
   se cierra; el botón **✕** lo cierra sin guardar.

## Opciones y comportamiento

- **Tagline**
  - Texto libre, una línea. Se almacena en `course.json` (no en la base de
    datos).
- **CPE hours**
  - Campo numérico, `min="0"`, `step="0.25"`, predeterminado `0`.
  - Se escribe en `courses.cpe_hours`; se otorga y se registra en la insignia del
    estudiante cuando completa el curso. Todo lo ya obtenido permanece en el
    expediente de forma permanente.
- **Access expires after (days)**
  - Campo numérico, `min="0"`; `0` significa **sin vencimiento** (según la
    información sobre herramientas del campo).
  - Respaldado por `set_course_enroll_days()`. Establecer un valor positivo
    **recalcula `expires_at` para las inscripciones activas existentes (no
    completadas)** a partir de la fecha de inscripción original de cada
    estudiante. Volver a establecerlo en `0` borra todas las fechas de
    vencimiento del curso.
  - Cuando el acceso caduca, se da de baja al estudiante de forma suave (se
    conservan el progreso, las insignias, el certificado y las horas CPE); se
    envían correos electrónicos de recordatorio previos al vencimiento y avisos
    en la aplicación en los hitos configurados.
- **Prerequisite course**
  - Menú desplegable de todos los demás cursos; **— none —** lo borra.
  - El servidor evita que un curso sea su propio requisito previo y que exista un
    requisito previo circular simple (A→B, B→A). Una elección bloqueada se
    restablece silenciosamente a ninguno.
  - Un estudiante no puede entrar a un curso hasta que haya **completado** su
    requisito previo.
- **Sequential mode**
  - Las opciones corresponden a: **Default** = seguir el valor predeterminado
    global del sitio, **On** = siempre secuencial, **Off** = navegación libre.
  - Respaldado por `set_course_sequential()`. Cuando el modo secuencial está en
    vigor, el lector **bloquea cada lección hasta que la anterior (y su
    cuestionario) esté completa**, de modo que la insignia/el certificado solo se
    alcanza después de terminar en orden.
  - "Default" se remite a la configuración del sitio `sequential_default`
    (activada a menos que un administrador la haya cambiado).
- **Discussion forum**
  - **On** — los foros están habilitados.
  - **On + post-first** — un control de "publica antes de ver": un estudiante
    debe contribuir antes de poder leer las publicaciones de los demás.
  - **Off** — foros deshabilitados.
  - Respaldado por las columnas `forum_enabled` y `forum_gated`
    (`forum_enabled=0` para Off; `forum_gated=1` para la variante de publicar
    primero).
  - Los foros en sí se agregan por módulo en el esquema del editor mediante el
    botón **＋ Forum**; esta configuración activa o desactiva la función en todo
    el curso.

## Cómo funciona

- El modal se rellena previamente con el objeto `settings` devuelto por
  `GET /api/editor/<slug>`, que lee los valores en vivo de la fila `courses`
  (`cpe_hours`, `prereq_id`, `enroll_days`, `sequential`, `forum_enabled`,
  `forum_gated`) más el tagline de `course.json`.
- **Save settings** envía un cuerpo codificado como formulario a
  `POST /api/editor/<slug>/settings`. La ruta:
  - actualiza `cpe_hours` y `prereq_id` (con las protecciones de propio/ciclo),
  - llama a `set_course_enroll_days()` (que recalcula las inscripciones),
  - llama a `set_course_sequential()` (`inherit` → `NULL`, de lo contrario
    on/off),
  - actualiza `forum_enabled` / `forum_gated`,
  - vuelve a escribir el tagline en `course.json`,
  - registra una entrada de auditoría `course.settings`.
- Los administradores también pueden cambiar varias de estas opciones fuera del
  editor mediante rutas dedicadas de gestión de cursos (p. ej.
  `/admin/courses/{slug}/cpe`, `/prereq`, `/expiry`, `/sequential`, `/forum`); el
  panel de configuración del editor es el lugar único y amigable para el
  desarrollador de cursos donde hacerlo todo a la vez.

## Consejos y trampas

- **Las horas CPE y el vencimiento viven en la base de datos**, mientras que el
  **tagline vive en `course.json`.** Editar la fuente en Markdown y reconstruir
  puede sobrescribir el tagline, pero no tocará la configuración respaldada por
  la base de datos.
- Cambiar **Access expires after** reescribe los plazos para todos los que ya
  están inscritos; hazlo de forma deliberada, especialmente a mitad de una
  cohorte.
- **Sequential = Default** no es lo mismo que **Off.** "Default" sigue la
  política del sitio (que viene activada); elige **Off** de forma explícita si
  quieres navegación libre sin importar la política del sitio.
- Los requisitos previos se aplican al **completar**, no al inscribirse ni al
  progresar: un estudiante que va por la mitad del requisito previo todavía no
  puede empezar este curso.
- El control de foro **post-first** solo importa una vez que los foros están
  **On**; no tiene efecto cuando el foro está Off.

## Relacionado

- [Editor de bloques visual](10-visual-block-editor.md) — agregar foros con ＋ Forum
- [Creación con Markdown](14-markdown-authoring.md)
- [Libro de calificaciones](13-gradebook.md)
