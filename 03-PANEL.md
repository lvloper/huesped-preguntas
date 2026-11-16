# 3. Panel de preguntas

## Acceso y objetivo

Panel privado con inicio de sesión mediante un starter kit de Laravel. Debe facilitar a la moderadora leer y elegir preguntas aunque lleguen muchas juntas. No se requiere Filament.

## Distribución acordada

### Izquierda: preguntas recibidas

- Mostrar las preguntas con nombre y mail de quien las envió.
- Lista con paginación.
- Permitir tildarlas como favoritas/seleccionadas.
- Esa selección alimenta la columna derecha: no son dos listas de favoritos distintas.

### Derecha: preguntas seleccionadas

- Mostrar las seleccionadas una debajo de otra, listas para leer.
- No reemplazar esta columna por una vista de detalle de una única pregunta.
- Cada pregunta puede marcarse como respondida.

### Al marcar como respondida

1. La tarjeta se muestra atenuada y pasa arriba.
2. Permanece en ese estado durante **3 segundos**.
3. Durante ese tiempo se ofrece **Deshacer**, para volver a marcarla como no respondida si hubo un error.
4. Pasados los 3 segundos sin deshacer, deja de ocupar la lista activa de seleccionadas y sigue disponible entre las respondidas. No se elimina la pregunta.

Debe haber un botón **Ver preguntas respondidas**.

## Actualización

Consultar novedades mediante polling cada **5 segundos**, con intervalo configurable. La actualización no debe interrumpir las acciones de selección, la paginación ni los 3 segundos disponibles para deshacer.

## Referencia

[Wireframe de dos columnas](referencias/panel-preguntas-wireframe.png).

La imagen ilustra la distribución y los estados. Sus datos son ficticios; no es una implementación, una guía exacta de marca ni una prueba de rendimiento. Usar el sitio de Eventos Huésped como referencia de estilo y este documento como fuente del comportamiento.
