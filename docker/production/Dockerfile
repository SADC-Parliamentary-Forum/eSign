# Stage 1: Build Frontend
FROM node:20 as frontend

WORKDIR /app

COPY frontend/package*.json ./
RUN npm ci --ignore-scripts

COPY frontend/ .
# Rebuild dependencies (like esbuild) that were skipped and run setup scripts
RUN npm rebuild && npm run build:icons && npm run msw:init
RUN npm run build

# Stage 2: Build Backend & Serve
FROM php:8.4-fpm

# Install system dependencies
RUN echo "exit 101" > /usr/sbin/policy-rc.d && chmod +x /usr/sbin/policy-rc.d

RUN apt-get update && apt-get install -y \
    nginx \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    postgresql-client \
    zip \
    unzip \
    supervisor \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd

# Get Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy backend dependencies definition
# Adjust paths relative to build context (root)
COPY backend/composer.json backend/composer.lock ./

# Install backend dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Copy backend code
# Copy backend code
COPY backend/ .

# Copy built frontend assets
COPY --from=frontend /app/dist public/

# Save a copy of public assets to a location not shadowed by volumes
RUN mkdir -p /usr/src/app/public && cp -r public/. /usr/src/app/public/

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Configure Nginx & Supervisor
# Config files are now relative to the build context
# Configure Nginx & Supervisor
# Config files are now relative to the build context
COPY docker/production/config/nginx.conf /etc/nginx/sites-available/default
COPY docker/production/config/php-fpm-override.conf /usr/local/etc/php-fpm.d/zz-force-listen.conf
COPY docker/production/config/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/production/config/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 3000

ENTRYPOINT ["/bin/sh", "/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
