# Grupos y gestores

**Público:** administrador   (un gestor de grupo también usa el área Gestionar — ver Relacionado)
**Dónde:** Admin → Grupos (/admin/groups) y la página de cada grupo (/admin/groups/{id})

## Qué es
Un **grupo** organiza a los estudiantes — normalmente por campus, cohorte o distrito — para que
puedas rastrear la finalización en conjunto y suscribirlos a cursos como un conjunto. Un grupo puede
existir por sí solo o pertenecer a una **organización** (ver "Organizaciones y
suscripciones").

Como las organizaciones, los grupos inscriben automáticamente: suscribir un grupo a un curso inscribe
a cada miembro actual, y cualquiera agregado después se inscribe automáticamente.
Cancelar la suscripción no es destructivo.

Un **gestor de grupo** es un subadministrador con alcance a un solo grupo. Agrega, edita, inscribe
y quita a los miembros de ese grupo desde su área **Gestionar**, limitado a los
permisos que concedas.

## Cómo usarla

### Crear un grupo
En /admin/groups, en **Crear un grupo**, ingresa un **nombre** (p. ej. `Austin ISD`)
y una **descripción** opcional, luego elige **Crear grupo**. Crear un grupo
cuyo nombre ya existe vuelve a abrir el existente.

Dos atajos en lote construyen muchos grupos a partir de tus datos de usuario existentes:
- **Crear grupos a partir de organizaciones (distritos)** — un grupo por cada valor distinto de
  Organización en los perfiles de usuario, con todos los usuarios coincidentes agregados.
- **Crear grupos a partir de campus** — un grupo por cada Campus distinto, llamado
  "Organización · Campus", con todos los usuarios coincidentes agregados.

Ambos piden confirmación antes de ejecutarse.

### Agregar y quitar miembros
En la página del grupo (/admin/groups/{id}):
- **Agregar miembros:** ingresa correos electrónicos en el campo **Correos electrónicos** (comas, espacios o nuevas
  líneas) y elige **Agregar al grupo**. Los usuarios deben existir ya; los correos electrónicos desconocidos se
  reportan. Los nuevos miembros se inscriben automáticamente en las suscripciones del grupo.
- **Quitar:** cada fila de miembro en la tabla **Miembros** tiene un botón **Quitar**
  (su cuenta, inscripciones y progreso se conservan).

### Suscribir el grupo a cursos
En **Suscripciones de cursos**:
1. Marca cursos en **Agregar suscripciones de cursos** ("inscribe a todos los miembros ahora").
2. Elige **Suscribir e inscribir**.
3. Para eliminar una suscripción, elige **Quitar** en la tabla de suscripciones. Los estudiantes
   actuales conservan el curso y su progreso; solo los nuevos miembros dejan de inscribirse
   automáticamente.

### Vincular un grupo a una organización
En **Suscripciones de cursos**, usa el menú desplegable **Organización**: elige una organización (o
"— ninguna (grupo independiente) —") y elige **Guardar**. Cuando vinculas un grupo a una
organización, sus miembros actuales heredan la pertenencia a la organización y los cursos para toda la organización.

### Asignar un gestor de grupo
En **Gestores (subadministradores)**:
1. Ingresa el correo electrónico del gestor y elige **Asignar gestor**.
2. La persona se agrega como gestor *y* como miembro del grupo.
3. Quita un gestor con **Quitar gestor** (esto conserva su cuenta y su pertenencia
   al grupo).

Los gestores asignados aquí obtienen los permisos predeterminados (puede inscribir y puede editar
miembros; no puede gestionar suscripciones). Para establecer permisos con precisión al momento de la asignación
— incluida la opción de conceder también la edición de contenido de cursos — usa **Gestión de
cuentas** (/admin/accounts) en su lugar, y para ajustar los interruptores Inscribir/Miembros de un gestor
existente, edítalos allí.

## Opciones y comportamiento
- **Inscripción automática al unirse** — agregar un miembro ejecuta las suscripciones del grupo (y,
  si el grupo está en una organización, las suscripciones para toda la organización) mediante el `enroll()`
  idempotente, por lo que no hay duplicados.
- **Cancelación de suscripción no destructiva** — quitar una suscripción conserva las inscripciones
  y el progreso existentes.
- **Eliminar un grupo** quita solo el grupo y sus pertenencias; las cuentas de usuario,
  inscripciones y progreso no se ven afectados (la **Zona de peligro** lo confirma).
- **Permisos de gestor** — una concesión de gestor de grupo lleva `enroll` y `members`
  (ambos ACTIVADOS por defecto) y `courses` (gestionar suscripciones, DESACTIVADO por defecto). La
  página de Gestión de cuentas expone Inscribir y Miembros; el permiso de "cursos" se
  establece a nivel de datos de la organización/grupo y está desactivado por defecto.
- **Los gestores de organización gestionan todos los grupos de su organización** — alguien que gestiona la
  organización principal puede gestionar cada grupo bajo ella, incluso sin una concesión directa.
- **Filtro de finalización y estadísticas** — la página del grupo muestra miembros, inscripciones,
  finalizaciones, tasa de finalización, pasos completados e insignias; un menú desplegable **Finalización para:**
  limita las estadísticas a un curso.
- **Exportar CSV** — **⬇ Exportar CSV** descarga los datos de finalización por miembro.

## Cómo funciona
Los grupos son filas en `user_groups`; la pertenencia es `user_group_members`;
las suscripciones son `group_courses`; las concesiones de gestor son `group_managers`
(`perm_enroll`, `perm_members`, `perm_courses`). Una columna `org_id` vincula un grupo
a su organización. Agregar un miembro dispara `on_group_member_added()`, que
lo inscribe en los cursos del grupo y — si el grupo está en una organización — lo agrega como
miembro de la organización para que los cursos para toda la organización también le lleguen. `manager_perm()` recurre
a la gestión a nivel de organización: si no eres gestor directo del grupo pero gestionas la
organización del grupo, heredas el permiso de organización correspondiente. Las acciones se auditan
(`group.create`, `group.subscribe`, `group.add_manager`, `group.delete`, etc.).

## Consejos y detalles
- **Independiente vs. vinculado a organización:** un grupo funciona bien por sí solo. Vincúlalo a una organización
  solo cuando quieras que sus miembros también reciban cursos para toda la organización.
- **"A partir de organizaciones/campus" lee campos del perfil de usuario.** Agrupa por el
  texto de Organización y Campus en las cuentas, por lo que esos campos deben estar poblados
  (p. ej. mediante importación CSV) para que los atajos sean útiles.
- **Cancelar la suscripción mantiene inscritos a los estudiantes.** Para retirar el acceso, desinscribe a las personas
  individualmente o en lote.
- **Establece los permisos de gestor en la página de Gestión de cuentas** para un control
  detallado; el "Asignar gestor" rápido de la página del grupo usa los valores predeterminados.
- Eliminar un grupo es seguro para los datos del estudiante — solo quita el agrupamiento.

## Relacionado
- [Organizaciones y suscripciones](21-organizations-and-subscriptions.md)
- [Usuarios y roles](20-users-and-roles.md)
- [El área Gestionar para subadministradores](25-manage-area-for-sub-admins.md)
- [Vencimiento de inscripción y recordatorios](23-enrollment-expiry-and-reminders.md)
