#!/bin/sh
set -e

cd /var/www/backend

# Wait for vendor/ to be ready (populated by backend container's entrypoint)
echo "[worker] Waiting for vendor/ to be available..."
for i in $(seq 1 30); do
  [ -d "vendor" ] && break
  echo "[worker] vendor/ not ready ($i/30), retrying in 3s..."
  sleep 3
done

if [ ! -d "vendor" ]; then
  echo "[worker] vendor/ still missing, running composer install..."
  composer install --no-interaction --optimize-autoloader --no-dev
fi

# Wait for .env
for i in $(seq 1 15); do
  [ -f ".env" ] && break
  echo "[worker] .env not ready ($i/15), retrying in 2s..."
  sleep 2
done

# Wait for MySQL
echo "[worker] Waiting for MySQL..."
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
  echo "[worker] MySQL not ready ($i/30), retrying in 2s..."
  sleep 2
done

echo "[worker] Starting: $@"
exec "$@"
