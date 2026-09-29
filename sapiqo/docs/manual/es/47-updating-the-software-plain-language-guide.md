# Actualizar el software — una guía paso a paso para administradores

**Para quién es esto:** cualquier administrador que necesite instalar una actualización de software,
aunque no te consideres una persona "técnica". No se da por sentado ningún
conocimiento especial — cada paso se explica con todo detalle.

**La versión corta:** actualizar agrega nuevas funciones y correcciones al software del LMS
en sí. **No** toca tus usuarios, cursos, insignias,
certificados, configuración ni nada más que ya hayas creado. Piénsalo
como actualizar una app en tu teléfono — la app cambia, tus fotos y archivos
dentro de ella no.

*(¿Buscas la referencia técnica en su lugar — nombres de funciones, ubicaciones de
archivos, cómo funcionan las comprobaciones de seguridad por dentro? Consulta
[Actualizaciones de software](44-software-updates.md) para eso. Esta página es el recorrido
en lenguaje sencillo; esa otra es el detalle de ingeniería).*

---

## Las dos cosas que se mantienen separadas

Antes de los pasos, ayuda saber que el LMS está construido a partir de **dos piezas
separadas**, mantenidas aparte a propósito:

1. **El software en sí** — el programa que hace funcionar el sitio web: páginas,
   botones, funciones. Esto es lo que cambia una actualización.
2. **Todo lo que has puesto en él** — tu base de datos de cuentas, cada
   curso, cada insignia y certificado que se haya ganado, tu personalización de marca, tu
   configuración, tus archivos subidos. Esto se llama **datos**, y vive en un
   lugar completamente separado del software.

Una actualización **solo reemplaza la pieza n.º 1.** La pieza n.º 2 nunca la toca el
proceso de actualización — vive físicamente en un lugar que la actualización no puede alcanzar, ni siquiera
si algo saliera mal. Ese es todo el diseño, y es la razón por la que actualizar es
seguro de hacer por tu cuenta sin ayuda de un desarrollador.

---

## Antes de empezar

Necesitarás un **archivo de actualización** — tendrá un nombre que termina en `.tar.gz`
(piénsalo como un archivo `.zip`: un solo archivo que contiene un paquete de
otros archivos). Vas a:

- recibir este archivo de quien mantiene el software para tu
  organización, **o**
- construir uno tú mismo a partir de una copia de trabajo del software, si eres quien lo
  mantiene (consulta "Construir tu propio archivo de actualización", cerca del final).

Guarda el archivo en un lugar fácil de encontrar, como tu Escritorio o la carpeta de Descargas.
No necesitas abrirlo, descomprimirlo ni hacer nada más con él — lo subirás
exactamente como lo recibiste.

---

## Paso a paso: aplicar una actualización

**1. Inicia sesión como administrador** y haz clic en **Admin** en el menú superior.

**2. Haz clic en "⚙️ Settings & integrations."** Esto abre una página de categorías de
configuración, mostradas como tarjetas.

**3. Haz clic en "⬆️ Software updates."** Esto abre la página de actualización. Cerca de la parte superior,
muestra tu número de versión actual (p. ej. "Version 1.12.0") — no necesitas
saber qué significa, es solo una etiqueta que el software comprueba automáticamente.

**4. Encuentra la tarjeta titulada "Apply an update."** Haz clic en el campo de archivo
(etiquetado **Update package**) y elige el archivo `.tar.gz` que guardaste antes.

**5. Deja la casilla de abajo sin marcar**, en casi todos los casos. (Dice
*"Apply anyway, even if it isn't newer."* Solo la marcarías si
alguien te indicara específicamente reinstalar exactamente la misma versión o volver
a una más antigua — consulta la nota en "Bueno saberlo", más abajo).

**6. Haz clic en el botón dorado "Upload & apply update".**

**7. Aparecerá un cuadro de confirmación**, pidiéndote que confirmes. Mencionará
que el software actual se respaldará primero — esa es la red de seguridad
integrada. Haz clic en **OK** para continuar.

**8. Espera unos segundos.** La página se recargará y te mostrará un mensaje que te dice
que funcionó — algo como *"Updated from 1.12.0 to 1.13.0. A backup of
the previous code was saved."* Si ves ese mensaje, la actualización está
instalada.

**9. Vuelve a cargar la página una vez más** (refresca tu navegador). Esto le da al
software la oportunidad de terminar cualquier pequeño ajuste entre bastidores que la actualización
necesite — no verás que pase nada, es solo una buena práctica.

**Eso es todo.** Tus usuarios, sus cuentas, su progreso, sus insignias,
tus cursos y toda tu configuración están exactamente como estaban antes.

---

## Si algo se ve mal después

Esto es raro, pero aquí tienes exactamente qué hacer si una página se ve rota o una
función deja de funcionar bien justo después de una actualización:

**1. Vuelve a Admin → Settings & integrations → Software updates.**

**2. Desplázate hacia abajo hasta la tarjeta titulada "Backups & rollback."** Verás una lista
de versiones anteriores del software, guardadas automáticamente — la más reciente
está etiquetada como **"most recent."**

**3. Haz clic en "Roll back"** junto a esa copia de seguridad más reciente.

**4. Confirma el cuadro emergente.** Esto deja el software exactamente como
estaba justo antes de la actualización — de nuevo, sin tocar ninguno de tus usuarios,
cursos o datos.

**5. Vuelve a cargar la página.** Estás de vuelta a la normalidad, y puedes intentar la actualización de nuevo
más tarde o contactar a quien mantiene el software para ti.

---

## Bueno saberlo

- **No puedes perder nada por accidente.** La actualización solo agrega o
  reemplaza los archivos propios del software — está hecha para que literalmente no pueda
  alcanzar tus cuentas, cursos, insignias o configuración, ni siquiera por error.
- **Se hace una copia de seguridad automáticamente, cada sola vez**, antes de que se cambie
  algo — nunca tienes que acordarte de hacerlo tú mismo.
- **La casilla que normalmente dejas sin marcar** ("Apply anyway…") existe para
  una situación específica: si el software cree que el archivo que subiste no es
  realmente más nuevo que el que ya tienes (por ejemplo, si estás
  reinstalando intencionalmente o volviendo a una versión más antigua a propósito).
  Si subes un archivo de actualización normal y más nuevo, no necesitarás tocarla.
- **Si el botón "Upload & apply update" está atenuado**, la carpeta del software
  en tu servidor no es escribible — este es un problema técnico de configuración de una sola
  vez para quien administra tu servidor, no algo malo con el archivo de
  actualización. Contáctalo, o consulta la página de referencia técnica para la corrección exacta de
  permisos.
- **La mayoría de las veces nada cambia visualmente.** Muchas actualizaciones son correcciones entre
  bastidores y pequeñas mejoras — no te preocupes si el sitio se ve idéntico
  después. El número de versión en la parte superior de la página de actualización habrá
  cambiado, lo que confirma que funcionó.

---

## Construir tu propio archivo de actualización

*(Omite esta sección si otra persona siempre te entrega el archivo de actualización — la mayoría
de los administradores nunca necesitan hacer esta parte por sí mismos).*

Si *tú eres* la persona responsable de mantener el software y has
hecho cambios en una copia de trabajo de él, puedes construir tu propio archivo de actualización
de una de dos maneras:

- **Desde dentro del sitio web:** ve a Admin → Settings & integrations →
  Software updates, encuentra la tarjeta titulada **"Create an update package,"** y
  haz clic en **"⬇ Download update package."** Esto crea un archivo `.tar.gz` construido
  a partir de exactamente lo que se está ejecutando ahora — entrega ese archivo a cualquier otra instalación
  para llevarla a la misma versión.
- **Desde una configuración técnica**, alguien cómodo con una línea de comandos puede ejecutar
  un solo comando (`php bin/build-update.php`) para hacer lo mismo — consulta
  la página de referencia técnica para los detalles.

---

## Páginas relacionadas

- [Actualizaciones de software](44-software-updates.md) — la referencia técnica: qué hay
  dentro de un archivo de actualización, exactamente cómo funcionan las comprobaciones de seguridad y las ubicaciones de
  archivos.
- [Copias de seguridad](34-backups.md) — una instantánea separada y completa de tus datos (no solo
  del software) para recuperación ante desastres.
