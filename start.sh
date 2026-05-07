#!/usr/bin/env bash
#
# Container entrypoint for Render.
# Runs once at boot: prepares storage, applies env-driven config to nginx,
# clears + rebuilds Laravel caches, applies migrations, then hands control
# to supervisord which keeps nginx and php-fpm running.

set -euo pipefail

cd /var/www/html

# ─────────────────────────────────────────────────────────────────
# 1. nginx port (Render sets $PORT — default 10000)
# ─────────────────────────────────────────────────────────────────
: "${PORT:=10000}"
export PORT

envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf
echo "[start.sh] nginx will listen on :${PORT}"

# ─────────────────────────────────────────────────────────────────
# 2. Storage + bootstrap/cache permissions (re-asserted at boot)
# ─────────────────────────────────────────────────────────────────
mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Storage symlink (public/storage → storage/app/public)
php artisan storage:link --force >/dev/null 2>&1 || true

# ─────────────────────────────────────────────────────────────────
# 3. Laravel caches: rebuild from current env vars
#    (Render env may have changed since the last deploy)
# ─────────────────────────────────────────────────────────────────
php artisan config:clear  || true
php artisan route:clear   || true
php artisan view:clear    || true

php artisan config:cache
php artisan route:cache
php artisan view:cache

# ─────────────────────────────────────────────────────────────────
# 4. Database migrations (idempotent — safe on every deploy)
# ─────────────────────────────────────────────────────────────────
echo "[start.sh] Applying migrations…"
php artisan migrate --force --no-interaction

# OPTIONAL: seed catalog + demo users on first deploy.
# Both seeders use updateOrCreate so re-running is safe. Toggle via env.
if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    echo "[start.sh] Running seeders (RUN_SEEDERS=true)…"
    php artisan db:seed --class=CatalogSeeder    --force --no-interaction || true
    php artisan db:seed --class=DemoUsersSeeder  --force --no-interaction || true
fi

# ─────────────────────────────────────────────────────────────────
# 5. Hand off to supervisor (foreground; PID 1)
# ─────────────────────────────────────────────────────────────────
echo "[start.sh] Starting supervisord (nginx + php-fpm)…"
exec /usr/bin/supervisord -c /etc/supervisord.conf
