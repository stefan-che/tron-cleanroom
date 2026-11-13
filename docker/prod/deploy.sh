#!/usr/bin/env bash
#
# Roll out fresh code to production.
#
# Deploy model: the code lives in a git checkout on the server and is mounted into the containers,
# so a deploy is `git pull` in the application directory + running this script.
# Images need rebuilding only when the Dockerfiles or the list of
# PHP extensions change (make build && make up).
#
# Order matters: dependencies and migrations first, then caches, and only then
# process reload, so workers do not pick up a half-applied state.
set -euo pipefail

cd "$(dirname "$(readlink -f "$0")")"

DC="docker compose"
PHP="${DC} exec -T app-php"

log() { printf '\033[36m[deploy]\033[0m %s\n' "$*"; }

if [ ! -f .env ]; then
    echo "[deploy] No .env: copy .env.example and fill it in" >&2
    exit 1
fi

if ! ${DC} ps --status running --services | grep -qx app-php; then
    echo "[deploy] Container app-php is not running. First: make up" >&2
    exit 1
fi

# Permissions are checked before installing: composer fails halfway with the vague
# "does not exist and could not be created" if vendor is owned by root
# (this happens when Docker created the directory itself while mounting).
log "0/9 checking write permissions"
if ! ${DC} exec -T -u www-data app-php sh -c \
        'test -w vendor && test -w storage && test -w bootstrap/cache'; then
    echo "[deploy] No write access to vendor / storage / bootstrap/cache." >&2
    echo "[deploy] Fix the permissions and retry: make fix-perms" >&2
    exit 1
fi

# MySQL 8.0 → 8.4 migration: step 1 would recreate app-mysql on the new
# image, and the server would upgrade the data directory in place; it could not be
# rolled back to 8.0 afterwards. So the first time is manual only (the official MySQL
# upgrade procedure), with a cold copy of the data and a dump of ALL databases,
# including encryptor. After the migration this check stays silent by itself.
if ${DC} exec -T app-mysql mysqld --version 2>/dev/null | grep -q ' 8\.0\.'; then
    echo "[deploy] app-mysql runs MySQL 8.0, but compose requires 8.4." >&2
    echo "[deploy] Not upgrading automatically: the migration is irreversible." >&2
    echo "[deploy] Run make backup and upgrade MySQL to 8.4 manually following the MySQL documentation." >&2
    exit 1
fi

log "1/9 applying docker-compose.yml (recreates containers if it changed)"
# Without this step, edits to compose and docker/prod/.env just sit on disk:
# restart only reloads the image, not the service configuration. up -d does nothing
# when there is nothing to change, so the step is safe on every deploy.
${DC} up -d --remove-orphans

log "2/9 composer install (no dev dependencies)"
# No --ignore-platform-req: the PHP 8.4 image satisfies all requirements of
# composer.lock (symfony/* v8 require >= 8.4.1; comely-io/data-types
# is vendored in packages/ with the constraint php ^7.2 || ^8.0).
${DC} exec -T -u www-data app-php \
    composer install --no-dev --no-interaction --prefer-dist \
        --optimize-autoloader

log "3/9 publishing package assets"
# A mandatory step after package updates, not optional cosmetics.
# Orchid and Log Viewer assets live in public/vendor/ and are published by
# post-update-cmd in composer.json, but `composer install` (step 2) does NOT
# run that hook; only `composer update` does. Without this step
# public/vendor/ keeps the js from the previous package version, and the admin panel breaks
# silently: the blade templates are already new while the stimulus controllers in js are still old.
# Caught on 2026-09-14 after upgrading Orchid 14.31 -> 14.53: modal windows
# failed with "can't access property open, getControllerForElementAndIdentifier
# is null". The step is idempotent: if there is nothing to publish, it just overwrites
# the files with the same ones.
#
# The tags differ: Orchid attaches its assets to orchid-assets/laravel-assets,
# Log Viewer to its own log-viewer-assets, so one command cannot cover both.
#${PHP} php artisan vendor:publish --tag=orchid-assets --force
#${PHP} php artisan vendor:publish --tag=log-viewer-assets --force

log "4/9 migrations"
${PHP} php artisan migrate --force --no-interaction

log "5/9 seeders"
${PHP} php artisan db:seed --force --no-interaction

log "6/9 rebuilding caches"
# clear first, then optimize. They take effect only after the php-fpm reload
# (step 7): the image has opcache.validate_timestamps = 0, and until the workers
# are reloaded php serves the previous version of the bootstrap/cache files from memory.
${PHP} php artisan optimize:clear
${PHP} php artisan optimize

log "7/9 graceful reload php-fpm (resets opcache without dropping requests)"
${DC} kill -s USR2 app-php

log "8/9 restarting workers and the scheduler"
# queue:restart asks workers to exit after the current job; supervisor
# starts new ones right away. Restarting the queue container is not needed:
# opcache.enable_cli = 0, so a freshly started worker reads files from disk
# anyway. Such a restart would cost up to stop_grace_period (620 s),
# which is how long docker waits for the current job to finish.
if ! ${PHP} php artisan queue:restart; then
    log "queue:restart failed (cache unavailable?), restarting the container"
    ${DC} restart app-queue
fi
# The scheduler is restarted: schedule:work keeps the schedule in memory and
# runs tasks as separate processes.
${DC} restart app-scheduler

log "9/9 reload nginx (re-read the upstream address)"
${DC} exec -T app-nginx nginx -s reload

log "done"
${DC} ps --format 'table {{.Name}}\t{{.Status}}'
