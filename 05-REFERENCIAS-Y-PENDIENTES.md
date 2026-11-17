# 5. Referencias y puntos por resolver

## Referencias

- Sitio y estilo: https://eventos.huesped.org.ar/aging-2026/
- Cadena «Aging - web eventos»: https://mail.google.com/mail/u/0/#all/1a1b1344859fa29c
- Confirmación del alcance de comentarios y compromiso de desarrollo: https://mail.google.com/mail/u/0/#all/1a1d14ac27c04275
- [Wireframe corregido del panel](referencias/panel-preguntas-wireframe.png).
- Limitaciones de CORS: https://developer.mozilla.org/en-US/docs/Web/HTTP/Guides/CORS
- Cloudflare Turnstile: https://developers.cloudflare.com/turnstile/get-started/

La cadena aporta contexto. No ampliar el proyecto para cubrir otros temas mencionados en ella. El wireframe anterior con una única vista de detalle a la derecha quedó reemplazado por el de dos columnas incluido aquí.

## Decisiones resueltas y configuración necesaria

1. **Enlace personal por uid:** la sección Usuarios permite cargar un ID manual con usuario y mail, individualmente o por CSV (`id,user,mail`), y utiliza ese ID como uid. `?uid=...` carga nombre y email desde esa tabla; el iframe copia el uid de la página de WordPress. El servidor resuelve nuevamente los datos al guardar. Sin uid se conserva la carga manual y la precarga opcional anterior. No otorga acceso al panel. Contrato en `app/embed/README.md`.
2. **Rate limit resuelto:** una pregunta cada 30 segundos por IP, confirmado por Luciano. No equivale a identidad verificada.
3. **Configuración de integración:** faltan la URL del backend y la configuración de Turnstile para el entorno final. No inventar valores ni incluir secretos en estos documentos o en el formulario.

El starter kit concreto y otros detalles internos mínimos corresponden al desarrollo, no son decisiones tomadas en esta documentación. No convertirlos en nuevas funciones ni introducir dependencias grandes sin necesidad.

No bloquear trabajo independiente de estos puntos. Consultar a Luciano antes de cerrar una decisión que cambie el acceso, la identidad o el alcance.

## Requisitos decididos (sin cambios)

Laravel con starter kit; formulario HTML con fallback manual; Turnstile en servidor; frecuencia de 30 segundos; recibidas paginadas y seleccionadas en dos columnas; transición de 3 segundos con Deshacer; consulta de respondidas; polling configurable de 5 segundos. Sin Filament, IA, WebSockets ni despliegue autorizado.

## Estado actualizado

Aplicación en `app/`, Breeze Blade sin registro público. Se permiten dos preguntas consecutivas **por IP** y una espera de 30 segundos desde la segunda, mediante RateLimiter de Laravel y bloqueo atómico. No es identidad autenticada: personas que comparten IP comparten espera. Los enlaces con uid recuperan datos de participantes precargados; no hay inicio de sesión para participantes.

Backend local por ahora; destino futuro dentro de `huesped.org` sin hostname definitivo. Formulario en `https://eventos.huesped.org.ar/`. Faltan claves Turnstile, integración real, prueba móvil y concurrencia. Al desplegar se revisarán proxies confiables para obtener la IP correcta sin confiar en cabeceras arbitrarias. No se publicaron cambios ni se crearon accesos.
