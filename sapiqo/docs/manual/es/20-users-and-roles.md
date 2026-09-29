# Usuarios y roles

**Público:** administrador
**Dónde:** Admin → Gestión de cuentas (/admin/accounts) y Admin → Gestionar usuarios (/admin/users)

## Qué es
Sapiqo tiene una cuenta por persona, identificada por correo electrónico. Lo que una persona puede hacer lo
decide su **rol** y cualquier **concesión** que le otorgues. Hay cuatro
tipos de acceso privilegiado, descritos en la página de **Gestión de cuentas**
(/admin/accounts) en la sección "Cómo funciona el acceso":

- **Administrador** — control total de todo: usuarios, cursos, configuración e
  integraciones. En el código esto es `role = 'admin'`; cada ruta solo para administradores llama
  a `require_admin()`.
- **Desarrollador de cursos** — puede crear, editar e importar únicamente *contenido de cursos*.
  No puede gestionar usuarios, configuración ni integraciones. Esta es la
  capacidad `can_edit_content` (`can_edit_content()` / `require_content_access()`).
  Los administradores siempre la tienen de forma implícita.
- **Gestor de grupo** — un subadministrador que gestiona a los miembros de determinados grupo(s):
  los inscribe en cursos o edita sus perfiles, según los
  permisos que le concedas. Un gestor de grupo *no* puede tocar el contenido de los cursos a menos que también
  le des acceso de desarrollador de cursos. (Los gestores trabajan desde el área **Gestionar**,
  documentada en "El área Gestionar para subadministradores".)
- **Estudiante** — el rol predeterminado (`role = 'learner'`): toma cursos, gana
  insignias, ve su propio panel y expediente. Toda cuenta que no sea de
  administrador es estudiante, incluso si también tiene una concesión de desarrollador de cursos o
  de gestor de grupo.

La página de **Gestión de cuentas** es el único lugar para ver y asignar cada
cuenta privilegiada. La página de **Gestionar usuarios** (/admin/users) es donde buscas
la lista completa y editas cuentas individuales.

## Cómo usarla

### Revisar y conceder acceso privilegiado (/admin/accounts)
La página tiene tres tarjetas:

1. **Administradores** — lista cada cuenta con el rol de administrador. Usa **Editar** para
   abrir el perfil de un usuario. Promueves o degradas administradores desde el campo Rol del perfil del usuario
   (ver más abajo), no desde esta lista.
2. **Desarrolladores de cursos** — escribe un correo electrónico en el campo y elige **Conceder
   acceso de edición de contenido**. La persona podrá entonces crear cursos sin derechos completos de administrador.
   Cada desarrollador listado tiene un botón **Revocar**. (Intentar conceder esto a
   un administrador se rechaza con "Los administradores ya pueden editar contenido.")
3. **Gestores de grupo** — elige un grupo en **Grupo que gestiona**, ingresa el
   correo electrónico del gestor, marca los permisos y elige **Asignar gestor de grupo**.
   Puedes marcar **Puede inscribir miembros en cursos**, **Puede agregar / editar perfiles
   de miembros** y, opcionalmente, **Permitir también editar el contenido de cursos** (lo que concede
   la capacidad de desarrollador de cursos al mismo tiempo). Asignar un gestor también
   lo agrega como miembro de ese grupo.

### Buscar y gestionar usuarios individuales (/admin/users)
1. Escribe un nombre, correo electrónico, campus u organización en el cuadro de búsqueda y elige
   **Buscar**. La lista se pagina a 50 por página.
2. Elige el nombre de una persona o **Editar** para abrir el editor completo de usuarios.
3. En el editor puedes cambiar el perfil (Nombre, Apellido, Correo electrónico, Teléfono,
   Tipo de usuario, Campus, Organización / Distrito), establecer el **Rol** (Estudiante o
   Administrador), restablecer la contraseña, sincronizar **Inscripciones** y establecer la pertenencia a **Grupos**.
   Elige **Guardar cambios**.

### Cambiar un rol
- **Un solo usuario:** abre el usuario (/admin/users/{id}), establece el **Rol** en Estudiante o
  Administrador y **Guarda los cambios**.
- **Desde la lista, en lote:** selecciona filas con las casillas, elige **Convertir en
  administrador** o **Convertir en estudiante** en el menú de acción en lote y elige
  **Aplicar**.

### Acciones en lote
Selecciona usuarios con las casillas de las filas (o la casilla del encabezado para seleccionar toda la
página), elige una acción y elige **Aplicar**:
- **Inscribir en curso** / **Quitar del curso** — elige primero un curso en el menú "— curso —".
- **Agregar a grupo** / **Quitar del grupo** — elige un grupo en el menú "— grupo —".
- **Convertir en administrador** / **Convertir en estudiante** — cambia roles.
- **Eliminar usuarios** — elimina permanentemente las cuentas seleccionadas.

### Restablecer la contraseña de un usuario
Abre el editor de usuarios y ya sea:
- Escribe una nueva contraseña en **Restablecer contraseña (opcional)** (al menos 8 caracteres)
  y **Guarda los cambios**, o
- Usa la tarjeta **Enlace para restablecer contraseña** en la parte inferior: elige **Generar enlace
  de restablecimiento**. Si el correo electrónico está configurado, el enlace se envía por correo al usuario. Si no,
  el enlace de un solo uso se te muestra en pantalla para que puedas compartirlo de forma segura. El enlace
  es válido por una hora y puede usarse una vez.

## Opciones y comportamiento
- **Buscar** coincide con correo electrónico, nombre, apellido, organización y campus.
- **Exportar CSV** en la página de Usuarios exporta la lista con todos los campos del perfil; si
  has buscado, el botón dice **Exportar resultados** y exporta exactamente las
  filas filtradas.
- **Importar CSV** enlaza al importador masivo de usuarios para crear cuentas en masa.
- **Ver como** aparece junto a los usuarios que no son administradores (ver "Ver como (suplantación de identidad)").
- **Inscripciones** en el editor: marca para inscribir, desmarca para quitar. Quitar una
  inscripción conserva el progreso del estudiante y cualquier insignia a menos que marques **También
  eliminar el progreso y cualquier insignia al quitar una inscripción**.
- **Grupos** en el editor: marca los grupos a los que pertenece este usuario; el conjunto se guarda
  exactamente como se marcó. Agregar a un grupo inscribe automáticamente al usuario en las suscripciones de cursos de ese grupo (y
  de su organización).
- **Salvaguardas contra el autobloqueo:** no puedes quitar tu propio rol de administrador
  (se fuerza de vuelta a administrador con un aviso), la acción en lote "Convertir en estudiante"
  omite tu propia cuenta y "Eliminar usuarios" en lote nunca elimina tu propia cuenta.
- **La lista de desarrolladores de cursos** muestra solo usuarios que no son administradores y que tienen la capacidad;
  los administradores se omiten porque ya pueden editar contenido.

## Cómo funciona
Los roles residen en la columna `users.role`. La capacidad de desarrollador de cursos es la
bandera `users.can_edit_content`, alternada por `grant_course_dev()` /
`revoke_course_dev()`. Las concesiones de gestor de grupo son filas en `group_managers` con
las banderas `perm_enroll` y `perm_members`. Las contraseñas se almacenan solo como hashes
unidireccionales (`password_hash`), nunca en texto plano, y se vuelven a hashear automáticamente si la
configuración de hashing cambia. Los enlaces de restablecimiento generados por el administrador almacenan solo un hash SHA-256 de
un token de un solo uso y una hora de validez, por lo que una copia de la base de datos no puede usarse para tomar
control de cuentas.

Las acciones privilegiadas se escriben en el registro de auditoría — por ejemplo `user.role`,
`user.edit`, `users.bulk`, `account.course_dev`, `account.manager.assign` y
`password.admin_reset_link` — para que puedas ver quién cambió qué y cuándo.

## Consejos y detalles
- **Concede el mínimo privilegio.** Si alguien solo crea cursos, hazlo desarrollador de cursos,
  no administrador. Si alguien solo administra la lista de un campus, hazlo gestor de grupo.
- **"No se encontró ningún usuario con ese correo electrónico."** Los formularios de concesión de desarrollador de cursos y de gestor
  solo funcionan en cuentas que ya existen. Crea primero la cuenta
  (importación, registro o la creación sobre la marcha en el área Gestionar) y luego concede.
- Un **gestor de grupo siempre es miembro** del grupo que gestiona — asignar la
  concesión agrega la pertenencia automáticamente.
- Eliminar un usuario es permanente y lo quita de todas partes; considera quitarlo
  de grupos/organizaciones (lo que conserva su expediente) en su lugar.
- El **registro de auditoría** (/admin/audit) es tu constancia de los cambios de rol y contraseña.

## Relacionado
- [Organizaciones y suscripciones](21-organizations-and-subscriptions.md)
- [Grupos y gestores](22-groups-and-managers.md)
- [Vencimiento de inscripción y recordatorios](23-enrollment-expiry-and-reminders.md)
- [Ver como (suplantación de identidad)](24-impersonation.md)
- [El área Gestionar para subadministradores](25-manage-area-for-sub-admins.md)
