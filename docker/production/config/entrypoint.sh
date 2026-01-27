#!/bin/bash
set -e

# Cache configuration
echo "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

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
