FROM php:8.4-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libzip-dev \
    postgresql-client \
    zip \
    unzip \
    ghostscript \
    libreoffice \
    supervisor \
    nginx \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Node.js for frontend dev
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd zip

# Get Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Development specific settings
# We don't copy code here because we mount it as a volume in docker-compose.dev.yml
# But we might need to copy basics if we want the container to be buildable without mount?
# Usually dev containers rely on volumes.

COPY docker/development/config/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/development/config/nginx.conf /etc/nginx/sites-available/default
COPY docker/development/config/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000 5173

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
