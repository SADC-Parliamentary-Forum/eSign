#!/bin/bash
set -e

# 1. Install/Update Backend Dependencies
echo "Installing Backend Dependencies..."
cd /var/www/html/backend
if [ -f "vendor/autoload.php" ]; then
  echo "Backend dependencies already present, skipping composer install."
else
  composer install --no-interaction --optimize-autoloader
fi

# 2. Run Migrations
# 2. Wait for Database
echo "Waiting for Database..."
while ! pg_isready -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME"; do
  sleep 1
done

# 3. Run Migrations
echo "Running Migrations..."
if [ "${RUN_MIGRATIONS_ON_BOOT:-0}" = "1" ]; then
  php artisan migrate --force
else
  echo "Skipping migrations on boot (RUN_MIGRATIONS_ON_BOOT!=1)."
fi

# 3b. Ensure private processing storage exists and is writable for uploads.
echo "Preparing local processing storage..."
mkdir -p /var/www/html/backend/storage/app/private/processing
chmod -R ug+rwX /var/www/html/backend/storage/app/private
chmod -R a+rwX /var/www/html/backend/storage/app/private

# 3. Install/Update Frontend Dependencies from the committed lockfile
echo "Installing Frontend Dependencies..."
cd /var/www/html/frontend
if [ -d "node_modules" ] && [ -f "node_modules/.package-lock.json" ]; then
  echo "Frontend dependencies already present, skipping npm ci."
else
  node scripts/check-lock-sync.mjs
  npm ci
fi

# 4. Start Supervisor (which starts the app servers and workers)
echo "Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
