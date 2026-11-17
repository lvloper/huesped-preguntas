# Operación en CloudPanel

Aplicación: **https://aging.huesped.org.ar/login**. Estado protegido: **https://aging.huesped.org.ar/api/status**.
Ambiente production; repositorio público `https://github.com/lvloper/huesped-preguntas.git`, rama `main`.

## Acceso y comandos

```bash
ssh -p 22 huesped-aging@20.220.171.156
~/htdocs/aging.huesped.org.ar/deploy          # actualizar main
~/htdocs/aging.huesped.org.ar/deploy plan     # consultar tareas
~/htdocs/aging.huesped.org.ar/deploy rollback # volver al código anterior
```

El rollback no restaura la base, .env ni uploads. No se ejecutó un rollback real de producción para ensayarlo. Si se vuelve a la primera release, ese código no tiene el endpoint de estado nuevo. El wrapper y la receta están fuera de current, con exclusión de ejecuciones simultáneas y retención de tres releases.

Para conocer la versión activa:

```bash
site=~/htdocs/aging.huesped.org.ar
readlink -f "$site/current"
cat "$site/current/REVISION"
```

## Estado por URL

La clave está en `STATUS_CHECK_KEY`, dentro de `/home/huesped-aging/htdocs/aging.huesped.org.ar/shared/.env` (permisos 600). Es independiente de APP_KEY y de las contraseñas SSH y del panel. No se guarda su valor en Git, esta guía ni la respuesta HTTP.

El consumidor externo debe enviar `Authorization: Bearer CLAVE`. Ejemplo si ya tiene la variable en su entorno:

```bash
curl --fail-with-body --silent --show-error \
  --header "Authorization: Bearer ${STATUS_CHECK_KEY}" \
  https://aging.huesped.org.ar/api/status
```

Respuesta:

```json
{"status":"ok","checks":{"application":"ok","database":"ok","storage":"ok"},"checked_at":"FECHA_ISO8601"}
```

- 401 sin clave válida, incluso si se intenta pasar sólo por query string. Sin clave configurada también cierra el acceso.
- 200 con la aplicación cargada, consulta real a la base y una sonda mínima de escritura/lectura/borrado en storage privado correctas.
- 503 con `status=degraded` si falla base o almacenamiento, sin detalles internos.

Las respuestas llevan `Cache-Control: no-store, private` y varían por Authorization. `/up` sigue siendo la salud mínima pública de Laravel. El endpoint nuevo no devuelve datos del panel ni inspecciona código, logs, archivos de usuarios o servicios externos. Turnstile pendiente se informa como configuración funcional, separada de estos controles de infraestructura.

**No queda cron de monitoreo ni envío a Hermes.** El consumidor decide la frecuencia y las alertas; no se creó ningún polling externo. El bloque anterior `AC_DEPLOY_WATCH s_2ecd9806046440d08236cbb57cbd6c5d` se retira conservando las otras entradas del usuario. Scripts, configuración e historial se conservan como archivo privado en `.deploy-tools/legacy-watch/`, sin hooks en deploy/rollback.

Para rotar la clave, cambiar sólo STATUS_CHECK_KEY en el .env privado, reconstruir la caché con PHP 8.5 y actualizar el consumidor. No rotar APP_KEY. Evitar copias del secreto en URLs, historial o capturas.

## Datos y herramientas

```text
/home/huesped-aging/htdocs/aging.huesped.org.ar/
├── deploy
├── .deploy-tools/
│   ├── deploy.php
│   ├── php-bin
│   ├── composer.phar
│   ├── vendor/                 # Deployer
│   ├── node-v22.23.5-linux-x64/
│   └── legacy-watch/           # archivo inactivo y respaldo de crontab
├── .dep/
├── releases/
├── shared/
│   ├── .env
│   ├── database/
│   │   ├── database.sqlite
│   │   └── backups/
│   └── storage/
├── current -> releases/RELEASE_ACTIVA
├── public -> current/public
└── public.cloudpanel-original/
```

PHP CLI/FPM 8.5.12, Composer privado 2.11.2, Deployer 8.0.5 y Node 22.23.5. Dependencias de la aplicación desde locks. No se usa administrador, sudo, runners ni workers residentes. El repositorio público se obtiene por HTTPS sin una credencial GitHub.

Producción usa MySQL desde el 17/11/2026; las credenciales quedan sólo en shared/.env. Se transfirieron y compararon por contenido las diez tablas, incluido el usuario, la pregunta y 14 sesiones presentes al corte. SQLite se conserva como respaldo histórico y ya no recibe las escrituras de producción. Sesiones, caché y límites usan la base configurada; uploads y APP_KEY se conservan. Antes de cada migración, app:backup detecta la conexión activa: para MySQL genera un dump privado transaccional en `shared/database/backups/before-release-N.sql`, con SHA-256 y credenciales temporales en un archivo privado que retira al terminar; para SQLite conserva su respaldo nativo. Se generó un primer dump MySQL y se comprobó la salida correcta, sin ensayar una restauración de producción. El respaldo del corte está en `shared/database/backups/mysql-cutover-20261117-152324/`; el entorno anterior sólo sirve para recuperación coordinada antes de nuevas escrituras: no restaurarlo ciegamente después del corte. No se subieron usuarios ni preguntas de prueba locales. El acceso autorizado existente de `debora.fiore@huesped.org.ar` se conserva.

La raíz pública configurada en CloudPanel resuelve a current/public mediante un enlace del usuario del sitio. La página inicial se preservó. Vistas compiladas separadas por release; assets de las dos versiones anteriores conservados para páginas abiertas. Revisar compatibilidad de migraciones futuras; no se promete continuidad bajo carga.

## Auditoría del montaje y verificación

El primer acceso SSH fue rechazado con la contraseña inicialmente facilitada; el puerto 22 funcionaba. La contraseña corregida permitió entrar. También se corrigieron durante el montaje la ayuda genérica no implementada por el wrapper clpctl, el cambio de tarea individual a grupo en Deployer 8 y un 403 del WAF al User-Agent predeterminado de Python. Se verificó el estado activo antes de continuar; no se cambió el WAF ni se repitió la publicación completa por ese 403.

La nueva implementación tiene pruebas específicas de autenticación, clave sólo en query, clave ausente, salud correcta, error de base y error de almacenamiento sin exponer detalles. Los fallos se simulan localmente, sin romper producción. La publicación comprueba además salud/login, 401 sin clave y 200 autenticado. La clave se conserva sólo en memoria durante la prueba HTTP. La comprobación externa usa el mismo contrato.

Los archivos privados .env, .git, Composer, vendor y herramientas no deben ser accesibles por la web; el panel sigue exigiendo sesión. El control de estado no sustituye un monitor de integridad.

## Pendientes funcionales

- Configurar las claves reales de Turnstile y el snippet de WordPress para habilitar recepción. No se copió la clave dummy local. Verificar la IP real detrás de Cloudflare antes de habilitar el límite por IP para visitantes.
- Configurar el consumidor externo del estado sólo si se desea. No hay destino ni credencial Hermes pendientes porque este modo no usa ese canal.

Las copias sin secretos de receta y wrapper están en `deploy-cloudpanel.php` y `deploy-cloudpanel.sh`. Para revocar el acceso temporal, confirmar primero el ingreso propio en otra sesión y cambiar la contraseña del usuario del sitio; no borrar authorized_keys ni APP_KEY.

## Skill actualizada

La copia instalada de ac-deploy-cp usa por defecto estado HTTPS con clave, sin cron. Valida SSH primero, descubre los datos disponibles y continúa el montaje de corrido con credenciales válidas. Incorpora los hallazgos comprobados de CloudPanel, Deployer y WAF; no conserva contraseñas ni datos específicos de este sitio como instrucciones generales. El modo avanzado con cron se conserva únicamente como opción explícita. Se validaron frontmatter, metadatos YAML, referencias y coherencia del modo reducido.

## Probar una revisión y cambiar de release

Actualmente sólo está configurado el host production; el wrapper admite deploy (main o SHA completo), releases, activate ID, plan y rollback. No hay URL/host test configurado todavía. La skill solicita la URL de prueba y exige base, storage, sesiones, configuración y bloqueos separados antes de habilitarla. La URL sugerida es test.aging.huesped.org.ar, pendiente de elección, DNS/TLS y sitio en CloudPanel. No apuntar una copia de prueba a la base viva.

Una revisión Git (SHA/tag) identifica el código; una release (1, 2, 3...) es una construcción local. Deployer 8 instalado admite `deploy --revision=SHA`; para probarla debe usarse una receta/host de test separado, todavía pendiente de configurar. Después se publica la misma revisión aprobada en producción; reconstruir desde el mismo SHA no equivale a promover el mismo artefacto.

Comandos existentes, ejecutados por SSH como usuario del sitio:

```bash
cd ~/htdocs/aging.huesped.org.ar
./deploy plan
./deploy
./deploy rollback
./deploy releases
# Publicar una revisión Git concreta (sustituir SHA_COMPLETO por 40 caracteres):
./deploy deploy SHA_COMPLETO
# Activar una release retenida, previa comprobación de compatibilidad:
./deploy activate ID
```

El rollback elige el candidato anterior bueno. El wrapper `./deploy activate ID` selecciona una release retenida usando la tarea de rollback con candidato explícito y los mismos bloqueos; comprobar antes compatibilidad de esquema, revisión y estado de esa release. No se ensayó una selección en producción para documentarla. Cambiar release no restaura la base, .env ni uploads. El wrapper aún no ofrece test ni promote: esos comandos son el contrato propuesto en la skill y requieren implementación por ambiente.

## Proxy inverso bajo /aging-panel (17/11/2026)

URL pública: `https://eventos.huesped.org.ar/aging-panel`. El upstream sigue siendo `https://aging.huesped.org.ar/`. El proxy elimina el prefijo al reenviar; Laravel lo recupera de X-Forwarded-Prefix **solo desde proxies confiables**. No agregar el prefijo a las rutas Laravel.

En `shared/.env`, conservar APP_KEY, DB y secretos existentes y actualizar:

```dotenv
APP_URL=https://eventos.huesped.org.ar/aging-panel
APP_ENV=production
APP_DEBUG=false
SESSION_COOKIE=huesped-preguntas-session
SESSION_DOMAIN=null
SESSION_PATH=/
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
```

`TRUSTED_PROXIES` debe contener las IP/CIDR verificadas de los saltos internos que recibe Laravel, separadas por coma. No usar `*` ni copiar una IP supuesta. Confirmar REMOTE_ADDR en la configuración/logs privados de aging y la cadena de CloudPanel/Cloudflare. El upstream debe conservar X-Forwarded-Host/Prefix/Proto/Port enviados por eventos; si otro proxy los sobrescribe hay que corregirlo allí. APP_URL por sí solo no corrige URLs generadas durante peticiones web.

En el bloque Nginx de eventos provisto, conservar `location ^~ /aging-panel/`, `proxy_pass https://aging.huesped.org.ar/;`, SNI, validación TLS y las cabeceras Host/Forwarded-Host/Prefix/Proto/Port. Cambiar:

```nginx
# Configuración activa: Nginx transforma el path raíz de Laravel:
proxy_cookie_path / /aging-panel/;
proxy_cookie_domain aging.huesped.org.ar eventos.huesped.org.ar;

# En el proxy de entrada: no conservar X-Forwarded-For arbitrario del visitante.
# Requiere que $remote_addr ya sea IP real si hay Cloudflare delante.
proxy_set_header X-Forwarded-For $remote_addr;
```

No mantener `proxy_cookie_path / /aging-panel/` con SESSION_PATH=/aging-panel: puede duplicar el prefijo. `SESSION_DOMAIN=null` produce cookies sin dominio explícito, asociadas por el navegador a eventos; no hace falta reescribir dominio. Eliminar cookies antiguas del navegador o volver a ingresar tras el cambio.

Si Cloudflare está delante, verificar primero el módulo real_ip de Nginx con rangos oficiales confiables y CF-Connecting-IP; no confiar en esa cabecera desde cualquier dirección. Si $remote_addr es todavía una IP de Cloudflare, el rate limit agrupará visitantes incorrectamente. Usar conexiones HTTPS al origen y bypass de caché Cloudflare para `/aging-panel/*`; `proxy_cache off` solamente controla la caché de Nginx.

Conservar `proxy_redirect https://aging.huesped.org.ar/ /aging-panel/;` como respaldo, pero no reemplaza corregir URLs en HTML/JS. El `^~` evita que la regex de assets WordPress capture JS/CSS del panel.

Después de actualizar el código y `.env`, desde la release activa, con el PHP usado por FPM:

```bash
cd ~/htdocs/aging.huesped.org.ar/current
php8.5 artisan config:clear
php8.5 artisan route:clear
php8.5 artisan view:clear
php8.5 artisan config:cache
php8.5 artisan view:cache
```

No ejecutar `cache:clear` ni `optimize:clear`: no hace falta vaciar la caché de datos/rate limits para este ajuste. No regenerar APP_KEY ni migrar la base por este cambio. Validar Nginx y recargar desde CloudPanel tras editar el vhost.

Verificar login, action del formulario, assets, logout, polling `/aging-panel/panel/data`, gestión de participantes y formulario `/aging-panel/formulario`. El iframe y snippet del repositorio ya apuntan al nuevo destino. El widget real debe permitir `eventos.huesped.org.ar`.

Revisión posterior con SSH: la petición atraviesa Cloudflare dos veces y el upstream sobrescribe X-Forwarded-Host con aging.huesped.org.ar. En producción el código fija URLs a APP_URL para no depender de ese host. TRUSTED_PROXIES incluye los rangos oficiales IPv4/IPv6 de Cloudflare consultados el 17/11/2026 y 20.220.171.156, salto de eventos observado. El .env tiene respaldo privado y mantiene SESSION_PATH=/ porque el Nginx existente reescribe a /aging-panel/. No cambiar únicamente uno de esos dos ajustes. No se modificó Nginx con el usuario del sitio.

Verificación tras publicación: release 6, commit `c1f0a7641abef666edc4c7c621ab87b82bf05674`. Login y formulario HTTP 200 bajo `/aging-panel`; action y assets apuntan al dominio/prefijo público; panel sin sesión redirige a ese login; CSS/JS/logo responden 200. Cookies Secure, SameSite=Lax, Path=/aging-panel/; sesión HttpOnly. Deploy verificó salud autenticada. No se ensayó ingreso con contraseña de Débora ni envíos reales de preguntas. La sonda temporal de cabeceras se retiró y se comprobó su ausencia.
