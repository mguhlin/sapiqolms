# Cuentas e inicio de sesión

**Audiencia:** estudiante
**Dónde:** /login, /register, /forgot y Perfil (navegación superior → Perfil, o /profile)

## Qué es
Cada estudiante tiene una sola cuenta de Sapiqo. Inicias sesión con tu dirección de
correo electrónico y una contraseña, o con un botón de inicio de sesión único (SSO)
si tu organización lo ha habilitado (Google, Microsoft, Clever, ClassLink o Rhythm).
Tu cuenta guarda tu progreso en los cursos, tus insignias, certificados y expediente,
de modo que iniciar sesión en cualquier dispositivo te trae todo eso contigo.

Según cómo haya configurado Sapiqo tu administrador, es posible que puedas crear tu
propia cuenta, o que las cuentas se aprovisionen por ti. Tu dirección de correo
electrónico es a la vez tu identidad y tu nombre de inicio de sesión.

## Cómo usarlo
1. Ve a la página de inicio de sesión (/login). Ingresa tu **Correo electrónico** y
   tu **Contraseña**, luego elige **Iniciar sesión**.
2. Si ves botones SSO en la parte superior de la tarjeta de inicio de sesión (por
   ejemplo **Continuar con Google**), puedes seleccionar uno en lugar de escribir una
   contraseña. Serás enviado a ese proveedor, inicias sesión ahí y regresas a tu
   panel.
3. Si el autorregistro está habilitado, elige **Crear una cuenta** en la página de
   inicio de sesión (o **Registrarse** en la navegación superior) y completa el
   formulario (ver más abajo).
4. Si olvidas tu contraseña, elige **¿Olvidaste tu contraseña?** en la página de
   inicio de sesión y sigue los pasos de restablecimiento.
5. Una vez que hayas iniciado sesión, abre **Perfil** en la navegación superior para
   actualizar tus datos, subir una foto o cambiar tu contraseña.

### Crear una cuenta (autorregistro)
1. En /register, ingresa tu **Nombre**, **Apellido** y **Correo electrónico**.
2. Elige una **Contraseña** de al menos 8 caracteres.
3. Opcionalmente agrega tu **Teléfono**, **Plantel**, **Organización / Distrito** y
   **Tipo de usuario** (Maestro, Estudiante, Administrador, Personal u Otro).
4. Opcionalmente elige un curso en **Inscribirse en un curso (opcional)** para
   quedar inscrito de inmediato.
5. Elige **Crear cuenta**. Se inicia tu sesión y se te lleva a tu panel.

### Restablecer tu contraseña
1. En /forgot, ingresa el correo electrónico de tu cuenta y elige **Enviar enlace de
   restablecimiento**.
2. Sapiqo siempre muestra el mismo mensaje de confirmación exista o no una cuenta
   para esa dirección (esto protege la privacidad). Si existe una cuenta, se genera
   un enlace de restablecimiento.
3. Abre el enlace (válido por una hora), ingresa tu **Nueva contraseña** dos veces y
   elige **Establecer nueva contraseña**.
4. Inicia sesión con tu nueva contraseña.

### Cambiar tu contraseña (con la sesión iniciada)
1. Abre **Perfil**.
2. En la tarjeta inferior, escribe en **Nueva contraseña (opcional)**. Dejarla en
   blanco mantiene tu contraseña actual.
3. Elige **Guardar cambios**.

### Subir una foto de perfil
1. En **Perfil**, en **Foto de perfil**, elige un archivo de imagen (JPEG, PNG o
   WebP) y selecciona **Subir foto**.
2. Para quitar una foto que subiste, elige **Quitar**.

## Opciones y comportamiento
- **Inicio de sesión con correo + contraseña** — el método estándar. Las contraseñas
  deben tener al menos 8 caracteres.
- **Botones SSO** — aparece uno por cada proveedor que tu administrador haya
  habilitado y configurado (Google, Microsoft, Clever, ClassLink, Rhythm). Si no hay
  proveedores habilitados, no verás ningún botón SSO ni el separador "o usa tu correo
  electrónico".
- **Enlace Crear una cuenta** — aparece solo cuando tu administrador habilita el
  autorregistro. Si está desactivado, registrarse te redirige de vuelta al inicio de
  sesión con una nota para que le pidas una cuenta a tu administrador.
- **¿Olvidaste tu contraseña?** — inicia el restablecimiento de autoservicio. Si la
  entrega de correo no está configurada en el servidor, la página de
  restablecimiento te indica que contactes a tu administrador para obtener el enlace.
- **Campos del perfil** — Nombre, Apellido, Teléfono, Tipo de usuario, Plantel y
  Organización / Distrito son editables. **El correo electrónico se muestra pero está
  deshabilitado** — es tu inicio de sesión y solo un administrador puede cambiarlo.
- **Foto de perfil** — sube una o quítala. Si iniciaste sesión con Google o
  Microsoft, se usa automáticamente la foto de tu proveedor hasta que subas la tuya.

## Cómo funciona
Las contraseñas se almacenan solo como hashes seguros de una sola vía, nunca como
texto sin cifrar, y se vuelven a cifrar automáticamente si cambia la configuración de
seguridad. Los enlaces de restablecimiento de contraseña son de un solo uso y
caducan después de una hora; solo se almacena un hash del enlace, de modo que una
copia de la base de datos no puede usarse para tomar el control de cuentas. Los
intentos fallidos repetidos de inicio de sesión están limitados por tasa — después
de demasiados intentos se te pide esperar unos minutos antes de volver a intentarlo.

El SSO funciona mediante OAuth/OpenID Connect. Cuando inicias sesión con un proveedor
por primera vez, Sapiqo te asocia a una cuenta existente por correo electrónico o
crea una cuenta de estudiante nueva, e importa tu nombre (y foto, cuando está
disponible). Un botón de proveedor solo aparece una vez que tu administrador ha
ingresado credenciales reales para él.

Las fotos subidas se recortan en un cuadrado centrado y se redimensionan a 256×256
píxeles.

## Consejos y detalles
- Tu correo electrónico no distingue mayúsculas de minúsculas para iniciar sesión.
- Si tu inicio de sesión con SSO falla o lo cancelas, regresas a /login con un aviso
  breve — simplemente vuelve a intentarlo.
- Si falta el enlace **Crear una cuenta**, el autorregistro está desactivado; pídele
  a tu administrador que te agregue.
- Si no recibes un correo de restablecimiento, verifica que el correo esté
  configurado en tu servidor; de lo contrario, tu administrador puede obtener el
  enlace por ti.
- No puedes cambiar tu propio correo electrónico en la página de Perfil — contacta a
  un administrador.

## Relacionado
- [Tomar un curso](02-taking-a-course.md)
- [Insignias, certificados y tu expediente](05-badges-certificates-transcript.md)
- [Notificaciones](06-notifications.md)
