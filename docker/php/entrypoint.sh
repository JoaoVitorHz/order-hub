#!/bin/sh
set -e

cd /var/www/backend

# Install dependencies if vendor is missing (happens with volume mounts after git clone)
if [ ! -d "vendor" ]; then
  echo "[entrypoint] vendor/ not found, running composer install..."
  composer install --no-interaction --optimize-autoloader --no-dev
fi

# Auto-create .env from .env.example if missing
if [ ! -f .env ]; then
  echo "[entrypoint] .env not found, copying from .env.example..."
  cp .env.example .env
fi

# Always sync critical Docker env vars into .env (overrides local values)
for var in DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD \
           REDIS_HOST REDIS_PORT REDIS_CLIENT CACHE_STORE QUEUE_CONNECTION \
           SESSION_DRIVER N8N_WEBHOOK_URL; do
  val=$(eval echo \$$var)
  if [ -n "$val" ]; then
    if grep -q "^${var}=" .env 2>/dev/null; then
      sed -i "s|^${var}=.*|${var}=${val}|" .env
    else
      echo "${var}=${val}" >> .env
    fi
  fi
done

# Generate APP_KEY if placeholder or empty
if grep -qE "^APP_KEY=$|^APP_KEY=base64:GENERATE" .env; then
  echo "[entrypoint] Generating APP_KEY..."
  php artisan key:generate --force
fi

# Wait for MySQL (max 60s)
echo "[entrypoint] Waiting for MySQL at ${DB_HOST:-mysql}:${DB_PORT:-3306}..."
for i in $(seq 1 30); do
  php -r "
    try {
      new PDO(
        'mysql:host=${DB_HOST:-mysql};port=${DB_PORT:-3306};dbname=${DB_DATABASE:-order_hub}',
        '${DB_USERNAME:-order_hub}',
        '${DB_PASSWORD:-order_hub_secret}'
      );
      exit(0);
    } catch (Exception \$e) { exit(1); }
  " 2>/dev/null && break
  echo "[entrypoint] MySQL not ready ($i/30), retrying in 2s..."
  sleep 2
done

# Run migrations
echo "[entrypoint] Running migrations..."
php artisan migrate --force

# Clear cached config (important after env changes)
php artisan config:clear
php artisan route:clear

echo "[entrypoint] Setup complete. Starting: $@"
exec "$@"
