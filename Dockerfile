# TeamSuite_ver.3 — Coolify / Hetzner
# serversideup/php keeps its own ENTRYPOINT + S6 (/init). We only add:
# - Vite build
# - Composer vendor
# - entrypoint.d script for migrate + caches

# ---------- Frontend ----------
FROM node:22-bookworm-slim AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY public ./public
COPY vite.config.js tailwind.config.js postcss.config.js jsconfig.json ./
RUN npm run build

# ---------- Composer ----------
FROM serversideup/php:8.3-fpm-nginx AS vendor
USER root
WORKDIR /var/www/html
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ---------- Runtime ----------
FROM serversideup/php:8.3-fpm-nginx

USER root
WORKDIR /var/www/html

# Laravel Number::format / money / locale helpers need intl (~40MB).
RUN install-php-extensions intl

# Laravel public webroot (critical).
# File/sync drivers avoid HTTP 500 when Coolify DB link is briefly unavailable.
ENV NGINX_WEBROOT=/var/www/html/public \
    SSL_MODE=off \
    AUTORUN_ENABLED=false \
    PHP_OPCACHE_ENABLE=1 \
    HEALTHCHECK_PATH=/up \
    SESSION_DRIVER=file \
    CACHE_STORE=file \
    QUEUE_CONNECTION=sync \
    PHP_MAX_EXECUTION_TIME=1800 \
    NGINX_FASTCGI_TIMEOUT=1800s

COPY --chown=www-data:www-data . /var/www/html
COPY --from=vendor --chown=www-data:www-data /var/www/html/vendor /var/www/html/vendor
COPY --from=frontend --chown=www-data:www-data /app/public/build /var/www/html/public/build

# Startup hooks (migrations / caches) — do NOT replace image ENTRYPOINT
COPY --chmod=755 docker/entrypoint.d/99-teamsuite.sh /etc/entrypoint.d/99-teamsuite.sh

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

USER www-data

EXPOSE 8080
