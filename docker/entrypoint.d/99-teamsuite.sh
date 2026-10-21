#!/usr/bin/env sh
# Runs after serversideup default entrypoint scripts, before nginx/php-fpm.
set -e

cd "${APP_BASE_DIR:-/var/www/html}"

echo "[teamsuite] init storage + caches..."

mkdir -p \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

# www-data owns these in the image; Coolify volumes may remount empty dirs.
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true

if [ -z "${APP_KEY:-}" ]; then
  echo "[teamsuite] ERROR: APP_KEY is empty."
  echo "[teamsuite] In Coolify → Environment Variables set APP_KEY from: php artisan key:generate --show"
  exit 1
fi

php artisan package:discover --ansi >/dev/null 2>&1 || true
php artisan storage:link --force >/dev/null 2>&1 || true

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  echo "[teamsuite] migrate..."
  php artisan migrate --force --no-interaction || {
    echo "[teamsuite] WARNING: migrate failed — check DB_* / linked MySQL. Continuing."
  }
fi

php artisan config:cache >/dev/null 2>&1 || php artisan config:clear || true
php artisan route:cache >/dev/null 2>&1 || php artisan route:clear || true
php artisan view:cache >/dev/null 2>&1 || php artisan view:clear || true

echo "[teamsuite] init done."
