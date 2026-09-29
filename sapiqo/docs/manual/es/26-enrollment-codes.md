# Códigos de inscripción

**Público:** administrador
**Dónde:** Admin → Gestión de cuentas → **Códigos de inscripción** (/admin/codes).

## Qué es
Los **códigos de inscripción** te permiten repartir (o importar) códigos cortos que inscriben automáticamente a
los estudiantes en uno o varios cursos cuando los canjean. En lugar de agregar personas a
cursos uno por uno, generas un código, lo compartes, y todos los que lo canjeen quedan
inscritos — ideal para una **suscripción de grupo o cohorte**, una cohorte de conferencia, o un
socio que distribuye acceso a través de su propio sistema.

Cada código puede otorgar un **solo curso o una serie completa**, llevar un **límite de plazas**
(cuántas personas pueden canjearlo) y una fecha de vencimiento opcional, y puede
**desactivarse** en cualquier momento. Puedes ver cada código que has emitido, quién lo canjeó,
y qué cursos afecta.

## Cómo usarla

### Crear un código
1. Abre Admin → Gestión de cuentas → **Códigos de inscripción**.
2. En **Crear un código**, completa:
   - **Etiqueta** — una nota interna (p. ej. "Aldirk ISD — Cohorte de otoño"). Los estudiantes nunca
     la ven; es cómo reconoces el código más tarde.
   - **Código** — déjalo en blanco para generar automáticamente un código inequívoco como
     `ABCD-EF23-GH45`, **o** pega un código de otro sistema (letras, dígitos y
     guiones) si una plataforma externa emite los códigos.
   - **Cursos** — marca uno o varios. Marcar varios convierte el código en un paquete/serie.
   - **Plazas / usos máximos** — cuántas personas pueden canjearlo. Déjalo en blanco (o 0) para
     ilimitado.
   - **Vence** — un último día opcional en que el código funciona.
3. Elige **Crear código**. El nuevo código aparece en **Códigos emitidos** con un
   **Enlace de canje** listo para compartir (`/redeem?code=…`).

### Compartirlo
Reparte ya sea el código en sí (los estudiantes lo escriben en la página **Canjear un código** o al
registrarse) o el **Enlace de canje**, que inicia a los recién llegados en el flujo de canjear-luego-registrarse.
Consulta [Canjear un código de inscripción](07-redeeming-a-code.md) para la experiencia
del estudiante.

### Rastrear y gestionar
- **Buscar** códigos emitidos por código o etiqueta.
- **Canjes** (en cada fila) abre una vista de detalle que lista a cada usuario que canjeó
  el código, cuándo, y los **cursos afectados**; cada usuario enlaza a su cuenta.
- **Desactivar / Reactivar** apaga o vuelve a encender un código sin eliminarlo.
- **Eliminar** quita el código y sus registros. Los estudiantes ya inscritos a través de él
  **conservan su acceso** — eliminar solo detiene futuros canjes.

## Opciones y comportamiento
- **Uno o varios cursos.** Un código inscribe en cada curso vinculado a él, en un
  solo paso. Agrega o quita cursos emitiendo un código nuevo; el conjunto de cursos de un código es fijo al
  crearlo.
- **Plazas.** Con un límite de plazas, cada estudiante *nuevo* consume una plaza; cuando se alcanza el
  límite el código reporta que "alcanzó su límite de uso." Un estudiante que vuelve a canjear un
  código que ya usó **no** consume otra plaza.
- **Vencimiento.** Después de la fecha de vencimiento el código deja de funcionar; las inscripciones existentes
  no se tocan.
- **Estado** se muestra como una etiqueta: **Activo**, **Inactivo** (desactivado), **Vencido**,
  o **Lleno** (plazas agotadas).
- **Generado vs. externo.** Los códigos generados automáticamente usan un alfabeto sin caracteres
  confundibles (sin `0/O/1/I`). Los códigos externos pegados se almacenan en mayúsculas y sin espacios;
  los duplicados se rechazan.
- **Inscripción automática al registrarse/iniciar sesión.** Un recién llegado que parte de un código o enlace de canje
  se inscribe automáticamente en el instante en que se crea su cuenta o inicia sesión.

## Cómo funciona
Un código se almacena en `enroll_codes` con su etiqueta, límite de plazas (`max_uses`), vencimiento,
y bandera de encendido/apagado; sus cursos residen en `enroll_code_courses` (muchos a muchos); y cada
canje se registra una vez por usuario en `enroll_code_redemptions`, que es lo que
impone "una plaza por persona" y alimenta el reporte de canjes. Canjear llama a la
misma inscripción idempotente usada en todo Sapiqo, por lo que el progreso, las insignias y los certificados
funcionan con normalidad. Crear, canjear, alternar y eliminar códigos se registran todos
en el [registro de auditoría](35-audit-log.md).

## Consejos y detalles
- **Usa bien la etiqueta.** Es tu único identificador legible para humanos de un código — nómbralo
  según el cliente, la cohorte o la campaña.
- **Las plazas son para acuerdos de grupo.** Establece el número de plazas en la cantidad de licencias vendidas para
  que un código compartido no pueda sobreinscribir.
- **Eliminar es seguro para los estudiantes.** Quitar un código nunca desinscribe a nadie; usa
  **Desactivar** si simplemente quieres detener nuevos canjes pero conservar el registro.
- **Los códigos no son invitaciones.** Inscriben en cursos pero no crean cuentas ni
  envían correo — combina un código (o enlace de canje) con tu propio anuncio.
- **Prefiere el Enlace de canje para los recién llegados.** Encamina a los usuarios primerizos a través de
  canjear → crear cuenta con el código ya aplicado, que es la ruta más fluida.
- **Para cohortes gestionadas, considera también los grupos/organizaciones.** [Organizaciones y suscripciones](21-organizations-and-subscriptions.md)
  inscriben automáticamente a los miembros que agregas directamente; los códigos de inscripción son la
  contraparte de **autoservicio** donde el estudiante trae el código.

## Relacionado
- [Canjear un código de inscripción](07-redeeming-a-code.md)
- [Organizaciones y suscripciones](21-organizations-and-subscriptions.md)
- [Grupos y gestores](22-groups-and-managers.md)
- [Usuarios y roles](20-users-and-roles.md)
