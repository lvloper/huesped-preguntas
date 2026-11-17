<?php

namespace Deployer;

require 'recipe/common.php';

localhost('production')
    ->set('deploy_path', '/home/huesped-aging/htdocs/aging.huesped.org.ar');

set('repository', 'https://github.com/lvloper/huesped-preguntas.git');
set('branch', 'main');
set('sub_directory', 'app');
set('user', 'huesped-aging');
set('bin/php', '/usr/bin/php8.5');
set('bin/composer', '{{bin/php}} {{deploy_path}}/.deploy-tools/composer.phar');
set('composer_options', '--prefer-dist --no-progress --no-interaction --no-dev --optimize-autoloader');
set('keep_releases', 3);
set('shared_files', ['.env']);
set('shared_dirs', ['storage']);
set('writable_mode', 'skip');
set('writable_use_sudo', false);
set('cleanup_use_sudo', false);
set('default_timeout', 600);
set('env', [
    'PATH' => '/home/huesped-aging/htdocs/aging.huesped.org.ar/.deploy-tools/node-v22.23.5-linux-x64/bin:/usr/local/bin:/usr/bin:/bin',
    'GIT_TERMINAL_PROMPT' => '0',
]);

desc('Builds the frontend from its lock');
task('app:build', function (): void {
    within('{{release_path}}', function (): void {
        run('npm ci --ignore-scripts --no-audit --no-fund');
        run('npm run build');
    });

    $previous = array_slice(get('releases_list'), 1, 2);
    $script = <<<'PY'
import json
from pathlib import Path
import shutil
import sys
root, target = Path(sys.argv[1]), Path(sys.argv[2])
for rid in sys.argv[3:]:
    source = root / 'releases' / rid / 'public/build'
    manifest = source / 'manifest.json'
    if not manifest.is_file():
        continue
    for item in json.loads(manifest.read_text()).values():
        names = [item.get('file')] + item.get('css', []) + item.get('assets', [])
        for name in names:
            if not isinstance(name, str) or not name.startswith('assets/') or '..' in Path(name).parts:
                continue
            src, dst = source / name, target / 'public/build' / name
            if src.is_file() and not dst.exists():
                dst.parent.mkdir(parents=True, exist_ok=True)
                shutil.copy2(src, dst)
PY;
    run('/usr/bin/python3 -c '.escapeshellarg($script).' {{deploy_path}} {{release_path}} '.implode(' ', array_map('escapeshellarg', $previous)));
    run('rm -rf {{release_path}}/node_modules');
});

desc('Backs up the persistent SQLite database before migrations');
task('app:backup', function (): void {
    $script = <<<'PY'
import os
from pathlib import Path
import sqlite3
import sys
os.umask(0o077)
root, rid = Path(sys.argv[1]), sys.argv[2]
directory = root / 'shared/database/backups'
directory.mkdir(mode=0o700, exist_ok=True)
destination = directory / ('before-release-' + rid + '.sqlite')
if destination.exists():
    raise SystemExit('El respaldo de esta release ya existe; revisar antes de continuar.')
with sqlite3.connect(root / 'shared/database/database.sqlite', timeout=30) as source:
    with sqlite3.connect(destination) as backup:
        source.backup(backup)
        if backup.execute('PRAGMA integrity_check').fetchone()[0] != 'ok':
            raise SystemExit('El respaldo no superó la comprobación de integridad.')
print('Respaldo SQLite privado comprobado.')
PY;
    run('/usr/bin/python3 -c '.escapeshellarg($script).' {{deploy_path}} {{release_name}}');
});

desc('Migrates the candidate and prepares its caches');
task('app:configure', function (): void {
    within('{{release_path}}', function (): void {
        run('{{bin/composer}} check-platform-reqs --no-dev');
        run('{{bin/php}} artisan migrate --force --no-interaction');
        run('{{bin/php}} artisan storage:link --no-interaction');
        run('mkdir -p bootstrap/cache/views');
        $env = ['VIEW_COMPILED_PATH' => parse('{{release_path}}/bootstrap/cache/views')];
        run('{{bin/php}} artisan config:cache --no-interaction', env: $env);
        run('{{bin/php}} artisan route:cache --no-interaction');
        run('{{bin/php}} artisan view:cache --no-interaction');
        run('{{bin/php}} artisan migrate:status --no-interaction');
        run('{{bin/php}} artisan route:list --except-vendor --no-ansi');
    });
    run('find {{release_path}} -type d -exec chmod 750 {} +');
    run('find {{release_path}} -type f -exec chmod u=rwX,g=rX,o= {} +');
});

desc('Checks the candidate against its source commit');
task('app:source', function (): void {
    $revision = trim(run('cat {{release_path}}/REVISION'));
    if (! preg_match('/\A[0-9a-f]{40}\z/', $revision)) {
        throw new \RuntimeException('La revisión del candidato no es válida.');
    }
    foreach (['composer.json', 'composer.lock', 'package-lock.json', 'public/index.php', 'bootstrap/app.php', 'routes/api.php', 'config/status.php', 'app/Http/Controllers/StatusController.php'] as $file) {
        $expected = run('{{bin/git}} -C {{deploy_path}}/.dep/repo show '.escapeshellarg($revision.':app/'.$file).' | sha256sum');
        $actual = run('sha256sum {{release_path}}/'.escapeshellarg($file));
        if (substr($expected, 0, 64) !== substr($actual, 0, 64)) {
            throw new \RuntimeException('El código del candidato difiere de su fuente Git.');
        }
    }
});

desc('Connects the existing public directory to the active release');
task('app:public', function (): void {
    $script = <<<'PY'
from pathlib import Path
import os
import sys
root = Path(sys.argv[1])
public = root / 'public'
if public.is_symlink():
    if public.resolve() != (root / 'current/public').resolve():
        raise SystemExit('El enlace público existente requiere revisión.')
elif public.is_dir():
    if {p.name for p in public.iterdir()} != {'index.php'} or (public / 'index.php').read_text().strip() != "<?php\n\necho 'Hello World :-)';":
        raise SystemExit('La carpeta pública tiene contenido que requiere una transición revisada.')
    backup, staging = root / 'public.cloudpanel-original', root / '.public-next'
    if backup.exists() or staging.exists() or staging.is_symlink():
        raise SystemExit('Ya existe un respaldo o transición pública pendiente.')
    os.symlink('current/public', staging)
    os.rename(public, backup)
    try:
        os.replace(staging, public)
    except BaseException:
        os.rename(backup, public)
        raise
else:
    raise SystemExit('No se encontró la raíz pública esperada.')
print('Raíz pública enlazada; página original conservada.')
PY;
    run('/usr/bin/python3 -c '.escapeshellarg($script).' {{deploy_path}}');
});

desc('Checks the live health and login over verified HTTPS');
task('app:http', function (): void {
    $script = <<<'PY'
from urllib.request import Request, urlopen
from urllib.error import HTTPError
from pathlib import Path
import json
for path in ('/up', '/login'):
    with urlopen(Request('https://aging.huesped.org.ar' + path, headers={'Cache-Control': 'no-cache', 'User-Agent': 'Mozilla/5.0'}), timeout=25) as response:
        body = response.read(262144)
        if response.status != 200 or (path == '/login' and b'name="email"' not in body):
            raise SystemExit('La comprobación HTTPS de la aplicación falló.')
    print('HTTPS', path, '200')
root = Path('/home/huesped-aging/htdocs/aging.huesped.org.ar')
if (root / 'current/config/status.php').is_file():
    try:
        with urlopen(Request('https://aging.huesped.org.ar/api/status', headers={'User-Agent': 'Mozilla/5.0'}), timeout=25) as response:
            raise SystemExit('El endpoint de estado permite acceso sin clave.')
    except HTTPError as error:
        if error.code != 401:
            raise SystemExit('El endpoint de estado no rechaza solicitudes anónimas con 401.')
    key = None
    for line in (root / 'shared/.env').read_text().splitlines():
        if line.startswith('STATUS_CHECK_KEY='):
            key = line.split('=', 1)[1].strip().strip('\"\'')
    if not key:
        raise SystemExit('Falta STATUS_CHECK_KEY en el entorno privado.')
    request = Request('https://aging.huesped.org.ar/api/status', headers={'User-Agent': 'Mozilla/5.0', 'Authorization': 'Bearer ' + key})
    with urlopen(request, timeout=25) as response:
        result = json.loads(response.read(8192))
        if response.status != 200 or result.get('status') != 'ok' or result.get('checks') != {'application': 'ok', 'database': 'ok', 'storage': 'ok'}:
            raise SystemExit('El estado autenticado no confirmó los componentes de la aplicación.')
    print('HTTPS /api/status: 401 sin clave, 200 autenticado; componentes correctos.')
PY;
    run('/usr/bin/python3 -c '.escapeshellarg($script));
});

desc('Deploys main with persistent data and a protected status endpoint');
task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'app:build',
    'app:backup',
    'app:configure',
    'app:source',
    'deploy:symlink',
    'app:public',
    'app:http',
    'deploy:cleanup',
    'deploy:unlock',
    'deploy:success',
]);

task('app:rollback', function (): void {
    $candidate = get('rollback_candidate');
    if (! preg_match('/\A[0-9]+\z/', $candidate)) {
        throw new \RuntimeException('El candidato de rollback no es válido.');
    }
    $script = <<<'PY'
from pathlib import Path
import os
import re
import sys
import time
root, rid = Path(sys.argv[1]), sys.argv[2]
candidate = (root / 'releases' / rid).resolve(strict=True)
if candidate.parent != (root / 'releases').resolve() or not candidate.is_dir() or (candidate / 'BAD_RELEASE').exists():
    raise SystemExit('El candidato de rollback no es una release válida.')
if not re.fullmatch('[0-9a-f]{40}', (candidate / 'REVISION').read_text().strip()):
    raise SystemExit('La revisión del candidato no es válida.')
if not (root / 'current').is_symlink():
    raise SystemExit('No existe el enlace current esperado.')
old = (root / 'current').resolve(strict=True)
if old.parent != (root / 'releases').resolve():
    raise SystemExit('La release activa está fuera de la carpeta esperada.')
staging = root / '.rollback-next'
if staging.exists() or staging.is_symlink():
    raise SystemExit('Hay una transición de rollback pendiente.')
os.symlink('releases/' + rid, staging)
os.replace(staging, root / 'current')
(old / 'BAD_RELEASE').write_text(str(int(time.time())) + ',huesped-aging\n')
print('Código anterior activado; base, configuración y archivos compartidos conservados.')
PY;
    run('/usr/bin/python3 -c '.escapeshellarg($script).' {{deploy_path}} '.escapeshellarg($candidate));
});

desc('Restores a previously approved release without reverting the database');
Deployer::get()->tasks->remove('rollback');
task('rollback', ['deploy:lock', 'app:rollback', 'app:http', 'deploy:unlock']);

after('deploy:failed', 'deploy:unlock');
fail('rollback', 'deploy:unlock');
