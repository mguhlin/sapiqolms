# Ver como (suplantación de identidad)

**Público:** administrador
**Dónde:** Admin → Gestionar usuarios (/admin/users) → la fila de un usuario → **Ver como**; o el
editor de usuarios (/admin/users/{id}) → **👁️ Ver como este usuario**. Regresa mediante el botón
**Volver a administrador** del banner ámbar.

## Qué es
**Ver como** permite a un administrador actuar temporalmente como otro usuario para solucionar problemas —
para ver exactamente lo que ese estudiante ve en su panel, en un curso o en su
expediente. Mientras ves como alguien, Sapiqo te trata como ese usuario: ves
sus páginas y no puedes alcanzar rutas solo para administradores. Un solo clic te regresa a
tu propia cuenta de administrador.

La suplantación de identidad está deliberadamente limitada: un administrador **no puede ver como otro
administrador**, y cada inicio y detención se registra en el registro de auditoría.

## Cómo usarla
1. Abre Admin → Gestionar usuarios (/admin/users) y encuentra a la persona, o abre su
   editor en /admin/users/{id}.
2. Elige **Ver como** (en la lista) o **👁️ Ver como este usuario** (en el editor).
   El botón aparece solo para cuentas que no son de administrador y nunca en tu propia fila.
3. Se te cambia a la sesión de ese usuario y se te lleva a su **panel**. Un
   mensaje emergente confirma como quién estás viendo ahora.
4. Un banner ámbar **"👁️ Viendo como … "** permanece en la parte superior de cada página mientras
   suplantas identidad.
5. Cuando termines, elige **Volver a administrador** en ese banner. Se te regresa a
   tu cuenta de administrador y de vuelta a la página del editor de ese usuario.

## Opciones y comportamiento
- **Como quién puedes ver** — cualquier cuenta que no sea de administrador. Intentar ver como un
  administrador se rechaza con "Por seguridad, no puedes ver como otro
  administrador." Tampoco puedes verte como tú mismo.
- **Una a la vez** — no puedes iniciar una segunda suplantación mientras ya estás
  suplantando; se te indica que debes "Volver a administrador primero."
- **Qué puedes hacer** — exactamente lo que puede hacer el usuario objetivo. Como la sesión
  ahora refleja al objetivo, `is_admin()` es falso mientras suplantas, por lo que las páginas solo
  para administradores están bloqueadas. Úsalo para reproducir y diagnosticar la experiencia del estudiante, no
  para realizar trabajo administrativo.
- **Regresar** — **Volver a administrador** restaura tu sesión original de administrador y
  te deja en el editor del usuario (/admin/users/{id}) para que puedas continuar.
- **A prueba de fallos** — si tu cuenta de administrador original ha sido eliminada o ha perdido su rol de
  administrador mientras suplantabas, elegir **Volver a administrador** te cierra la sesión
  por completo en lugar de dejarte en un estado roto.

## Cómo funciona
Iniciar la suplantación (`begin_impersonation()`) guarda tu id real de administrador en la
sesión y cambia el id de usuario activo al objetivo, regenerando el id de sesión en el
cambio de privilegio. A partir de entonces `current_user()` e `is_admin()` reflejan al
objetivo, por lo que las rutas de administrador son inaccesibles y la vista del estudiante es
fiel.

Regresar (`end_impersonation()`) restaura el id de administrador almacenado y de nuevo
regenera el id de sesión. El diseño renderiza el banner ámbar siempre que
`is_impersonating()` sea verdadero.

Ambos eventos se auditan: `user.impersonate.start` registra al objetivo y su
correo electrónico; `user.impersonate.stop` registra al administrador original y el id del objetivo. Las
acciones de suplantación están protegidas contra CSRF.

## Consejos y detalles
- **El banner es tu señal.** Si ves la barra ámbar "Viendo como …", no estás actuando
  como tú mismo — termina tu revisión y elige **Volver a administrador**.
- **No puedes hacer tareas de administrador mientras suplantas.** Regresa primero, luego haz
  cambios como tú mismo. Esto es intencional para evitar confusión de privilegios.
- **No hay suplantación de administrador a administrador.** Para solucionar el problema de otro administrador, no
  puedes ver como él; investiga mediante el registro de auditoría y la configuración de su cuenta
  en su lugar.
- **Todo queda registrado.** Las entradas de inicio/detención en el registro de auditoría (/admin/audit)
  dan un rastro responsable de quién vio como quién.
- Siempre **Vuelve a administrador** en lugar de simplemente navegar a otro lugar — el banner te sigue,
  pero regresar limpiamente restaura tu sesión de administrador.

## Relacionado
- [Usuarios y roles](20-users-and-roles.md)
- [El área Gestionar para subadministradores](25-manage-area-for-sub-admins.md)
