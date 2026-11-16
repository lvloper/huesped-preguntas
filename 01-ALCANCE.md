# 1. Alcance y contexto

## Objetivo

Recibir preguntas de quienes siguen el evento Aging de Fundación Huésped y permitir que una moderadora las lea, seleccione y marque como respondidas desde un panel privado sencillo.

La página del evento ya existe en WordPress y contiene el streaming. El trabajo es agregar el formulario de preguntas y su panel, no reconstruir la landing ni la transmisión.

## Componentes acordados

- Formulario HTML embebible en la página de WordPress, con nombre, mail y pregunta.
- Envío directo desde el navegador del visitante hacia el backend Laravel mediante POST.
- Persistencia de las preguntas en una base de datos.
- Laravel con starter kit para el inicio de sesión del panel privado. El kit concreto no está prescrito.
- Panel liviano para leer, seleccionar y gestionar las preguntas respondidas.
- Cloudflare Turnstile, límite de envíos y actualización periódica del panel.

En estos documentos, **backend** designa el panel privado de preguntas y la aplicación Laravel que lo sostiene. El login de ese panel no es un login general de asistentes al evento.

## Límites

- Sin Filament: se descartó para mantener el proyecto acotado.
- Sin funciones de IA.
- Sin WebSockets: se acordó polling.
- Sin rehacer WordPress, streaming, certificados ni el acceso general al evento.
- Sin elegir o iniciar un arnés de programación por Luciano.
- Sin sumar buscadores, filtros, votaciones, chat público, exportaciones u otras funciones no acordadas.
- La configuración de caché se gestiona por separado; no se define infraestructura de caché aquí.

## Contexto operativo

En la cadena «Aging - web eventos», Luciano ofreció un campo de preguntas junto al streaming y un panel privado. Florencia aceptó probarlo y señaló a Débora Fiore como moderadora. Luciano indicó entrega del desarrollo para el 18 de noviembre; el evento es el 20 de noviembre de 2026.

Estas fechas son contexto de planificación, no evidencia de que el desarrollo esté realizado. No enviar accesos ni comunicaciones a terceros automáticamente.
