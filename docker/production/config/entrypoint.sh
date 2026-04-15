#!/bin/sh
set -e

# Cache configuration
echo "Syncing public assets..."
cp -r /usr/src/app/public/. /var/www/html/public/
chown -R www-data:www-data /var/www/html/public
chmod -R 755 /var/www/html/public

# Ensure views directory exists to prevent crash
mkdir -p /var/www/html/resources/views
chmod 755 /var/www/html/resources/views

# Ensure storage directories exist (Critical for View caching)
echo "Ensuring storage structure exists..."
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
chmod -R 775 /var/www/html/storage
chown -R www-data:www-data /var/www/html/storage

# Fix permissions for application code (Critical for Docker root-built files)
echo "Fixing application permissions..."
chown -R www-data:www-data /var/www/html/bootstrap
chown -R www-data:www-data /var/www/html/app
chown -R www-data:www-data /var/www/html/vendor
chown -R www-data:www-data /var/www/html/routes

# Ensure config/view.php exists (fail-safe for missing file sync)
echo "Checking/Creating config/view.php..."
if [ ! -f /var/www/html/config/view.php ]; then
    echo "Creating missing config/view.php..."
    cat > /var/www/html/config/view.php <<EOF
<?php
return [
    'paths' => ['/var/www/html/resources/views'],
    'compiled' => env('VIEW_COMPILED_PATH', realpath(storage_path('framework/views'))),
];
EOF
fi

# Debug: Verify files exist (only when DEBUG_ENTRYPOINT=1 for troubleshooting)
if [ "${DEBUG_ENTRYPOINT:-0}" = "1" ]; then
  echo "Debug: Listing config directory..."
  ls -la /var/www/html/config
  echo "Debug: Listing views directory..."
  ls -la /var/www/html/resources/views
  echo "Debug: Content of config/view.php:"
  cat /var/www/html/config/view.php
fi

echo "Caching configuration..."
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Artisan commands above run as root during container startup and can recreate
# storage/logs files with root ownership. Restore writable ownership before
# handing requests to php-fpm, which runs as www-data.
mkdir -p /var/www/html/storage/logs
touch /var/www/html/storage/logs/laravel.log
touch /var/www/html/storage/logs/esign.log
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage

# Run migrations forced
# Wait for Database
echo "Waiting for Database..."
while ! pg_isready -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME"; do
  sleep 1
done

# Run migrations forced
echo "Running migrations..."
php artisan migrate --force

# Start Supervisor
echo "Starting Supervisor..."
exec "$@"
