# Preguntas Aging

Implementación local Laravel 13 + Breeze Blade. Especificaciones acordadas en la carpeta superior.

## Prueba local

Desde `app/`: `composer install`, copiar `.env.example` a `.env` si no existe, `php artisan key:generate`, `php artisan migrate`, `npm ci`, `npm run build`, `php artisan serve`. La raíz dirige al panel y este requiere login. No se crean usuarios automáticamente. Las pruebas crean usuarios efímeros en SQLite en memoria.

Ejecutar `php artisan test --compact`. Login utiliza Breeze; registro y funcionalidades de perfil no están expuestas.

El snippet WordPress está en `embed/formulario.html`; instrucciones en `embed/README.md`. No insertar en producción todavía. Configurar secreto solo en servidor. CORS permite el origen acordado, pero no autentica solicitudes.

`AGING_POLL_MS` configura polling (5000 por defecto). Recibidas ordenadas por ID ascendente para mantener páginas estables ante nuevos envíos. Marcar respondida inicia una ventana local de 3 segundos; Deshacer cancela la escritura. Al vencer se persiste, con recuperación visible ante error.

## Configuración pendiente

RateLimiter de Laravel permite dos preguntas consecutivas por IP y aplica una espera de 30 segundos desde la segunda, con bloqueo atómico. La IP no autentica a la persona. El formulario admite precarga editable con `aging_nombre` y `aging_email`; no autentica. Ver contrato en `embed/README.md`. Falta configurar Turnstile; sin secreto la API devuelve 503. Al desplegar se configurarán los proxies confiables según la infraestructura real. Backend local por ahora; futuro destino dentro de huesped.org.

## Comprobaciones ejecutadas

Suite PHP: 22 pruebas, 73 aserciones exitosas (SQLite en memoria, HTTP Turnstile simulado). `npm run build`, `php artisan view:cache`, sintaxis de panel.js y Pint exitosos. El primer intento de Pint con `--dirty` no aplica porque esta carpeta no tiene repositorio Git; se ejecutó luego sobre el proyecto. El formulario tuvo comprobaciones Node con DOM/fetch/Turnstile simulados para render diferido, payload, 201, 429 y reset. Esto no demuestra integración real ni capacidad concurrente.

## Acceso al panel

Única cuenta autorizada: `debora.fiore@huesped.org.ar`. Se verifica tanto en login como en cada ruta privada, incluidas lecturas y modificaciones. La cuenta local anterior `pruebas@aging.test` ya no puede acceder. No se crearon ni enviaron credenciales para Débora.
