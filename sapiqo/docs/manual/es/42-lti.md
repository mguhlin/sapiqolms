# LTI 1.3 (lanzar Sapiqo desde tu LMS)

**Público:** administrador / operador
**Dónde:** Admin → Settings & integrations → **LTI 1.3** (`/admin/lti`)

## Qué es

Sapiqo es un **proveedor de herramientas LTI 1.3 Advantage**. Eso permite que un LMS de plataforma —
Canvas, Moodle, Blackboard, Schoology o cualquier plataforma LTI 1.3 — lance un curso
de Sapiqo como herramienta externa. Al lanzarse, el estudiante se aprovisiona e inicia sesión
automáticamente, y al completar, Sapiqo puede devolver una calificación a la plataforma mediante
AGS (Assignment & Grade Services).

La implementación (`app/lti.php`) hace la firma/verificación JWT (RS256) y JWKS a
mano usando la extensión OpenSSL — no se requiere ninguna biblioteca JWT externa. Sapiqo
genera y almacena su propio par de claves RSA una vez, en `sapiqo-data/data/lti/`.

## Cómo se usa

El registro es un intercambio bidireccional: das a la plataforma las URL de herramienta de Sapiqo y
registras los detalles de la plataforma en Sapiqo.

### 1. Da al administrador de la plataforma los detalles de la herramienta de Sapiqo

En la parte superior de `/admin/lti`, la tarjeta **Your tool details** muestra (desde
`lti_tool_urls()`):

- **URL de inicio / iniciación OIDC** — `{your-site}/lti/login`
- **URL de lanzamiento / redirección** — `{your-site}/lti/launch` (también la única URI de redirección)
- **URL pública de JWKS** — `{your-site}/lti/jwks`
- **Key ID (kid)** — el id de la clave de firma actual

Una versión legible por máquina de lo mismo está en `{your-site}/lti/config` (JSON).

Ingresa estos datos al crear la clave de desarrollador / herramienta LTI 1.3 en tu plataforma:

- **Canvas** — crea una LTI Developer Key; usa la URL de inicio OIDC como "OpenID
  Connect Initiation URL", la URL de lanzamiento como "Target Link URI" / Redirect URI,
  y la URL de JWKS como método público de JWKS.
- **Moodle** — agrega una External tool con LTI 1.3; pega la URL de iniciación, la URL de redirección
  (lanzamiento) y la URL del conjunto de claves público (JWKS).
- **Blackboard** — registra la herramienta en el portal de desarrolladores con las mismas tres
  URL.

### 2. Registra la plataforma en Sapiqo

En la tarjeta **Register a platform**, completa lo que la plataforma te devuelve:

| Field | Meaning |
|-------|---------|
| Name | Etiqueta descriptiva, p. ej. "Canvas de nuestro distrito". |
| Issuer (iss) | El emisor de la plataforma, p. ej. `https://canvas.instructure.com`. |
| Client ID | El ID de cliente/clave de desarrollador que asigna la plataforma. |
| Deployment ID | Opcional; si se establece, se aplica en el lanzamiento. |
| Auth / OIDC authorize URL | El endpoint de redirección de autorización de la plataforma. |
| Access-token URL | El endpoint de token OAuth2 de la plataforma (necesario para la devolución de calificaciones). |
| Platform JWKS URL | Dónde Sapiqo obtiene las claves de firma de la plataforma (preferido). |
| …o clave pública (PEM) | Pega un PEM en línea en lugar de una URL de JWKS. |

Proporciona **o bien** una URL de JWKS (preferida, con rotación automática) **o bien** un PEM en línea para
la verificación de firma. Las plataformas registradas se enumeran debajo del formulario y se pueden
eliminar.

### 3. Apunta un lanzamiento a un curso específico

Por defecto, un lanzamiento lleva al estudiante a su panel. Para abrir un curso
específico, agrega un **parámetro personalizado** en el enlace/emplazamiento de tu plataforma:

```
course=<slug>
```

(`sapiqo_course=<slug>` también se acepta). El estudiante queda entonces inscrito y es llevado
a ese curso.

## Opciones y comportamiento

**Aprovisionamiento de usuarios.** Al lanzarse, `lti_provision_user()` busca al usuario por el
correo electrónico en el id_token. Si no existe ninguno, se crea una nueva cuenta (un estudiante, a menos
que el claim de roles de LTI contenga "administrator", en cuyo caso se crea un administrador). Si
la plataforma no entrega ningún correo electrónico, se sintetiza una pseudodirección estable a partir del
emisor+asunto para que la misma persona se mapee a la misma cuenta cada vez.

**Devolución de calificaciones (AGS).** Si el lanzamiento incluye un claim de endpoint AGS con un
lineitem y el scope `score`, Sapiqo lo registra (`lti_capture_ags`). Al completar el curso,
Sapiqo puede publicar (POST) una calificación de vuelta (`lti_send_score`) — un porcentaje 0–100 con
`activityProgress: Completed` / `gradingProgress: FullyGraded`. La devolución necesita que la
**Access-token URL** de la plataforma esté registrada; Sapiqo autentica el POST de la calificación
con una aserción firmada de credenciales de cliente. La devolución es de mejor esfuerzo: las fallas
se registran, no se muestran al estudiante.

**Seguridad del lanzamiento.** El flujo OIDC usa `state` + `nonce` respaldados por la base de datos (no
cookies de sesión, para que los lanzamientos `form_post` entre sitios funcionen). La firma del id_token se
verifica contra la clave de la plataforma; la audiencia, el nonce, el Deployment ID y el tipo de
mensaje se validan todos, con una tolerancia de desfase de reloj de 60 segundos. Las filas de state son
de un solo uso y caducan tras ~10 minutos.

## Cómo funciona

1. La plataforma inicia el inicio de sesión OIDC → `GET/POST /lti/login` (`lti_oidc_login`): Sapiqo
   encuentra la plataforma por emisor (+client_id), almacena state/nonce en `lti_state` y
   redirige a la URL de authorize de la plataforma.
2. La plataforma autentica y hace `form_post` de un `id_token` firmado → `POST /lti/launch`
   (`lti_launch`): Sapiqo consume el state, verifica el JWT mediante el
   JWKS/PEM de la plataforma, valida los claims, aprovisiona + inicia sesión del usuario, captura cualquier endpoint
   AGS y redirige al curso (o al panel).
3. Al completar, `lti_send_score()` obtiene un token de acceso de la plataforma
   (`lti_get_token`, aserción de cliente firmada) y publica (POST) la calificación en el
   `/scores` del lineitem.
4. La plataforma verifica las aserciones de Sapiqo contra `{your-site}/lti/jwks`
   (`lti_jwks`), servido desde el par de claves almacenado de Sapiqo.

Un lanzamiento también escribe una entrada `lti.launch` en el registro de auditoría.

## Consejos y trampas

- **Solo los administradores** pueden acceder a `/admin/lti`; los POST de registro/eliminación están
  protegidos contra CSRF.
- **Mantén `sapiqo-data/data/lti/` seguro.** Contiene la clave de firma privada de Sapiqo. Está
  dentro de la raíz de datos (fuera de la raíz web) y se genera automáticamente en el
  primer uso; respáldalo junto con el resto de `sapiqo-data/`.
- **¿No se abre ningún curso?** Confirma que el parámetro personalizado del emplazamiento sea `course=<slug>`
  con un slug de curso exacto y existente — de lo contrario, el estudiante aterriza en el panel.
- **¿La devolución de calificaciones es silenciosa?** Asegúrate de que la Access-token URL esté registrada y de que la
  plataforma haya concedido el scope AGS `score`; revisa el registro de errores del servidor en busca de
  mensajes `lti_send_score`.
- **¿Falla la verificación?** Prefiere la URL de JWKS a un PEM pegado para que la rotación de claves
  no rompa los lanzamientos; confirma que el Issuer y el Client ID coincidan exactamente con los valores de la
  plataforma.
- La herramienta expone exactamente una URI de redirección — la URL de lanzamiento. Registra esa, no la
  URL de inicio de sesión, como la URI de redirección/destino.

## Relacionado

- `41-single-sign-on.md` — la otra manera en que los estudiantes llegan preautenticados.
- `13-gradebook.md` — cómo se rastrean las finalizaciones/calificaciones en Sapiqo.
- `45-security.md` — manejo de JWT/JWKS y postura de CSRF para los endpoints de LTI.
