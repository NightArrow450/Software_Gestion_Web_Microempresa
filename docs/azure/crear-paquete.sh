#!/usr/bin/env bash
set -Eeuo pipefail

# Solo empaqueta desde un checkout efímero Linux, nunca desde la instalación local.
if [[ "${GITHUB_ACTIONS:-}" != true || "${RUNNER_ENVIRONMENT:-}" != github-hosted || "$(uname -s)" != Linux ]]; then
    echo 'Ejecutar mediante el workflow Azure - preparar paquete Linux.' >&2
    exit 1
fi
cd "$(dirname "$0")/../.."
test ! -f .env
test ! -f bootstrap/cache/config.php
test -f vendor/autoload.php
test -f public/build/manifest.json
composer check-platform-reqs --no-dev

stage=$(mktemp -d "${RUNNER_TEMP:?}/dhacer-azure.XXXXXX")
mkdir -p "$stage/database" "$stage/docs/azure" "$stage/bootstrap/cache"
for directory in app config resources routes vendor; do
    rsync -a "$directory/" "$stage/$directory/"
done
rsync -a --exclude=cache bootstrap/ "$stage/bootstrap/"
rsync -a --exclude=hot --exclude=storage public/ "$stage/public/"
rsync -a database/migrations/ "$stage/database/migrations/"
rsync -a database/seeders/ "$stage/database/seeders/"
cp artisan composer.json composer.lock "$stage/"
cp docs/azure/{nginx.conf,startup.sh} "$stage/docs/azure/"
mkdir -p "$stage/storage/framework/"{cache/data,sessions,views} "$stage/storage/logs" "$stage/storage/app/"{private,public}

export AZURE_STAGE="$stage"
export AZURE_OUTPUT="${RUNNER_TEMP}/azure-release.zip"
python3 <<'PY'
import hashlib, json, os, pathlib, zipfile
root = pathlib.Path(os.environ['AZURE_STAGE'])
output = pathlib.Path(os.environ['AZURE_OUTPUT'])
manifest = {
    'commit': os.environ['GITHUB_SHA'],
    'branch': os.environ['GITHUB_REF_NAME'],
    'run_id': os.environ['GITHUB_RUN_ID'],
    'php': '8.4',
}
(root / 'release.json').write_text(json.dumps(manifest, indent=2) + '\n')
with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED) as archive:
    for path in sorted(root.rglob('*')):
        if not path.is_file():
            continue
        relative = path.relative_to(root)
        if path.is_symlink() or any(part in ('.git', 'node_modules') for part in relative.parts):
            raise RuntimeError(f'Ruta no permitida: {relative}')
        if path.name.startswith('.env') or path.suffix in ('.sqlite', '.log'):
            raise RuntimeError(f'Archivo no permitido: {relative}')
        archive.write(path, relative.as_posix())
with zipfile.ZipFile(output) as archive:
    required = {'artisan', 'vendor/autoload.php', 'public/build/manifest.json', 'docs/azure/startup.sh', 'release.json'}
    assert required <= set(archive.namelist()), 'Paquete incompleto'
    assert archive.testzip() is None, 'ZIP dañado'
digest = hashlib.sha256(output.read_bytes()).hexdigest()
output.with_suffix('.zip.sha256').write_text(f'{digest}  {output.name}\n')
print(f'Paquete Linux verificado: {len(archive.namelist())} entradas. SHA256: {digest}')
PY
