#!/usr/bin/env bash
set -Eeuo pipefail

# Lo invoca la imagen PHP administrada; no sustituye su proceso PHP-FPM/Nginx.
if [[ "${APP_ENV:-}" != production || "${DB_CONNECTION:-}" != mysql ]]; then
    echo 'Azure: se exige APP_ENV=production y DB_CONNECTION=mysql.' >&2
    exit 1
fi
if [[ "${LARAVEL_STORAGE_PATH:-}" != /home/dhacer-storage ]]; then
    echo 'Azure: configura LARAVEL_STORAGE_PATH=/home/dhacer-storage.' >&2
    exit 1
fi
if [[ -z "${APP_KEY:-}" || "${APP_DEBUG:-false}" != false ]]; then
    echo 'Azure: falta APP_KEY o APP_DEBUG no es false.' >&2
    exit 1
fi

cd /home/site/wwwroot
test -f vendor/autoload.php
test -f public/build/manifest.json
test -r "${MYSQL_ATTR_SSL_CA:-/etc/ssl/certs/ca-certificates.crt}"
umask 0022
mkdir -p /home/dhacer-storage/{app/private,app/public,framework/cache/data,framework/sessions,framework/views,logs}
mkdir -p bootstrap/cache

# Mantener datos fuera del directorio que reemplaza ZIP Deploy.
if [[ -L public/storage ]]; then
    test "$(readlink public/storage)" = /home/dhacer-storage/app/public
elif [[ -e public/storage ]]; then
    echo 'Azure: public/storage ya existe y no es el enlace previsto; revisar manualmente.' >&2
    exit 1
else
    ln -s /home/dhacer-storage/app/public public/storage
fi

# Nunca migra, crea usuarios o regenera claves durante el arranque.
cp docs/azure/nginx.conf /etc/nginx/sites-available/default
nginx -t
service nginx reload
echo 'Azure: raíz public/, almacenamiento persistente y Nginx preparados.'
