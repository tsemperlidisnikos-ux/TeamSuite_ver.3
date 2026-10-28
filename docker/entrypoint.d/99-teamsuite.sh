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

# Coolify / compose sometimes paste "DB_HOST=hostname" into the value.
if [ -n "${DB_HOST:-}" ]; then
  case "${DB_HOST}" in
    DB_HOST=*)
      export DB_HOST="${DB_HOST#DB_HOST=}"
      echo "[teamsuite] sanitized DB_HOST=${DB_HOST}"
      ;;
  esac
fi

# Map common Coolify MySQL resource env aliases when DB_* are empty.
if [ -z "${DB_HOST:-}" ] && [ -n "${MYSQL_HOST:-}" ]; then
  export DB_HOST="${MYSQL_HOST}"
fi
if [ -z "${DB_PORT:-}" ] && [ -n "${MYSQL_PORT:-}" ]; then
  export DB_PORT="${MYSQL_PORT}"
fi
if [ -z "${DB_DATABASE:-}" ] && [ -n "${MYSQL_DATABASE:-}" ]; then
  export DB_DATABASE="${MYSQL_DATABASE}"
fi
if [ -z "${DB_USERNAME:-}" ] && [ -n "${MYSQL_USER:-}" ]; then
  export DB_USERNAME="${MYSQL_USER}"
fi
if [ -z "${DB_PASSWORD:-}" ] && [ -n "${MYSQL_PASSWORD:-}" ]; then
  export DB_PASSWORD="${MYSQL_PASSWORD}"
fi

# Prefer file drivers until DB is proven reachable (avoids 500 on /login).
export SESSION_DRIVER="${SESSION_DRIVER:-file}"
export CACHE_STORE="${CACHE_STORE:-file}"
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-database}"
export DB_QUEUE_RETRY_AFTER="${DB_QUEUE_RETRY_AFTER:-1800}"

echo "[teamsuite] DB_CONNECTION=${DB_CONNECTION:-} DB_HOST=${DB_HOST:-} DB_PORT=${DB_PORT:-} DB_DATABASE=${DB_DATABASE:-}"

# Never migrate against a stale cached config from a previous boot.
php artisan config:clear >/dev/null 2>&1 || true
php artisan package:discover --ansi >/dev/null 2>&1 || true
php artisan storage:link --force >/dev/null 2>&1 || true

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  if [ -z "${DB_HOST:-}" ] && [ "${DB_CONNECTION:-}" != "sqlite" ]; then
    echo "[teamsuite] WARNING: DB_HOST empty — skip migrate. Link MySQL in Coolify or set DB_*."
  else
    echo "[teamsuite] migrate..."
    if ! php artisan migrate --force --no-interaction; then
      echo "[teamsuite] WARNING: migrate failed — DB host unreachable or credentials wrong."
      echo "[teamsuite] Coolify: same Project+Environment as MySQL, Link database, copy Internal hostname into DB_HOST, Restart."
    fi
  fi
fi

# Cache config only after env is finalized for this boot.
php artisan config:cache >/dev/null 2>&1 || php artisan config:clear || true
php artisan route:cache >/dev/null 2>&1 || php artisan route:clear || true
php artisan view:cache >/dev/null 2>&1 || php artisan view:clear || true

echo "[teamsuite] init done."
