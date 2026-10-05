#!/bin/bash
set -euo pipefail

docroot=/var/www/html

if [[ -d "$docroot" ]]; then
    uid="$(stat -c '%u' "$docroot")"
    gid="$(stat -c '%g' "$docroot")"
    if [[ "$uid" != "0" ]]; then
        usermod -o -u "$uid" www-data
        groupmod -o -g "$gid" www-data
    fi
fi

runtime_dirs=(
    "$docroot/log"
    "$docroot/admin_state"
    "$docroot/build_state"
    "$docroot/csv/SQLite"
    "$docroot/registrs/assets/search"
    "$docroot/registrs/ai_cache"
    "$docroot/konkursi/db"
    "$docroot/granti/db"
    "$docroot/granti/data"
    "$docroot/temp"
    "$docroot/pensionars"
    "$docroot/struktura/data"
)

mkdir -p "${runtime_dirs[@]}"
chown www-data:www-data "${runtime_dirs[@]}"

exec docker-php-entrypoint "$@"
