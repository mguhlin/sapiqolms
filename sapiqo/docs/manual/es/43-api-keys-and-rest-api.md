# Claves de API y la API REST

**Público:** administrador / operador
**Dónde:** Admin → Settings & integrations → **API keys** (`/admin/api-keys`).
Ruta base de REST: `{your-site}/api/v1`.

## Qué es

Sapiqo expone una pequeña API REST autenticada por token para integraciones
servidor a servidor — sincronizaciones de SIS, bots de reportes, scripts de aprovisionamiento. El acceso se concede
mediante **claves de API** que creas en la interfaz de administración. Una clave v1 concede acceso completo,
con alcance de administrador, a los endpoints `/api/v1`, así que trata una clave como una
credencial de administrador.

Las claves se muestran **una vez** en el momento de la creación. Solo se almacena el hash SHA-256 de la clave
(`app/api.php`), de modo que una base de datos filtrada no puede usarse para recuperar tokens activos.

## Cómo se usa

### Crear una clave

1. Abre **Admin → API keys**.
2. En **Create a key**, ingresa un nombre descriptivo (p. ej. "SIS sync", "Reporting
   bot") y haz clic en **Create key**.
3. La nueva clave se muestra una vez en una tarjeta resaltada — **cópiala de inmediato**.
   No se volverá a mostrar. Las claves tienen el aspecto `sk_` seguido de 48 caracteres hexadecimales.

La lista de claves muestra el nombre de cada clave, el prefijo (primeros 10 caracteres, para identificación),
la fecha de creación, la marca de tiempo de último uso y el estado (Active/Revoked).

### Revocar una clave

Haz clic en **Revoke** junto a cualquier clave activa. Las apps que la usan dejan de funcionar de inmediato.
La revocación es una eliminación suave (se establece `revoked_at`); la fila permanece para auditoría.

### Autenticar una solicitud

Envía la clave como token Bearer en cada solicitud:

```
Authorization: Bearer sk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Ejemplo:

```bash
curl -H "Authorization: Bearer $SAPIQO_KEY" \
     https://lms.example.org/api/v1/courses
```

Una clave ausente o inválida devuelve `401` con un error JSON y un
encabezado `WWW-Authenticate: Bearer`.

## Opciones y comportamiento

### Endpoints (v1)

Todas las respuestas son JSON; los endpoints de lista envuelven los resultados en un arreglo `data`.

| Method & path | Purpose |
|---------------|---------|
| `GET /api/v1/courses` | Lista cursos (id, slug, title, total_units, active). |
| `GET /api/v1/users` | Lista usuarios, paginado (`?page=`, `?per_page=`, máx. 200). Devuelve `data`, `page`, `per_page`, `total`. |
| `POST /api/v1/users` | Crea un usuario (first_name, last_name, email, role, user_type, campus, organization, password opcional). Devuelve `201`. |
| `GET /api/v1/users/{id}` | Un usuario con sus `enrollments` y `badges`. |
| `GET /api/v1/enrollments` | Inscripciones para un usuario (`?user=<id>`) **o** un curso (`?course=<id>`). Se requiere un parámetro. |
| `POST /api/v1/enrollments` | Inscribe a un usuario en un curso. Identifica al usuario por `user_id` o `email`, el curso por `course_id`, `course` o `slug`. Devuelve `201`. |
| `GET /api/v1/completions` | Inscripciones completadas (email, name, course slug, status, completed_at, badge_code), las más nuevas primero, hasta 1000. |
| `GET /api/v1/badges` | Insignias, todas o para un usuario (`?user=<id>`), hasta 1000. |

Los cuerpos de solicitud para los endpoints POST pueden ser JSON o con codificación de formulario (`api_body()` lee
JSON primero, luego recurre a los campos POST).

### Notas de comportamiento

- **Roles al crear.** `POST /api/v1/users` solo respeta `role: admin`; cualquier otra
  cosa crea un **estudiante**. Si no se suministra ninguna contraseña, se genera una
  aleatoria (la cuenta puede entonces usar restablecimiento de contraseña o SSO).
- **Inscripción casi idempotente.** `POST /api/v1/enrollments` llama a la misma función
  `enroll()` que usa la interfaz; inscribir a un usuario ya inscrito es seguro.
- **Paginación.** Solo `/api/v1/users` está paginado. `completions` y `badges`
  están limitados de forma estricta a 1000 filas por llamada.
- **Auditoría.** Las acciones de escritura registran entradas de auditoría atribuidas a `api:<key name>`
  (p. ej. `api.user.create`, `api.enroll`), para que puedas rastrear qué clave hizo qué.
- **Último uso.** Cada llamada autenticada marca el `last_used_at` de la clave.

## Cómo funciona

Cada ruta `/api/v1` llama a `require_api_key()`, que lee el encabezado `Authorization`
(recurriendo a `apache_request_headers()` donde sea necesario), extrae el
token Bearer, lo hashea con SHA-256 y busca una fila no revocada en `api_keys`.
Sin coincidencia → `401` JSON y salida. En caso de éxito, se devuelve la fila de la clave y se actualiza su
`last_used_at`.

La creación de claves (`api_key_create`) genera `sk_` + 24 bytes aleatorios (hex), almacena el
hash SHA-256 más un prefijo de 10 caracteres y el id del administrador que la crea, y devuelve el
texto plano una vez (guardado en el flash de sesión para la visualización única).

## Consejos y trampas

- **Copia la clave en el momento de la creación** — es irrecuperable después. Si se pierde, revócala
  y crea una nueva.
- **Una clave v1 tiene alcance de administrador.** Todavía no hay alcances por endpoint ni de solo lectura;
  emite claves de forma acotada, nómbralas con claridad y revoca las que no se usan.
- **Solo los administradores** pueden crear o revocar claves; esos POST están
  protegidos contra CSRF. Las llamadas a la API en sí son sin estado (sin CSRF, sin sesión).
- **Envía `Authorization: Bearer`, no un parámetro de consulta.** El token solo se lee del
  encabezado.
- **Usa HTTPS.** Un token Bearer en texto claro sobre HTTP queda expuesto en la red.
- Rota las claves periódicamente: crea la nueva, migra la integración y luego
  revoca la antigua — la columna de último uso ayuda a confirmar que nada la usa aún.

## Relacionado

- `20-users-and-roles.md` — los roles que la API puede asignar.
- `21-organizations-and-subscriptions.md` — inscripción de organización/grupo (lado de la interfaz).
- `45-security.md` — almacenamiento de tokens hasheados, HTTPS, registro de auditoría.
