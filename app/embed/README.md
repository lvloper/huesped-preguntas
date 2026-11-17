# Formulario embebido en WordPress

Copiar el contenido completo de `formulario.html` una sola vez a un bloque **HTML personalizado** de la página de Eventos Huésped. El editor debe permitir `<script>` y `<style>`; si WordPress los elimina, cargar estos recursos desde el tema o un mecanismo autorizado de snippets, conservando el orden y el contenedor. No se necesita compilar ni instalar dependencias.

Antes de publicarlo, reemplazar las constantes `BACKEND_URL` (origen HTTPS de Laravel, sin `/api/questions`) y `TURNSTILE_SITEKEY` (clave pública). Nunca incluir la clave secreta en WordPress. Registrar el dominio `eventos.huesped.org.ar` en Turnstile. Los placeholders bloquean el envío de manera visible.

El diseño toma el violeta `#672f63`, rojo `#e73c4e` y la tipografía Poppins del sitio de referencia. Hereda Poppins si está cargada; tiene fallback Arial y CSS acotado al contenedor. No modifica el sitio de referencia ni incorpora fuentes o dependencias adicionales.

## Integración mediante iframe

Para mostrar la página pública `/formulario` en WordPress, copiar `iframe.html` completo. Si la página de WordPress recibe `?uid=...`, el script copia ese parámetro al `src` del iframe, conservando los demás parámetros de su URL. El iframe ocupa el ancho disponible y el script ajusta su altura al contenido, incluidos Turnstile, errores y cuenta regresiva. Verifica el origen y la ventana del mensaje antes de ajustar la altura. Si el editor elimina el script, el contenido sigue accesible con desplazamiento dentro del iframe.

El formulario adapta los campos y el botón al ancho de su contenedor. Turnstile usa tamaño compacto cuando el espacio disponible es menor a 300 px, también al girar el teléfono o cambiar el tamaño del iframe.

## Contrato HTTP

El navegador envía `POST BACKEND_URL/api/questions`, con `Content-Type: application/json`, `Accept: application/json`, sin cookies, y estos campos:

```json
{"name":"Nombre","email":"persona@example.org","question":"Pregunta","cf-turnstile-response":"token"}
```

- `201`: pregunta guardada; JSON con `retry_after` numérico en segundos: `0` tras el primer envío y `30` tras el segundo. Solo este estado muestra éxito y vacía la pregunta.
- `429`: pregunta rechazada por frecuencia; JSON con `retry_after` numérico en segundos. Conserva los campos y muestra cuenta regresiva visible junto al botón, también en móvil.
- `422`: validación rechazada; admite el objeto `errors` de Laravel (arrays de mensajes). Conserva los campos.
- Otros estados, JSON inválido o problemas de conexión: no muestran éxito. Una interrupción puede ocurrir después de que el servidor guardó la pregunta, por lo que se informa que no se pudo confirmar el envío.

El backend debe devolver siempre `retry_after` válido en `201` y `429`; la interfaz no inventa una espera cuando falta. Configurar CORS para el origen exacto `https://eventos.huesped.org.ar`, métodos POST/OPTIONS y cabeceras Content-Type/Accept. CORS no autentica al visitante ni impide todas las solicitudes externas.

## Verificación y decisiones pendientes

Turnstile se carga y renderiza explícitamente al comenzar a escribir la pregunta, con acción `question`. Guarda el ID y reinicia el widget después de cada intento HTTP, incluidos errores, ya que cada token es de un solo uso. Maneja expiración, timeout, fallo de carga y reintento. El backend debe validar Siteverify, acción `question` y hostname autorizado antes de guardar; la clave secreta queda en Laravel. Referencia: https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/.

Nombre y email son datos declarados, sin autenticación. El snippet precarga parámetros de enlace según el contrato siguiente, sin autenticar a la persona. Se permiten dos preguntas consecutivas por IP; después del segundo envío aceptado comienza una espera de 30 segundos. Un primer envío aislado deja de contar después de 30 segundos. El servidor debe aplicar el límite; la cuenta regresiva es solo una indicación de su respuesta.

## Verificación de integración pendiente

Con URL y claves reales, probar desde WordPress: aceptación 201, rechazo 429 con espera, validación 422, token expirado/inválido/reutilizado, caída de red, y vista móvil a 320 px. Verificar que se guardan los dos primeros envíos, que el tercero se rechaza durante la espera y que al cumplirse los 30 segundos se permiten dos envíos nuevos. Las pruebas locales con mocks no demuestran la configuración real de Turnstile, CORS ni la política de identidad.

## Enlaces personales por uid

En el panel privado, abrir **Usuarios** (`/panel/participantes`). La lista muestra ID, usuario, mail y enlace personal. Se puede cargar una persona o importar un CSV UTF-8 con columnas `id,user,mail` (también se admiten `uid`, `nombre` o `name`, y `email` como alias de esas columnas, separador punto y coma y BOM). El ID se carga manualmente, admite hasta 40 letras, números, guiones o guiones bajos y conserva los ceros iniciales. El archivo se valida completo antes de guardar; IDs o mails repetidos dentro del archivo se rechazan. Volver a importar un ID actualiza su usuario y mail. Si el mail pertenece a otro ID, se rechaza toda la importación. Se pueden descargar los datos y enlaces desde el botón de exportación.

El ID cargado se guarda en `participants.uid` y se utiliza literalmente como `uid` en el enlace; por ejemplo, el ID `000123` produce `?uid=000123`. La columna interna `participants.id` se mantiene para las relaciones de la base de datos. Los participantes se guardan en `participants`, separados de las cuentas que acceden al panel. Antes de usar esta función, aplicar las migraciones de participantes y de `questions.participant_id`.

- Página contenedora: `https://eventos.huesped.org.ar/aging-2026/?uid=UID_PERSONAL`.
- El script de `iframe.html` construye: `https://aging.huesped.org.ar/formulario?uid=UID_PERSONAL`.
- Tanto la página pública como el HTML directo consultan `POST /api/participants/resolve` con `{"uid":"..."}`. La respuesta contiene únicamente nombre y email, sin caché.
- Con uid, nombre y email se completan automáticamente y quedan de solo lectura. El envío incluye el uid y la pregunta; Laravel vuelve a buscar al participante y usa los datos registrados, aunque se altere el HTML o el payload.
- Un uid inválido, repetido, desconocido o una falla de carga muestran un mensaje y bloquean el envío. Las fallas de carga permiten reintentar.
- Sin uid, se mantienen los campos manuales. El límite de dos preguntas y posterior espera de 30 segundos sigue aplicado por IP.

El enlace personal permite recuperar los datos de esa persona; compartirlo permite usar ese mismo enlace. No inicia sesión ni habilita acceso al panel.

## Parámetros de precarga manual

Sin uid, siguen admitidos los parámetros anteriores, sin autologin:

- `aging_nombre`: nombre, máximo 150 caracteres.
- `aging_email`: email, máximo 255 caracteres y formato válido.

Ejemplo con datos ficticios: `https://eventos.huesped.org.ar/aging-2026/?aging_nombre=Ana%20P%C3%A9rez&aging_email=ana%40example.com`.

Quien prepare los enlaces puede construir la query con `URLSearchParams`; no hay emisor privilegiado ni servicio nuevo de generación. Los campos permanecen visibles y editables. Parámetros ausentes, vacíos, repetidos, demasiado largos o inválidos se ignoran por campo y se completan manualmente. Los valores se asignan mediante `.value`, nunca como HTML. La validación de Laravel sigue siendo obligatoria. El formulario no escribe estos datos en almacenamiento del navegador.

Los parámetros son datos declarados, no prueba de identidad, autorización ni clave del rate limit. No incluir secretos en enlaces. Nombre y email en una URL pueden quedar en historial y registros del sitio; el enlace general sin parámetros sigue funcionando y evita esa exposición.

## Prueba local lista

Con Laravel iniciado en `127.0.0.1:8001`, abrir `http://127.0.0.1:8001/prueba-formulario`. Sirve `prueba-local.html` solo cuando APP_ENV es local. No abrirlo como file://: usar esa URL. Incluye las claves públicas dummy de Cloudflare; Siteverify se consulta realmente, pero no demuestra protección antibot. El backend admite la respuesta especial (hostname example.com, sin action) exclusivamente con la clave dummy exacta y entorno local; fuera de local esa clave se rechaza. El snippet WordPress original conserva placeholders de producción.

El servidor local queda ligado a loopback. La cuenta de prueba es solo local y no debe copiarse a producción. Se creó una pregunta ficticia para comprobar recepción.
