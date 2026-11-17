# 2. Formulario embebido

## Ubicación y presentación

- Se incorpora como HTML en la página WordPress de Eventos Huésped.
- El navegador envía la pregunta directamente a la aplicación Laravel.
- Tomar como referencia visual el sitio de Eventos Huésped, sin modificarlo durante la preparación del proyecto.
- Mantener una interfaz liviana y usable en celular.

## Datos

Cada pregunta se relaciona con un nombre y un mail.

- Si se resuelve la identificación mediante enlace, aprovechar esa identificación.
- Si no está identificada la persona, mostrar los campos nombre y mail, además de la pregunta.
- El mecanismo concreto del enlace sigue por resolver. Completar nombre y mail no equivale a verificar la identidad ni a autenticar al visitante.

## Flujo

1. La persona accede al formulario y queda identificada mediante el enlace, si ese mecanismo está disponible; de lo contrario completa nombre y mail.
2. Escribe su pregunta.
3. Mostrar Cloudflare Turnstile recién cuando empieza a escribir la pregunta.
4. Al enviar, validar en el servidor los datos, Turnstile y el límite de frecuencia antes de aceptar y guardar la pregunta.
5. Una pregunta aceptada queda disponible para el panel privado.
6. Después del segundo envío aceptado, bloquear el botón durante 30 segundos e informar cuánto falta para volver a preguntar.

## Límite y respuesta de la interfaz

- Permitir **dos preguntas consecutivas**; después de la segunda, esperar **30 segundos**. Un primer envío aislado deja de contar a los 30 segundos.
- El servidor aplica el límite; deshabilitar el botón por sí solo no alcanza.
- Mostrar el tiempo restante junto al botón, visible también en celulares. No depender solamente de un mensaje al pasar el mouse.
- Habilitar el envío nuevamente cuando corresponda según el límite del servidor.
- Si el servidor rechaza el envío por el límite o por validación, la interfaz no debe mostrarlo como enviado correctamente.

La forma de reconocer a la misma persona para este límite depende de la identificación pendiente. No elegir silenciosamente el mail escrito, la IP u otro dato como identidad verificada.
