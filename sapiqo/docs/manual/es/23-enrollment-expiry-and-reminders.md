# Vencimiento de inscripción y recordatorios

**Público:** administrador
**Dónde:** Por curso — Admin → Cursos (/admin/courses) → la configuración de vencimiento de un curso
(o **⚙ Configuración** del editor visual). Cadencia de recordatorios — Admin →
Configuración (/admin/settings). Barrido a demanda — Admin → Cursos → **Ejecutar barrido de vencimiento**
(/admin/expire).

## Qué es
Cada curso puede tener una **vida útil de inscripción** medida en días. Cuando se establece una vida
útil, el acceso de un estudiante a ese curso termina un número fijo de días después de su
inscripción. Antes de que termine el acceso, Sapiqo envía **recordatorios graduales previos al vencimiento**
(por defecto 30, 7 y 1 día antes), y cuando pasa la ventana **desinscribe suavemente**
al estudiante.

Crucialmente, el vencimiento no es destructivo para el crédito ganado: **las insignias, certificados,
horas CPE y el expediente se conservan permanentemente**, incluso después de que termine el acceso.
Las filas de progreso también se conservan, por lo que volver a inscribirse más tarde restaura el lugar del estudiante.

## Cómo usarla

### Establecer la vida útil de inscripción de un curso
1. Abre Admin → Cursos (/admin/courses), o abre el curso en el editor visual.
2. Establece la vida útil de inscripción en **días** (en el editor este es el campo **Vencimiento**
   bajo ⚙ Configuración).
3. Guarda. Un valor mayor que 0 activa el vencimiento; **0 lo desactiva** (sin vencimiento).

Establecer una vida útil recalcula de inmediato la fecha de vencimiento de las inscripciones existentes aún no
finalizadas en ese curso, según la fecha de inscripción original de cada estudiante. Volver a establecerlo en 0
borra todas las fechas de vencimiento de ese curso.

### Establecer la cadencia de recordatorios
1. Abre Admin → Configuración (/admin/settings).
2. En **Recordatorios de vencimiento de acceso (días antes)**, ingresa una lista separada por comas de
   umbrales de días. El valor predeterminado es `30,7,1` (un mes antes, la semana de, y el día
   anterior).
3. Guarda. La lista se aplica a todos los cursos.

### Ejecutar el barrido
El vencimiento y los recordatorios se aplican mediante un **barrido**, no de forma continua. Ejecútalo:
- **A demanda:** Admin → Cursos → **Ejecutar barrido de vencimiento**. Sapiqo reporta cuántas
  inscripciones terminaron y a cuántos estudiantes se advirtió.
- **Según un programa (recomendado):** ejecuta `bin/expire.php` a diario mediante cron. Ejemplo:
  ```cron
  0 2 * * *  php /path/to/courses/sapiqo/bin/expire.php
  ```

## Opciones y comportamiento
- **Vida útil de inscripción (días)** — por curso. `> 0` activa el vencimiento; `0` lo desactiva.
  Los estudiantes recién inscritos obtienen una fecha de vencimiento de la hora de inscripción + N días.
- **Recalcular al cambiar** — cambiar la vida útil de un curso reescribe `expires_at`
  para sus inscripciones activas (no finalizadas) a partir del `enrolled_at` de cada estudiante.
- **Umbrales de recordatorio** — la configuración `expiry_reminder_days`, por defecto `30,7,1`.
  Ordenados de más lejano primero; cada umbral se rastrea por inscripción para que un estudiante
  reciba **cada recordatorio como máximo una vez**.
- **Entrega** — los recordatorios salen por **correo electrónico** (solo cuando el correo está configurado) y
  como una **notificación en la aplicación** que enlaza al curso.
- **Comportamiento de recuperación** — si un barrido se ejecuta tarde y una inscripción ha cruzado dos
  umbrales desde la última ejecución, Sapiqo envía un único recordatorio del umbral *más
  urgente*, no uno por cada hito omitido.
- **Desinscripción suave al vencer** — una vez pasado `expires_at` (y sin finalizar), la
  inscripción se elimina pero **el progreso, la insignia, el certificado, el CPE y el expediente
  se conservan**. El estudiante también recibe una notificación de que el acceso ha terminado.
- **Los cursos finalizados están exentos** — las inscripciones con estado `completed` nunca se
  advierten ni vencen.

## Cómo funciona
`set_course_enroll_days()` escribe la columna `enroll_days` y recalcula
`expires_at` para las inscripciones activas. Las nuevas inscripciones obtienen su `expires_at` establecido por
`enroll()` en el momento de la creación.

`expire_enrollments()` realiza dos pasadas. Primero selecciona las inscripciones cuyo
`expires_at` cae dentro de la ventana de recordatorio más amplia, calcula los días restantes y
envía el recordatorio más urgente recién cruzado — registrándolo en una máscara de bits por inscripción
(`expiry_warned`) para que cada hito se dispare una vez. Luego selecciona
las inscripciones ya pasado `expires_at` (y no finalizadas) y llama al `unenroll()` suave,
que elimina la fila de inscripción pero deja el progreso y las insignias
intactos. Devuelve conteos de `removed` y `warned`, que el barrido a demanda
muestra en un mensaje emergente y en el registro de auditoría (`enrollments.expire`).

Los correos electrónicos de recordatorio aseguran explícitamente a los estudiantes que cualquier cosa ya ganada — insignia,
certificado, horas CPE — permanece en su expediente permanentemente.

## Consejos y detalles
- **Nada vence sin que se ejecute el barrido.** Las fechas se calculan, pero a un
  estudiante solo se le recuerda/desinscribe cuando se ejecuta `bin/expire.php` (cron) o el
  botón **Ejecutar barrido de vencimiento**. Programa el cron diario.
- **Establece la vida útil en 0** para cursos que nunca deberían vencer (el valor predeterminado cuando
  no se establece).
- **Los recordatorios necesitan el correo configurado** para llegar a los estudiantes por correo electrónico; las notificaciones
  en la aplicación aparecen de todos modos.
- **Cambiar la vida útil recalcula la base de los estudiantes existentes** a partir de su fecha de inscripción
  original — no reinicia el reloj desde "hoy".
- **"Acceso terminado" es reversible.** Volver a inscribir a un estudiante (individualmente, en lote,
  o mediante una suscripción de grupo/organización) restaura su progreso conservado; una insignia vuelta a ganar o
  ya ganada no se duplica.
- Como los recordatorios son únicos por hito, acortar la lista de umbrales
  más tarde no reenviará recordatorios que un estudiante ya recibió.

## Relacionado
- [Usuarios y roles](20-users-and-roles.md)
- [Organizaciones y suscripciones](21-organizations-and-subscriptions.md)
- [Grupos y gestores](22-groups-and-managers.md)
