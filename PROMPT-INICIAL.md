# Prompt inicial

Leé primero `aging-especificaciones/00-INDICE.md` y todos los Markdown que enlaza. Si esta carpeta está en otra ubicación, ajustá solamente esa ruta. Mirá también `aging-especificaciones/referencias/panel-preguntas-wireframe.png` como referencia de distribución.

Vamos a desarrollar el formulario de preguntas y el panel privado para Aging de Fundación Huésped. Tomá estos archivos como el alcance acordado. No arrastres requisitos de Masterclasses Amnistía ni agregues funciones por iniciativa propia.

Inspeccioná primero el proyecto disponible para no sobrescribir trabajo existente. Luego iniciá la implementación de lo definido: Laravel con starter kit para el acceso al panel, formulario HTML embebible en WordPress, persistencia de preguntas, Turnstile validado en servidor, una pregunta cada 30 segundos, panel de dos columnas y polling de 5 segundos configurable. No usar Filament ni agregar IA o WebSockets.

Respetá especialmente la interacción del panel: recibidas paginadas y seleccionables a la izquierda; seleccionadas a la derecha; al marcar respondida, tarjeta atenuada arriba durante 3 segundos con Deshacer; botón para consultar respondidas.

Usá el estilo de Eventos Huésped como referencia, sin modificar el sitio público ni desplegar allí por tu cuenta. El wireframe no fija colores exactos, datos reales ni requisitos adicionales. Las especificaciones escritas prevalecen.

Separá los requisitos ya decididos de los puntos abiertos en `05-REFERENCIAS-Y-PENDIENTES.md`. No inventes cómo funciona la identificación por enlace ni la clave de identidad del rate limit. Avanzá con lo que no dependa de esas decisiones y consultame cuando una definición sea necesaria. No presentes nombre y mail escritos como identidad autenticada, ni CORS como garantía de origen.

Mantené la solución liviana. Elegí únicamente los detalles internos necesarios para implementar lo acordado, sin ampliar el alcance. No crees ni envíes accesos a terceros, no publiques y no hagas cambios en producción sin mi aprobación.

Verificá los criterios de `04-SEGURIDAD-Y-VERIFICACIONES.md`. Al terminar, indicá qué implementaste, cómo probarlo, qué comprobaciones ejecutaste y qué puntos siguen pendientes. No afirmes que algo está probado si no lo ejecutaste.
