FROM php:8.4-fpm

# ==============================================================================
# 1. OS & PHP Dependencies
# ==============================================================================
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    supervisor \
    libmagickwand-dev \
    poppler-utils \
    libreoffice-writer-nogui \
    gnupg \
    && rm -rf /var/lib/apt/lists/*

# ==============================================================================
# 2. Install PHP Extensions
# ==============================================================================
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    opcache

# Install Redis & Imagick
RUN pecl install redis imagick \
    && docker-php-ext-enable redis imagick

# ==============================================================================
# 3. Install Node.js (Version 20)
# ==============================================================================
RUN mkdir -p /etc/apt/keyrings \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_20.x nodistro main" | tee /etc/apt/sources.list.d/nodesource.list \
    && apt-get update \
    && apt-get install -y nodejs \
    && npm install -g npm@latest

# ==============================================================================
# 4. Install Composer
# ==============================================================================
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ==============================================================================
# 5. Configuration
# ==============================================================================
# Set working directory to root of the mount (we will mount backend/frontend separately or together)
WORKDIR /var/www/html

# Create log directory for supervisor
RUN mkdir -p /var/log/supervisor

# Copy Supervisor config
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Permissions
RUN usermod -u 1000 www-data

# Expose ports: 8000 (Laravel), 5173 (Vue)
EXPOSE 8000 5173

# Start Supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
