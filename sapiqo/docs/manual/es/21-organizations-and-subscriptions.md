# Organizaciones y suscripciones

**Público:** administrador   (un gestor de organización también usa el área Gestionar — ver Relacionado)
**Dónde:** Admin → Organizaciones (/admin/orgs) y la página de cada organización (/admin/orgs/{id})

## Qué es
Una **organización** es una entidad de nivel superior — un cliente o distrito (por ejemplo
*Aldirk ISD*) — que posee miembros, grupos y suscripciones de cursos. El acceso a los cursos
fluye *hacia abajo* desde la organización:

- Una **suscripción para toda la organización** inscribe a todos los miembros de la organización.
- Agregar un miembro (directamente, o al agregarlo a cualquiera de los grupos de la organización)
  lo **inscribe automáticamente** en todo aquello a lo que estén suscritos la organización y esos grupos.
- **Cancelar la suscripción no es destructivo:** los estudiantes existentes conservan el curso y
  su progreso; solo se detiene la futura inscripción automática.

Las organizaciones son la herramienta adecuada cuando todo un distrito debe recibir los mismos
cursos. Para cursos que solo algunas personas necesitan, usa en su lugar un **grupo** dentro de la organización
(ver "Grupos y gestores").

## Cómo usarla

### Crear una organización
1. Ve a /admin/orgs.
2. En **Crear una organización**, ingresa un **nombre** (p. ej. `Aldirk ISD`) y una
   **descripción** opcional, luego elige **Crear organización**.
3. Aterrizas en la página de la organización (/admin/orgs/{id}). Crear una organización que ya
   existe por nombre simplemente vuelve a abrir la existente.

### Agregar miembros
En la página de la organización, en **Miembros**:
1. Ingresa correos electrónicos en **Agregar miembros por correo electrónico** (sepáralos con comas, espacios o nuevas
   líneas).
2. Elige **Agregar a la organización**.

Las cuentas deben existir ya; los correos electrónicos desconocidos se reportan como "No encontrado". Para
crear muchas cuentas nuevas, usa primero la página de **Importaciones** en lote. Cada miembro agregado
se inscribe automáticamente en los cursos para toda la organización.

### Suscribir toda la organización a cursos
En **Suscripciones de cursos para toda la organización**:
1. Marca uno o más cursos en **Agregar curso(s) para toda la organización** (la etiqueta indica que
   "inscribe a los N miembro(s) ahora").
2. Elige **Suscribir e inscribir**. Cada miembro actual se inscribe de inmediato,
   y cualquiera agregado después se inscribe automáticamente.

Para suscribir solo a una parte de la organización, crea o vincula un **grupo** y suscribe el
grupo en su lugar.

### Cancelar la suscripción (no destructivo)
En la tabla de suscripciones, elige **Quitar** junto a un curso. La confirmación
lo explica: los estudiantes actuales conservan el curso y su progreso; solo los nuevos miembros
dejan de inscribirse automáticamente.

### Agregar grupos a la organización
En **Grupos en esta organización** puedes:
- **Crear un grupo aquí** — crea un nuevo grupo ya vinculado a esta organización, o
- **Agregar un grupo existente** — elige un grupo independiente del menú desplegable y elige
  **Agregar**. Sus miembros actuales heredan la pertenencia a la organización y el acceso a los cursos para toda la organización.

### Asignar gestores de organización
En **Gestores de organización**:
1. Ingresa el correo electrónico del gestor en **Asignar un gestor por correo electrónico**.
2. Marca los permisos: **Puede inscribir miembros**, **Puede agregar/editar miembros** y/o
   **Puede gestionar suscripciones de cursos**.
3. Elige **Asignar gestor**.

El gestor administra toda la organización — todos sus grupos — desde su área **Gestionar**,
limitado a los permisos que concedas. También se agrega como miembro de la organización.
Cada fila de gestor muestra ✅/— para los tres permisos y un botón **Quitar**.

## Opciones y comportamiento
- **Inscripción automática al unirse** — `org_add_member()` inscribe al nuevo miembro en cada
  curso para toda la organización; `org_subscribe()` inscribe a todos los miembros actuales. La inscripción es
  idempotente, por lo que volver a agregar o volver a suscribir nunca crea duplicados.
- **Los grupos dentro de una organización heredan los cursos para toda la organización** — un miembro agregado a cualquier grupo
  bajo la organización también se convierte en miembro de la organización y obtiene las suscripciones para toda la organización.
- **Cancelación de suscripción no destructiva** — `org_unsubscribe()` solo elimina la fila de
  suscripción; las inscripciones y el progreso permanecen.
- **Quitar un miembro** lo saca de la organización y de los grupos de la organización, pero
  conserva sus inscripciones y progreso ("conservar acceso").
- **Permisos de gestor por concesión** — `enroll` y `members` están ACTIVADOS por defecto;
  `courses` (gestionar suscripciones) está DESACTIVADO por defecto y debe marcarse explícitamente.
- **Los gestores gestionan implícitamente cada grupo de la organización** — un gestor de organización puede abrir
  y gestionar cualquier grupo bajo esa organización desde el área Gestionar.
- **Exportar miembros CSV** — el botón **⬇ Exportar miembros CSV** descarga el nombre,
  correo electrónico, inscripciones, finalizaciones e insignias de todos los miembros.
- **Estadísticas** en la parte superior muestran miembros, grupos, cursos para toda la organización, inscripciones,
  tasa de finalización e insignias.
- **Renombrar** y **Eliminar** están en los formularios de la página de la organización. **Eliminar no es
  destructivo para las personas ni el contenido:** los grupos de la organización se vuelven independientes,
  y cada cuenta, inscripción e insignia se conserva — solo se eliminan el agrupamiento de la organización, su
  lista de miembros, suscripciones y concesiones de gestor.

## Cómo funciona
Las organizaciones son filas en `organizations`. La pertenencia es `org_members`, los enlaces de grupos
son la columna `org_id` en `user_groups`, las suscripciones son `org_courses`,
y las concesiones de gestor son `org_managers` (`perm_enroll`, `perm_members`,
`perm_courses`). Agregar un miembro o suscribir un curso llama a la función compartida
`enroll()`, que respeta la vida útil de inscripción de cada curso (ver
"Vencimiento de inscripción y recordatorios"). Como `enroll()` es idempotente, los
ganchos de inscripción automática son seguros de ejecutar repetidamente. Eliminar una organización desvincula sus grupos
(`org_id = NULL`) y elimina solo las filas del ámbito de la organización. Las acciones se auditan
(`org.create`, `org.subscribe`, `org.manager.assign`, `org.delete`, etc.).

## Consejos y detalles
- **Cancelar la suscripción no quita a los estudiantes.** Si realmente necesitas retirar el acceso,
  desinscribe a las personas individualmente (o mediante acciones en lote) y, opcionalmente, purga — quitar una
  suscripción por sí sola deja a todos inscritos.
- **Agrega miembros antes de suscribir, o después — cualquier orden funciona.** Suscribir
  inscribe a los miembros actuales; agregar un miembro lo inscribe en las suscripciones actuales.
- **Los correos electrónicos desconocidos no se crean aquí.** Usa Importaciones para crear cuentas en lote,
  luego agrégalos. (Un *gestor* de organización que trabaja en el área Gestionar *sí puede* crear
  cuentas sobre la marcha — ver "El área Gestionar para subadministradores".)
- **Concede el permiso de "cursos" con moderación.** Permite a un gestor cambiar en qué está inscrita
  toda la organización (y sus miembros).
- Prefiere los **grupos** para dirigir cursos dentro de un distrito grande, para no
  inscribir a todos en todo.

## Relacionado
- [Grupos y gestores](22-groups-and-managers.md)
- [Usuarios y roles](20-users-and-roles.md)
- [Vencimiento de inscripción y recordatorios](23-enrollment-expiry-and-reminders.md)
- [El área Gestionar para subadministradores](25-manage-area-for-sub-admins.md)
