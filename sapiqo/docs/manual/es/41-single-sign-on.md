# Inicio de sesión único (SSO)

**Público:** administrador / operador
**Dónde:** `sapiqo-data/config.local.php` (el bloque `'sso'`). Los botones de inicio de sesión aparecen
automáticamente en la página de inicio de sesión (`/login`).

## Qué es

Sapiqo admite inicio de sesión único opcional para que los estudiantes puedan iniciar sesión con una cuenta
existente en lugar de un correo electrónico/contraseña local. Hay cinco proveedores integrados:

- **Google** (OAuth2 / OIDC)
- **Microsoft** (Azure AD / Entra ID; por inquilino)
- **Clever** (SSO/registro de listas para K-12)
- **ClassLink** LaunchPad (SSO para K-12)
- **Rhythm** (SSO para K-12; endpoints por inquilino)

El SSO se controla enteramente por configuración. Un proveedor solo aparece en la página de inicio de sesión una vez que está
habilitado **y** tiene un ID de cliente real (`sso_providers()` en `app/auth.php` omite cualquier
proveedor que esté deshabilitado o al que le falten credenciales). Nunca se almacenan credenciales
en la base de datos — viven únicamente en la configuración local basada en archivos fuera de la raíz web.

## Cómo se usa

1. **Registra una URI de redirección con el proveedor.** Para cada proveedor, la URL de devolución de llamada
   sigue un patrón:

   ```
   {your-site}/auth/<provider>/callback
   ```

   p. ej. `https://lms.example.org/auth/google/callback`,
   `.../auth/microsoft/callback`, `.../auth/clever/callback`,
   `.../auth/classlink/callback`, `.../auth/rhythm/callback`.
   (Esto es exactamente lo que `sso_redirect_uri()` construye a partir de la URL base de tu sitio).

2. **Edita `sapiqo-data/config.local.php`** y completa el `client_id` y el `client_secret`
   del proveedor, luego establece `enabled => true`. Parte de
   `app/config.local.example.php`, que contiene el bloque `'sso'` completo y anotado.

3. **Vuelve a cargar la página de inicio de sesión.** Aparece un botón "Continue with *Provider*" para cada
   proveedor habilitado y con credenciales.

No hay una pantalla de interfaz de administración para el SSO — es una función basada en archivos de configuración por diseño (los
secretos se mantienen fuera de la base de datos y fuera de la raíz web).

## Opciones y comportamiento

Cada proveedor necesita campos diferentes:

| Provider   | Required fields | Notes |
|-----------|-----------------|-------|
| Google    | `client_id`, `client_secret` | Los endpoints OIDC estándar están integrados. |
| Microsoft | `client_id`, `client_secret`, `tenant` | `tenant` tiene por defecto `common`; establece tu directorio (GUID o dominio) para restringir a tu organización. |
| Clever    | `client_id`, `client_secret` | Usa intercambio de token HTTP Basic; el usuario se resuelve mediante `/me` → `/users/{id}`. |
| ClassLink | `client_id`, `client_secret` | Los campos de userinfo se mapean (Email/FirstName/LastName/UserId). |
| Rhythm    | `client_id`, `client_secret`, **`auth_url`**, **`token_url`**, **`userinfo_url`** | Los endpoints varían por inquilino de distrito, así que debes establecerlos explícitamente. `scope` y `map` opcionales. |

**Rhythm es la excepción.** Google, Microsoft, Clever y ClassLink tienen sus
endpoints de OAuth codificados de forma fija. Rhythm no — porque los endpoints difieren según el
inquilino de distrito, Rhythm ni siquiera aparecerá a menos que `client_id`, `auth_url` y
`token_url` estén todos establecidos. Completa, por ejemplo:

```php
'rhythm' => [
    'enabled'      => true,
    'client_id'     => '...',
    'client_secret' => '...',
    'auth_url'      => 'https://<tenant>.rhithm.app/oauth/authorize',
    'token_url'     => 'https://<tenant>.rhithm.app/oauth/token',
    'userinfo_url'  => 'https://<tenant>.rhithm.app/oauth/userinfo',
    'scope'         => 'openid email profile',
    // Optional if the tenant uses non-standard field names:
    // 'map' => ['email' => 'email', 'first' => 'first_name', 'last' => 'last_name', 'sub' => 'id'],
],
```

**Inquilino de Microsoft.** Deja `tenant => 'common'` para permitir cualquier cuenta de Microsoft, o
establece el ID/dominio de tu inquilino para restringir a tu organización. Las URL de
authorize/token se construyen con el valor del inquilino.

**Creación y vinculación de cuentas.** Tras una devolución de llamada exitosa, Sapiqo busca al usuario
por el correo electrónico devuelto por el proveedor. Si coincide con una cuenta existente, se usa esa
cuenta (el SSO efectivamente se vincula a ella). Si no existe ninguna cuenta, se crea automáticamente una nueva
cuenta de **estudiante** con `auth_provider` establecido en el nombre del
proveedor. Si el proveedor devolvió una foto de perfil y el usuario aún no tiene
avatar, se guarda. Los nuevos usuarios de SSO siempre se crean como estudiantes — eleva los
roles después en Admin → Users.

**Alcances (scopes).** Se usan valores predeterminados sensatos por proveedor (`openid email profile` para
Google/Microsoft/Rhythm; `read:user_id read:sis` para Clever; `profile` para
ClassLink). El scope de Rhythm se puede anular en la configuración.

## Cómo funciona

`sso_providers()` devuelve únicamente los proveedores habilitados y con credenciales como un arreglo de
descriptores de endpoint. La vista de inicio de sesión los recorre para renderizar un botón
`/auth/<provider>` por cada uno. Al hacer clic en un botón se llama a `sso_begin()`, que almacena
un `state` aleatorio en la sesión y redirige a la URL de authorize del proveedor con
`response_type=code` y tu URI de redirección.

El proveedor redirige de vuelta a `/auth/<provider>/callback`, gestionado por
`sso_complete()`: verifica el `state` (protección CSRF con `hash_equals`),
intercambia el código de autorización por un token de acceso, obtiene el userinfo, lo normaliza
a `email/first/last/sub/picture`, luego encuentra o crea el usuario local e inicia
su sesión. Google/Microsoft/ClassLink/Rhythm usan el flujo genérico de OAuth2
(`sso_identity_oauth`); Clever usa un intercambio de token con autenticación Basic más una búsqueda de usuario
en dos pasos (`sso_identity_clever`).

La URI de redirección siempre se deriva del sitio actual
(`base_url_absolute() . '/auth/<provider>/callback'`), así que debe coincidir exactamente con lo que
registraste — incluyendo el esquema (https) y el host.

## Consejos y trampas

- **El botón no se mostrará** si `enabled` es false, `client_id` está en blanco o (para
  Rhythm) faltan `auth_url`/`token_url`. Verifica los tres para Rhythm.
- **La URI de redirección debe coincidir exactamente.** La mayoría de las fallas de SSO son un desajuste entre la
  URI que registraste y `{your-site}/auth/<provider>/callback`. Detrás de un proxy,
  asegúrate de que Sapiqo vea HTTPS (consulta `trusted_proxies` / `X-Forwarded-Proto`) para que la
  devolución de llamada se construya como `https://`.
- **El correo electrónico es obligatorio.** Si un proveedor no entrega ningún correo electrónico, la creación de la cuenta falla
  y se rechaza el inicio de sesión. Configura el proveedor para que entregue email/profile.
- **Los usuarios de SSO empiezan como estudiantes.** No hay mapeo de roles desde el IdP; promueve
  administradores/desarrolladores manualmente.
- **Los secretos permanecen en el archivo.** Mantén `sapiqo-data/config.local.php` fuera de la raíz web
  y legible solo por el usuario web — contiene los secretos de cliente.
- El inicio de sesión local con correo electrónico/contraseña sigue funcionando junto con el SSO; el SSO es aditivo.

## Relacionado

- `01-accounts-and-signing-in.md` — la experiencia de inicio de sesión de cara al estudiante.
- `20-users-and-roles.md` — promover estudiantes creados por SSO a otros roles.
- `45-security.md` — endurecimiento de sesión, secretos fuera de la raíz web.
- `DEPLOYMENT.md` §8 — recorrido de configuración del SSO.
