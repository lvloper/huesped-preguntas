#!/usr/bin/env bash
# Copiar a DEPLOY_PATH/deploy. Configuración completada por el agente al montar.
set -Eeuo pipefail
umask 027

usage() {
  printf '%s\n' 'Uso: deploy [deploy|rollback|plan|--help]'
}
fail() {
  printf '%s\n' "$1" >&2
  exit 1
}

if (( $# > 1 )); then usage >&2; exit 2; fi
action="${1:-deploy}"
case "$action" in
  --help|-h) usage; exit 0 ;;
  deploy|rollback|plan) ;;
  *) usage >&2; exit 2 ;;
esac

[[ "$EUID" -ne 0 ]] || fail 'Ejecutá este comando con el usuario del sitio, no como root.'

site_dir="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
tools_dir="$site_dir/.deploy-tools"
php_config="$tools_dir/php-bin"
dep_bin="$tools_dir/vendor/bin/dep"
recipe="$tools_dir/deploy.php"

[[ -r "$php_config" ]] || fail 'Falta .deploy-tools/php-bin; completar el montaje.'
php_bin="$(<"$php_config")"
[[ "$php_bin" = /* && -f "$php_bin" && -x "$php_bin" ]] || fail 'php-bin debe indicar una ruta absoluta ejecutable de PHP, sin argumentos.'
[[ -f "$dep_bin" && -r "$dep_bin" ]] || fail 'Falta Deployer en .deploy-tools/vendor/bin/dep.'
[[ -f "$recipe" && -r "$recipe" ]] || fail 'Falta la receta .deploy-tools/deploy.php.'
cd -- "$site_dir"

if [[ "$action" = plan ]]; then
  exec "$php_bin" "$dep_bin" "--file=$recipe" tree deploy
fi

command -v flock >/dev/null 2>&1 || fail 'Falta flock; el montaje debe resolver la exclusión antes de desplegar.'
exec 9>"$tools_dir/manual.lock"
flock -n 9 || fail 'Ya hay un deploy o rollback en curso. Esperá a que termine.'
# Respetar también operaciones lanzadas directamente con Deployer.
[[ ! -e "$site_dir/.dep/deploy.lock" ]] || fail 'Existe un bloqueo de Deployer. Verificá el proceso antes de desbloquear.'
exec "$php_bin" "$dep_bin" "--file=$recipe" "$action" production
