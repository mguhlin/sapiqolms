# Registro de auditoría

**Audiencia:** administrador
**Dónde:** Registro de auditoría — `/admin/audit`

## Qué es
El **Registro de auditoría** es un registro de solo anexado de las acciones privilegiadas realizadas en Sapiqo,
mantenido para la rendición de cuentas (por ejemplo, cumplimiento de FERPA / privacidad de datos estatal). Cada
entrada registra **cuándo** (UTC), **quién** (correo electrónico del actor), **qué** (acción), el
**objetivo**, una cadena de **detalle** y la **dirección IP** del solicitante. La auditoría es
de mejor esfuerzo y nunca bloquea la acción que está registrando.

## Cómo usarlo
1. Ve al **Registro de auditoría** (`/admin/audit`).
2. La tabla enumera las entradas más recientes, primero las más nuevas.
3. **Busca** con el cuadro (coincide con correo electrónico del actor, objetivo o detalle) o elige una
   acción específica del menú desplegable **Todas las acciones**, luego elige **Filtrar**.
4. Elige **Exportar CSV** para descargar las entradas filtradas actualmente.

## Opciones y comportamiento

**Columnas.** Cuándo (UTC), Actor, Acción, Objetivo, Detalle, IP. La Acción se muestra como una
etiqueta; un actor u objetivo faltante se representa como un guion largo.

**Filtros.**
- **Búsqueda (`q`)** — coincidencia de subcadena en el correo electrónico del actor, el objetivo y el detalle.
- **Acción** — el menú desplegable se completa a partir de las acciones distintas realmente
  presentes en el registro, así que solo ofrece acciones que hayan ocurrido.

**Tope de visualización.** La pantalla muestra hasta las **500 entradas coincidentes más recientes**
(indicado al pie de la página).

**Exportación CSV.** **Exportar CSV** descarga `sapiqo-audit.csv` y **conserva tus
filtros actuales de búsqueda y acción**. Columnas: Cuándo (UTC), Actor, Acción, Tipo de
objetivo, Objetivo, Detalle, IP.

**Qué se registra.** Las acciones se registran en toda la aplicación. Ejemplos representativos:

- **Inicio de sesión y sesiones:** `login.success`, `login.fail`, `login.blocked`,
  `logout`.
- **Contraseñas:** `password.reset_requested`, `password.reset_done`,
  `password.admin_reset_link`.
- **Cuentas y roles:** `user.register`, `user.edit`, `user.role`,
  `account.course_dev`, y concesiones a responsables
  (`account.manager.assign` / `.perms` / `.remove`).
- **Inscripción:** `user.enroll`, `user.disenroll`, `user.progress_set`,
  `enrollments.expire`, `course.expiry`.
- **Suplantación:** `user.impersonate.start`, `user.impersonate.stop`.
- **Cursos:** `course.editor_new`, `course.editor_save`, `course.publish`,
  `course.rescan`, `course.settings`, `course.cpe`, `course.prereq`,
  `course.sequential`, `course.forum`, y carga/eliminación de recursos.
- **Importaciones:** `course.import`, `course.import_cc`, `course.import_scorm`,
  `course.import_learndash`, `course.import_split`, `users.import`,
  `users.import_oneroster`, `users.bulk`.
- **Exportaciones y copias de seguridad:** `course.export`, `course.export_cc`,
  `course.export_json`, `course.export_split`, `users.export`, `backup.download`.
- **Organizaciones y grupos:** `org.create`, `org.delete`, `org.subscribe`,
  `org.manager.assign`; `group.create`, `group.delete`, `group.subscribe`,
  `group.add_manager`.
- **Configuración e integraciones:** `settings.update`, `settings.theme_preset`,
  `api_key.create`, `api_key.revoke`, `lti.platform.create`, `lti.platform.delete`,
  `lti.launch`.
- **Actualizaciones de software:** `app.update`, `app.update.build`, `app.update.rollback`.

(El conjunto exacto crece con el software; el menú desplegable siempre refleja lo que ha
ocurrido realmente en tu instalación.)

## Cómo funciona
Cada acción auditada llama a un único asistente de registro que inserta una fila en la tabla
`audit_log` con el usuario que actúa (resuelto desde la sesión), el nombre de la
acción, un tipo/id de objetivo opcional, una cadena de detalle (los arreglos se almacenan como JSON), la
IP del cliente y una marca de tiempo UTC. El asistente está envuelto de modo que una falla de registro se
escribe en el registro de errores de PHP en lugar de interrumpir la solicitud del usuario. La
pantalla y el CSV leen ambos de esta tabla mediante la misma consulta filtrada, primero las más nuevas.

## Consejos y trucos
- **El registro es de solo anexado desde la interfaz** — no hay botón de editar ni de eliminar.
  Trátalo como tu registro a prueba de manipulaciones.
- **Las horas son UTC.** Convierte a la hora local al correlacionar con otros sistemas.
- **La vista de 500 filas es un tope de visualización, no un tope de datos** — las entradas más antiguas permanecen en
  la base de datos; usa los filtros de búsqueda/acción (y la exportación CSV) para alcanzarlas.
- **El menú desplegable de acciones solo enumera acciones que han ocurrido**, así que una instalación
  nueva muestra una lista corta que se completa con el tiempo.
- **La suplantación se registra por completo** (`user.impersonate.start` / `.stop`), así que las
  acciones realizadas durante la suplantación son atribuibles.
- **Usa la columna Detalle para ver detalles específicos** — por ejemplo las horas CPE establecidas,
  los ids de prerrequisitos, el modo secuencial/foro, o el slug del curso importado.

## Relacionado
- Suplantación (`24-impersonation.md`)
- Exportaciones (`32-exports.md`)
- Copias de seguridad (`34-backups.md`)
- Usuarios y roles (`20-users-and-roles.md`)
