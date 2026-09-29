# Tomar un curso

**Audiencia:** estudiante
**Dónde:** Panel, Catálogo (navegación superior → Catálogo, o /catalog) y el
lector de cursos en /learn/<slug>

## Qué es
Un curso de Sapiqo es un conjunto de módulos, cada uno con lecciones (y temas
adicionales opcionales y cuestionarios de verificación de conocimientos). Avanzas a
través de él en el lector de cursos — un reproductor de pantalla completa con una
barra lateral de módulos/lecciones, una barra de progreso, el contenido de la
lección, videos incrustados y cualquier recurso que el autor haya agregado. Terminar
cada paso rastreable completa el curso y te hace ganar tu insignia.

Tu progreso se guarda en tu cuenta, así que puedes detenerte en un dispositivo y
retomar donde lo dejaste en otro.

## Cómo usarlo
1. Desde el **Catálogo**, encuentra un curso. Elige **Inscribirse y comenzar** (o
   **Continuar** si ya estás inscrito) para abrirlo.
2. Como alternativa, desde tu **Panel**, elige **Comenzar**, **Reanudar** o
   **Revisar** en cualquier curso en el que estés inscrito.
3. El lector se abre en la página de inicio del curso, mostrando los módulos y un
   botón **Comenzar / Reanudar / Revisar**. Selecciona una tarjeta de módulo o una
   lección en la barra lateral izquierda para empezar.
4. Lee la lección. Mira cualquier video incrustado y abre cualquier **Tema de esta
   lección** adicional (cada uno es un elemento expandible).
5. Cuando termines una lección, elige **Marcar lección como completada**. Para los
   temas adicionales, elige **Marcar tema como completado**. Si la lección tiene una
   verificación de conocimientos, realiza el cuestionario y apruébalo.
6. Usa **Anterior** y **Siguiente** en la parte inferior de una lección, o la barra
   lateral, para moverte entre lecciones.
7. Observa cómo la **barra de progreso** en la barra lateral sube hacia el 100 %.
   Cuando llegues al 100 %, se emite tu insignia y aparece un mensaje "¡Curso
   completado!" con un enlace para descargar tu insignia.

## Opciones y comportamiento
- **Inscribirse y comenzar / Continuar** (Catálogo) — te inscribe si es necesario y
  abre el curso. La inscripción también ocurre automáticamente la primera vez que
  abres un curso.
- **Comenzar / Reanudar / Revisar** (Panel) — la etiqueta refleja tu estado: no
  iniciado, en progreso o ya completado. **Reanudar** te lleva de vuelta a la última
  lección que estabas viendo.
- **Barra lateral** — lista los módulos y sus lecciones. Una palomita verde marca los
  elementos completados; cada módulo muestra un conteo de "hechas / total". Una
  insignia ▶ muestra cuántos videos contiene una lección.
- **Marcar lección como completada / Marcar tema como completado** — registra ese
  paso. Seleccionarlo de nuevo lo desmarca. Los temas adicionales cuentan para tu
  progreso general pero no te impiden avanzar.
- **Verificación de conocimientos** — un cuestionario dentro de una lección. Debes
  aprobarlo (70 % de forma predeterminada) para que esa lección cuente como
  totalmente completada. Consulta
  [Cuestionarios y evaluaciones](03-quizzes-and-assessments.md).
- **Anterior / Siguiente** — moverse entre lecciones. En modo secuencial, el botón
  **Siguiente** muestra un candado y está deshabilitado hasta que la lección actual
  esté completada.
- **Requisitos previos** — si un curso requiere otro curso primero, el Catálogo
  muestra una etiqueta "🔒 Requiere <curso>" y un botón **Comenzar requisito previo**,
  y el lector no se abrirá hasta que hayas completado el curso requerido.
- **Cursos no disponibles** — si un curso se retira, muestra una etiqueta **No
  disponible** en tu panel y no puede abrirse, pero cualquier insignia o certificado
  que ya hayas ganado permanece en tu panel y expediente.

### Modo secuencial (orden bloqueado)
Algunos cursos están configurados en modo secuencial. Cuando está activado:
- Cada lección permanece **bloqueada** (mostrada con un ícono de candado en la barra
  lateral) hasta que la lección anterior — y su verificación de conocimientos, si la
  tiene — esté completada.
- El botón **Siguiente** y las tarjetas de lecciones bloqueadas no te dejarán
  adelantarte; aparece un breve aviso "🔒 Bloqueado" si lo intentas.
- Como debes terminar en orden, la insignia y el certificado solo son alcanzables una
  vez que completas todo el curso en secuencia.

Los temas adicionales no condicionan la progresión — puedes avanzar sin abrirlos,
aunque todavía cuentan para tu porcentaje general.

### Videos y recursos
- Los videos se incrustan directamente en el contenido de la lección (o tema). Los
  videos autoalojados admiten búsqueda/desplazamiento, y los subtítulos se muestran
  cuando el autor los proporcionó. Los videos incrustados (por ejemplo, de un
  servicio de alojamiento) se reproducen en su lugar.
- Algunas lecciones son "un conjunto de recursos" — en lugar de texto, presentan
  elementos para abrir uno a la vez en **Temas de esta lección**.

## Cómo funciona
Cada lección, tema adicional y verificación de conocimientos es un "paso" rastreable.
Tu porcentaje de finalización es `pasos completados ÷ pasos totales`, con un máximo
del 100 %. Cuando marcas un paso como completado (o apruebas un cuestionario), el
lector lo registra localmente y, cuando tienes la sesión iniciada, lo refleja al
servidor para que cuente hacia tu insignia y te siga entre dispositivos. Al cargar,
el registro del servidor es la fuente de verdad y alimenta al lector.

El contenido y los medios del curso se sirven desde el mismo origen que Sapiqo y
están restringidos: los visitantes con la sesión cerrada solo pueden ver una vista
previa del temario (títulos de módulos y lecciones, sin contenido de lección ni
medios). Los estudiantes con la sesión iniciada obtienen el curso completo, pero las
claves de respuestas de los cuestionarios se eliminan antes de que los datos del
curso lleguen a tu navegador — la calificación ocurre en el servidor. El modo
secuencial es aplicado por el servidor como una configuración del curso que el lector
respeta.

Alcanzar el 100 % emite tu insignia, marca la inscripción como completada y puede
desencadenar una notificación de felicitación (y correo electrónico, si está
configurado).

## Consejos y detalles
- Se te inscribe automáticamente la primera vez que abres un curso desde el Catálogo
  o desde un enlace de curso.
- Si un curso no se abre y menciona un requisito previo, completa ese curso primero.
- En modo secuencial, completar una lección (y aprobar su cuestionario) desbloquea de
  inmediato la siguiente — sin necesidad de recargar la página.
- Marcar una lección como completada es independiente de aprobar su cuestionario; una
  lección con verificación de conocimientos cuenta como totalmente hecha solo cuando
  ambas están hechas.
- Tu posición de "reanudar" registra la última lección que viste, así que usa
  **Reanudar** desde el panel para volver directamente a ella.

## Relacionado
- [Cuestionarios y evaluaciones](03-quizzes-and-assessments.md)
- [Foros de discusión](04-discussion-forums.md)
- [Insignias, certificados y tu expediente](05-badges-certificates-transcript.md)
- [Cuentas e inicio de sesión](01-accounts-and-signing-in.md)
