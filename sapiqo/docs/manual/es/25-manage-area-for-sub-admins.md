# El área Gestionar para subadministradores

**Público:** administrador (para entender y configurar subadministradores); gestores de grupo y
de organización (usuarios cotidianos de esta área)
**Dónde:** Gestionar (/manage) — aparece en la navegación superior para gestores de grupo y
de organización. También accesible en /manage/orgs/{id}, /manage/groups/{id}
y /manage/users/{id}.

## Qué es
El área **Gestionar** es una consola de autoservicio con alcance limitado para **subadministradores** — los
gestores de grupo y gestores de organización que un administrador ha asignado. Muestra
solo las organizaciones y grupos que una persona gestiona, y le permite hacer exactamente
lo que sus permisos concedidos permiten: inscribir miembros, editar perfiles de miembros o
gestionar suscripciones de cursos. Nunca expone la configuración del sitio, el contenido de cursos ni
los grupos de otras personas.

Los administradores asignan estos gestores en la página de Organizaciones
(/admin/orgs/{id}) y en las páginas de Grupos / Gestión de cuentas — ver "Organizaciones
y suscripciones" y "Grupos y gestores." Esta página explica qué ve un gestor
y qué puede hacer una vez asignado.

## Cómo usarla (como gestor)
1. Inicia sesión y elige **Gestionar** en la navegación superior (aparece para cualquiera que
   gestione al menos un grupo u organización). Aterrizas en /manage.
2. El inicio de **Gestionar** lista tus **Organizaciones** (si las hay) y tus **Grupos**,
   cada uno con conteos de miembros y finalización. Elige **Abrir** para trabajar con uno.
3. En una página de organización o grupo puedes, sujeto a tus permisos:
   - **Agregar miembros por correo electrónico** — sepáralos con comas, espacios o nuevas líneas.
   - **Quitar** un miembro de la organización o grupo.
   - **Editar** un miembro (abre /manage/users/{id}): actualiza su perfil o
     inscripciones.
   - **Suscribir e inscribir** la organización/grupo a cursos, o **Quitar** una suscripción.
4. Cuando agregas un correo electrónico que **aún no tiene cuenta**, Sapiqo crea una cuenta de
   estudiante sobre la marcha con una **contraseña temporal** y te la muestra en una tarjeta **Nuevas
   cuentas creadas**. Comparte cada contraseña temporal de forma segura y pide a la
   persona que la cambie en su página de Perfil.

## Qué permite cada permiso
Una concesión de gestor lleva hasta tres permisos. Lo que aparece y funciona en el área
Gestionar depende de cuáles tengas:

- **Inscribir** (`enroll`) — te permite cambiar las **inscripciones** de curso de un miembro desde
  su página de edición (/manage/users/{id}). Marca para inscribir, desmarca para quitar.
- **Miembros** (`members`) — te permite **agregar y quitar miembros** del grupo/organización
  y **editar perfiles de miembros** (nombre, teléfono, tipo de usuario, campus, organización) y
  establecer la contraseña de un miembro en su página de edición. Agregar miembros es también lo que dispara
  la creación de cuentas sobre la marcha. Nota: un gestor **no puede cambiar el correo electrónico
  (inicio de sesión) de un miembro** — ese campo se muestra deshabilitado y solo un administrador completo puede
  cambiarlo.
- **Cursos** (`courses`) — te permite **gestionar suscripciones de cursos** para el
  grupo/organización: el formulario **Suscribir e inscribir** y los botones **Quitar** por curso
  aparecen solo con este permiso. Sin él, la lista de suscripciones es de
  solo lectura y una nota explica que un administrador controla las suscripciones.

Para los gestores de organización, los tres permisos son **Puede inscribir miembros**, **Puede
agregar/editar miembros** y **Puede gestionar suscripciones de cursos**, establecidos cuando el administrador
asigna al gestor. `enroll` y `members` están ACTIVADOS por defecto; `courses` está DESACTIVADO por defecto.

Un gestor de organización gestiona implícitamente **cada grupo** bajo su organización, por lo que
también puede abrir esos grupos en el área Gestionar.

## Opciones y comportamiento
- **Visibilidad con alcance** — /manage muestra solo las organizaciones y grupos que gestionas;
  abrir uno que no gestionas devuelve una respuesta "Prohibido".
- **Creación de cuentas sobre la marcha** — en los formularios de "Agregar miembros por
  correo electrónico" tanto del grupo como de la organización, los correos electrónicos desconocidos (pero válidos) se convierten en nuevas cuentas de estudiante con una
  contraseña temporal generada. Las contraseñas se muestran una vez, en la tarjeta **Nuevas
  cuentas creadas**, justo después de agregarlas.
- **Eliminación no destructiva** — quitar un miembro de una organización/grupo conserva su
  cuenta, inscripciones y progreso.
- **Cancelación de suscripción no destructiva** — quitar una suscripción mantiene a los estudiantes
  actuales inscritos; solo se detiene la futura inscripción automática.
- **Inscripción automática al agregar** — un miembro recién agregado se inscribe automáticamente en las suscripciones
  de cursos del grupo (y, para grupos en una organización, de la organización).
- **Vista de finalización y exportación** — las páginas de grupo y organización muestran estadísticas de miembros/finalización;
  la página del grupo tiene un filtro de curso **Finalización para:** y un botón **⬇ Exportar CSV**.
- **Los administradores aterrizan en /admin/groups** — si un administrador completo sin concesiones de
  gestor visita /manage, se le redirige a Admin → Grupos (su herramienta más amplia). Los
  administradores que *también* gestionan algo pueden usar /manage directamente.

## Cómo funciona
Cada ruta de Gestionar vuelve a verificar el acceso: `require_group_access()` /
`require_org_access()` confirman que gestionas esa entidad, y cada escritura también verifica
el permiso específico (`manager_allows_group()` / `manager_allows_org()` para
`enroll`, `members` o `courses`) antes de actuar — de modo que la ocultación de un control en la interfaz está
respaldada por una protección del lado del servidor. Las ediciones de miembros pasan por `manager_allows_user()`,
que es verdadero cuando tienes el permiso para un grupo al que pertenece el objetivo.
Las cuentas sobre la marcha se crean con `register_local()` usando una contraseña temporal
aleatoria, y la contraseña temporal en texto plano se devuelve una vez mediante la sesión para su
visualización. Agregar/quitar miembros y suscribir/cancelar suscripción reutilizan las mismas
funciones `org_add_member` / `add_member` / `group_subscribe` / `org_subscribe` que las
páginas de administrador, por lo que la inscripción automática y la semántica no destructiva son idénticas.

## Consejos y detalles
- **Concede "cursos" deliberadamente.** Permite a un gestor cambiar en qué está inscrito todo el
  grupo/organización; déjalo desactivado si los gestores solo deben manejar personas.
- **Las contraseñas temporales se muestran una vez.** Cópialas de la tarjeta **Nuevas cuentas
  creadas** de inmediato; si te pierdes una, un administrador puede generar un enlace de restablecimiento
  desde el editor de usuarios.
- **Los gestores no pueden cambiar correos electrónicos.** El correo electrónico es la identidad de inicio de sesión — dirige esas
  solicitudes a un administrador completo.
- **Quitar ≠ revocar acceso.** Quitar un miembro conserva su expediente e
  inscripciones; úsalo para ordenar listas, no para retirar crédito.
- Si falta **Gestionar** en la navegación de un gestor, es que aún no se le ha
  asignado a ningún grupo u organización — asígnalo desde las páginas de administrador de organización/grupo.

## Relacionado
- [Organizaciones y suscripciones](21-organizations-and-subscriptions.md)
- [Grupos y gestores](22-groups-and-managers.md)
- [Usuarios y roles](20-users-and-roles.md)
- [Ver como (suplantación de identidad)](24-impersonation.md)
