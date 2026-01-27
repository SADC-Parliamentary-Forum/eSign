#!/bin/bash
set -e

# 1. Install/Update Backend Dependencies
echo "Installing Backend Dependencies..."
cd /var/www/html/backend
composer install --no-interaction --optimize-autoloader

# 2. Run Migrations
# 2. Wait for Database
echo "Waiting for Database..."
while ! pg_isready -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME"; do
  sleep 1
done

# 3. Run Migrations
echo "Running Migrations..."
php artisan migrate --force

# 3. Install/Update Frontend Dependencies
echo "Installing Frontend Dependencies..."
cd /var/www/html/frontend
npm install

# 4. Start Supervisor (which starts the app servers and workers)
echo "Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
