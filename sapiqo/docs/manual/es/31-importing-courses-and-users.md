# Importar cursos y usuarios

**Audiencia:** administrador
**Dónde:** Importaciones y copias de seguridad — `/admin/import`

## Qué es
La pantalla de **Importaciones y copias de seguridad** trae personas y contenido de cursos a Sapiqo en
un solo lugar. Tiene tres secciones, accesibles desde los enlaces de salto de la parte superior:

- **Usuarios (CSV)** — crea/actualiza cuentas de forma masiva (y opcionalmente las inscribe y agrupa)
  a partir de una hoja de cálculo.
- **OneRoster (SIS)** — importa usuarios, escuelas e inscripciones desde una exportación de un Sistema
  de Información Estudiantil.
- **Contenido de cursos** — importa un curso completo como paquete, Common Cartridge,
  SCORM o exportación de LearnDash.

Un cuarto enlace, **Descargar copia de seguridad**, va a la página de copias de seguridad (cubierta por separado).

## Cómo usarla

### Importar usuarios de forma masiva (CSV)
1. En la sección **Usuarios (CSV)**, elige **⬇ Descargar plantilla CSV** para obtener el
   diseño exacto de columnas (también disponible en `/admin/template.csv`).
2. Completa una fila por persona. La fila de encabezado es obligatoria. Las columnas son:

   ```
   first name,last name,email,campus,organization,user type,course title/id,group
   ```

   Solo **email** es obligatorio por fila. Los nombres de encabezado se emparejan de forma flexible (por
   ejemplo `school` se asigna a campus, `district` a organización, `role` a tipo de
   usuario, `cohort` a grupo), pero los nombres de la plantilla son los más seguros.
3. Elige el archivo CSV y selecciona **Cargar e importar**.
4. Lee el **Informe de importación** que aparece debajo del formulario (ver Opciones y comportamiento).

Filas de ejemplo de la plantilla:

```
first name,last name,email,campus,organization,user type,course title/id,group
Ada,Lovelace,ada@example.edu,Central High School,Example ISD,Teacher,Chromebook Educator,Example ISD
Grace,Hopper,grace@example.edu,North Elementary,Example ISD,Teacher,chromebook-educator,Example ISD
```

La columna **course title/id** acepta ya sea el título de visualización del curso o su
slug/id; déjala en blanco para crear una cuenta sin inscribir. La columna **group**
crea (o reutiliza) un grupo por nombre y agrega a la persona a él.

### Importar desde un SIS (OneRoster)
1. En la sección **OneRoster (SIS)**, elige tu archivo de exportación. Sapiqo acepta un
   `.zip` de **OneRoster v1.1** que contenga `orgs.csv`, `users.csv`, `classes.csv`
   y `enrollments.csv` — o solo un único `users.csv`.
2. Selecciona **Importar OneRoster**.

Las cuentas se crean o actualizan, se agrupan por escuela/organización y se inscriben
donde el **título** de una clase coincide con un curso existente.

### Importar contenido de cursos
En la sección **Contenido de cursos**, cada formato tiene su propio formulario de carga. Los cursos
nuevos aparecen en el catálogo inmediatamente después de una importación exitosa.

1. **Paquete de curso** — elige un `.tar` / `.zip` producido por la propia Exportación de Sapiqo,
   luego **Importar paquete**. También acepta `.tar.gz` / `.tgz`.
2. **Common Cartridge** — elige un `.imscc` (de Canvas, Blackboard, Moodle o
   Sakai), luego **Importar .imscc**.
3. **Paquete SCORM** — elige un `.zip` (SCORM 1.2 o 2004), luego **Importar SCORM**.
4. **Exportación de LearnDash** — elige un `.json` exportado de un curso de WordPress LearnDash,
   deja **Reflejar imágenes localmente** marcada (recomendado), luego
   **Importar LearnDash**.

### Importar un curso muy grande en partes divididas
Cuando un curso excede el límite de carga del servidor, usa el flujo de trabajo de división en la
página **Gestionar cursos** (`/admin/courses`):

1. Exporta el curso en partes desde su menú **⋯ ▸ Exportar ▸ Dividir (cargas grandes)**,
   y descarga cada archivo de parte.
2. En **Gestionar cursos**, en la barra **Importar partes divididas**, elige todos los archivos
   de partes a la vez y selecciona **Cargar e instalar**. Las partes se cargan una solicitud a la
   vez (para que cada una quede bajo el tope), luego se ensamblan y se instalan.

## Opciones y comportamiento

**Informe de importación CSV.** Después de una importación de usuarios, Sapiqo muestra cuántas filas se
procesaron y enumera:

- **Cuentas creadas** con una **contraseña temporal** de un solo uso por cada usuario nuevo —
  compártelas de forma segura; pide a cada persona que la cambie en su página de Perfil.
- **Inscripciones** realizadas (correo electrónico → curso).
- **Asignaciones a grupos** realizadas.
- **Errores** por número de fila (por ejemplo un correo electrónico inválido o un curso no coincidente).

Las cuentas existentes se emparejan por correo electrónico; los campos de perfil en blanco se completan
sin sobrescribir los valores que la cuenta ya tiene.

**Informe de OneRoster.** Informa recuentos de organizaciones, usuarios creados/actualizados, grupos,
inscripciones y cualquier **clase no coincidente** (títulos de clase que no coincidieron con un
curso). Los roles se asignan a roles/tipos de usuario de Sapiqo (docente, estudiante,
administrador, personal).

**Importación de LearnDash.**
- Las lecciones se agrupan en **módulos por su prefijo numérico** (1.1, 1.2, …); una
  lección "Get Your Badge"/certificado cae en un módulo Finish.
- **Las incrustaciones de Vimeo se conservan** y se reescriben en reproductores adaptables; el enlace
  original de Vimeo se preserva para cada lección.
- **Reflejar imágenes localmente** (marcada por defecto) descarga las imágenes referenciadas al
  curso para que funcione sin solicitudes externas. La obtención de imágenes está
  protegida contra SSRF y limitada, así que algunas imágenes pueden reportarse como fallidas; el recuento
  de imágenes reflejadas vs. fallidas aparece después de la importación.
- Un `.json` de LearnDash **referencia videos en Vimeo, no archivos** — los cursos
  importados incrustan Vimeo en lugar de alojar el video de forma propia.

**Compatibilidad de tipos de archivo (aceptación exacta):**

| Sección | Archivos aceptados |
|---|---|
| Usuarios | `.csv` |
| OneRoster | `.zip`, o un único `.csv` |
| Paquete de curso | `.tar`, `.tar.gz`, `.tgz`, `.zip` |
| Common Cartridge | `.imscc`, `.zip` |
| SCORM | `.zip` |
| LearnDash | `.json` |

## Cómo funciona
Las importaciones de cursos se extraen en un directorio temporal bajo la carpeta de datos (no el temporal del
sistema, que a menudo es demasiado pequeño para video), verifican que el archivo no tenga rutas de archivo
inseguras, localizan el curso y lo copian en el directorio de contenido. Cada importación de
curso exitosa desencadena un reescaneo para que el catálogo se actualice, y se registra en el
registro de auditoría (`course.import`, `course.import_cc`, `course.import_scorm`,
`course.import_learndash`, `course.import_split`). Las importaciones de usuarios se registran como
`users.import` y `users.import_oneroster`.

El análisis de Common Cartridge lee la organización de `imsmanifest.xml` en
módulos/lecciones, extrae el HTML de contenido web como texto de lección, convierte QTI
de opción múltiple en cuestionarios y agrupa los medios referenciados; el informe enumera
lecciones, cuestionarios, medios y cualquier cosa omitida. La importación de SCORM copia el paquete
bajo una carpeta `scorm/`, detecta la versión desde el manifiesto y genera un
reproductor que asigna la finalización/puntuación del paquete de vuelta al progreso de Sapiqo.

## Consejos y trucos
- **Solo el correo electrónico es obligatorio** en el CSV de usuarios — una importación mínima puede ser una única
  columna de correo electrónico.
- **Las contraseñas temporales se muestran una sola vez, en el informe.** Captúralas antes de
  salir de la página.
- **Los cursos/clases no coincidentes se reportan, no se crean** — el curso debe
  existir ya para que ocurra la inscripción. Importa el curso primero.
- **Para alojar de forma propia los videos de LearnDash** en lugar de incrustar Vimeo: coloca los MP4 en
  `content/<slug>/media/videos/`, agrega un mapa `videos.json`, luego Reescanea (ver
  DEPLOYMENT.md).
- **La importación dividida vive en Gestionar cursos**, no en esta página — la pantalla de Importaciones
  te enlaza allí para cursos de gran tamaño.
- Alternativamente, puedes **copiar una carpeta de curso directamente** en el directorio de
  contenido y **Reescanear** — sin límite de carga involucrado.

## Relacionado
- Gestión de cursos (`30-course-management.md`)
- Exportaciones (`32-exports.md`)
- Usuarios y roles (`20-users-and-roles.md`)
- Organizaciones y suscripciones (`21-organizations-and-subscriptions.md`)
- Copias de seguridad (`34-backups.md`)
