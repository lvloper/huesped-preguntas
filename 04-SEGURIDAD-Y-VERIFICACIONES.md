# 4. Seguridad, actualización y verificaciones

## Controles acordados

- Formulario en `https://eventos.huesped.org.ar`, con POST desde el navegador a Laravel.
- Configurar CORS para permitir ese origen.
- Configurar Cloudflare Turnstile para el dominio del formulario y validar su resultado en Laravel antes de aceptar la pregunta.
- La clave secreta de Turnstile permanece en el servidor, nunca dentro del HTML o JavaScript embebido.
- Permitir dos preguntas consecutivas y aplicar una espera de 30 segundos desde la segunda en el servidor; reflejar la espera en la interfaz.
- Proteger el panel de preguntas con inicio de sesión.

**Límite conocido:** CORS no autentica el origen de todas las solicitudes ni garantiza que nadie envíe desde fuera de esa página. El usuario aceptó el enfoque de CORS, Turnstile y rate limit para esta campaña breve, con esa limitación explícita.

No restringir los envíos a la IP del servidor WordPress: en el flujo elegido envía el navegador del visitante. Tampoco agregar un secreto al HTML como supuesto control de acceso.

## Ligereza

- El sistema debe ser liviano y contemplar envíos simultáneos.
- Panel con paginación de recibidas y polling de 5 segundos configurable.
- No se definió una cifra de concurrencia ni una infraestructura. No inventar una garantía de capacidad.
- La imagen del wireframe no demuestra rendimiento: verificarlo durante el desarrollo e informar las condiciones de la prueba.

## Lista de comprobación para el desarrollo

- El formulario se integra en WordPress y envía hacia Laravel.
- Nombre, mail y pregunta quedan asociados y guardados cuando el envío se acepta.
- El fallback manual no se presenta como autenticación verificada.
- Turnstile aparece al escribir y se verifica en servidor; un resultado inválido no permite aceptar la pregunta.
- Los dos primeros envíos se aceptan; un tercero durante los 30 segundos posteriores al segundo se rechaza en servidor y muestra la espera restante.
- La espera se entiende y se ve en móvil.
- Sin login no se puede acceder al panel ni leer sus datos privados.
- Seleccionar una recibida la incorpora a la columna derecha.
- Las recibidas tienen paginación; el polling no cambia inesperadamente la página consultada.
- Marcar respondida muestra el estado atenuado arriba y permite deshacer durante 3 segundos.
- Las respondidas pueden consultarse con su botón, sin perder el registro.
- El polling actualiza sin interrumpir la interacción.
- No afirmar que CORS impide todas las solicitudes externas.

Estas son comprobaciones pendientes para quien implemente; no pruebas ya ejecutadas.
