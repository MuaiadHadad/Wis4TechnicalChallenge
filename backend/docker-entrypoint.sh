#!/usr/bin/env sh
set -e
cd /var/www/html

# If vendor is missing (due to bind mount), install deps
if [ ! -f vendor/autoload.php ]; then
  echo "[entrypoint] Installing composer dependencies..."
  COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader || true
fi

exec "$@"

