# Cuestionarios y evaluaciones

**Audiencia:** estudiante
**Dónde:** Dentro de una lección en el lector de cursos (/learn/<slug>) — mostrado
como una **Verificación de conocimientos**

## Qué es
Una verificación de conocimientos es un cuestionario corto adjunto a una lección.
Confirma que entendiste el material antes de que la lección cuente como totalmente
completada. Las preguntas pueden ser de opción única, de opción múltiple ("elige
todas las que apliquen") o de respuesta corta. Cada cuestionario tiene una
calificación aprobatoria (70 % de forma predeterminada), y aprobar hace que el
cuestionario de esa lección cuente hacia la finalización del curso y tu insignia.

La calificación ocurre en el servidor, y las respuestas correctas nunca se envían a
tu navegador — de modo que puedes confiar en el resultado y no puedes espiar la
clave.

## Cómo usarlo
1. Desplázate hasta la sección **Verificación de conocimientos** al final de una
   lección.
2. Lee el requisito de aprobación que se muestra bajo el título, por ejemplo
   "Necesitas 70 % para aprobar" (también indica cuántas preguntas y cuántos intentos
   están permitidos, cuando hay límite).
3. Responde cada pregunta:
   - **Opción única** — selecciona una opción.
   - **Elige todas las que apliquen** — marca cada opción correcta.
   - **Respuesta corta** — escribe tu respuesta en el cuadro de texto.
4. Elige **Enviar respuestas**. Primero debes responder cada pregunta, o se te
   pedirá que las completes.
5. Lee tu resultado: tu calificación, porcentaje y retroalimentación por pregunta
   (✓ Correcto o ✗ No del todo, más cualquier nota que el autor haya agregado).
6. Si aprobaste, la verificación se marca como completada. Si no, revisa la
   retroalimentación y elige **Repetir cuestionario** para intentarlo de nuevo.

## Opciones y comportamiento
- **Calificación aprobatoria** — se muestra como "Necesitas X % para aprobar" (70 %
  de forma predeterminada). Apruebas cuando tu porcentaje es igual o superior a ella.
- **Tipos de pregunta** — opción única, opción múltiple (elige todas las que
  apliquen) y respuesta corta.
- **Intentos** — si el autor estableció un límite, se muestra ("N intentos
  permitidos") y tu resultado muestra "Intento X de N". Una vez que hayas usado todos
  los intentos sin aprobar, al enviar aparece "No quedan intentos". Una vez que hayas
  aprobado, las repeticiones adicionales no son bloqueadas por el límite.
- **Banco de preguntas / elegir N** — algunos cuestionarios presentan un subconjunto
  aleatorio de sus preguntas en cada intento; la línea de meta muestra cuántas
  preguntas recibirás.
- **Opciones mezcladas** — algunos cuestionarios aleatorizan el orden de las opciones
  de respuesta.
- **Retroalimentación por pregunta** — después de calificar, cada pregunta se marca
  como correcta o incorrecta con cualquier explicación proporcionada por el autor.
- **Repetir cuestionario** — aparece después de un primer envío (y después de
  aprobar) para que puedas intentarlo de nuevo, sujeto a cualquier límite de
  intentos.

## Cómo funciona
Cuando envías, el lector manda tus respuestas al servidor, que las califica contra la
clave de respuestas almacenada en el servidor y devuelve tu calificación,
aprobado/reprobado y retroalimentación por pregunta. Las marcas de respuesta correcta
y las respuestas cortas aceptadas se eliminan de los datos del curso antes de que
lleguen a tu navegador, de modo que la calificación es autoritativa del lado del
servidor.

Las respuestas cortas se comparan con tolerancia: se ignoran mayúsculas/minúsculas,
espacios circundantes, espacios internos adicionales y puntuación al final. Las
preguntas de opción múltiple requieren que tu conjunto seleccionado coincida
exactamente con el conjunto correcto.

Cada envío se registra e incrementa tu conteo de intentos. Aprobar una verificación
de conocimientos marca el paso de ese cuestionario como completado, lo que cuenta
hacia tu porcentaje del curso — y si te lleva al 100 %, emite tu insignia al
instante. Una repetición fallida posterior no descompleta una verificación que ya
hayas aprobado.

## Consejos y detalles
- Debes responder cada pregunta mostrada antes de poder enviar.
- Para "elige todas las que apliquen", las selecciones parciales se marcan como
  incorrectas — selecciona cada opción correcta.
- Si los intentos son limitados, úsalos con criterio; el conteo se muestra con tu
  resultado.
- Si obtienes un subconjunto aleatorio (elegir N), repetir puede presentar preguntas
  diferentes.
- Aprobar es lo que hace que una lección con cuestionario cuente como totalmente
  completada — marcar la lección como completada por sí solo no es suficiente.

## Relacionado
- [Tomar un curso](02-taking-a-course.md)
- [Insignias, certificados y tu expediente](05-badges-certificates-transcript.md)
