# 2. Formulario embebido

## Ubicación y presentación

- Se incorpora como HTML en la página WordPress de Eventos Huésped.
- El navegador envía la pregunta directamente a la aplicación Laravel.
- Tomar como referencia visual el sitio de Eventos Huésped, sin modificarlo durante la preparación del proyecto.
- Mantener una interfaz liviana y usable en celular.

## Datos

Cada pregunta se relaciona con un nombre y un mail.

- Con un enlace `?uid=...`, cargar nombre y email desde la tabla de participantes precargados y mostrarlos de solo lectura.
- Si no está identificada la persona, mostrar los campos nombre y mail, además de la pregunta.
- La sección Usuarios del panel permite cargar el ID, usuario y mail manualmente o mediante CSV. El ID cargado se utiliza como uid del enlace. El uid identifica el registro y no permite acceder al panel privado.

## Flujo

1. La persona accede con un uid válido y se cargan sus datos; el iframe recibe el uid desde la página contenedora. Sin uid, completa nombre y mail manualmente.
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

El límite sigue aplicándose por IP: personas que comparten una red comparten la espera. El uid permite recuperar los datos del participante, pero no inicia una sesión de usuario ni habilita el panel privado.
