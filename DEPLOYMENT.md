# eSign Platform - Production Deployment Guide

## Overview

This guide covers deploying the eSign platform to production with Docker, including all services, security configurations, and monitoring.

**Stack:**
- Backend: Laravel 10 (PHP 8.2)
- Frontend: Vue 3 + Vite
- Database: PostgreSQL 15
- Cache: Redis 7
- Storage: MinIO (S3-compatible)
- WebSocket: Laravel Reverb
- Reverse Proxy: Traefik
- Email: SMTP (production) / Mailpit (development)

---

## Prerequisites

### Server Requirements

**Minimum (Staging):**
- 2 CPU cores
- 4GB RAM
- 40GB SSD storage
- Ubuntu 22.04 LTS

**Recommended (Production):**
- 4 CPU cores
- 8GB RAM
- 100GB SSD storage
- Ubuntu 22.04 LTS

### Software Requirements

```bash
# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER

# Install Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/download/v2.24.0/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Verify installation
docker --version
docker-compose --version
```

---

## Environment Setup

### 1. Clone Repository

```bash
cd /var/www
git clone https://github.com/your-org/esign-platform.git
cd esign-platform
```

### 2. Create Environment Files

**Backend (.env):**

```bash
cd backend
cp .env.example .env
```

Edit `backend/.env`:

```env
# Application
APP_NAME="eSign Platform"
APP_ENV=production
APP_KEY=base64:YOUR_GENERATED_KEY_HERE
APP_DEBUG=false
APP_URL=https://esign.yourdomain.com

# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=sadc_esign
DB_USERNAME=sadc_esign
DB_PASSWORD=YOUR_SECURE_PASSWORD_HERE

# Cache & Sessions
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# File Storage (MinIO/S3)
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=YOUR_MINIO_ACCESS_KEY
AWS_SECRET_ACCESS_KEY=YOUR_MINIO_SECRET_KEY
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=documents
AWS_ENDPOINT=http://minio:9000
AWS_USE_PATH_STYLE_ENDPOINT=true

# Email
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@esign.yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

# WebSocket (Reverb)
BROADCAST_DRIVER=reverb
REVERB_APP_ID=esign
REVERB_APP_KEY=YOUR_REVERB_KEY
REVERB_APP_SECRET=YOUR_REVERB_SECRET
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=ws

# Frontend URL
FRONTEND_URL=https://esign.yourdomain.com

# Phase 10: Legal Defensibility
SIGNATURE_CERTIFICATE_VALIDITY_YEARS=2
EVIDENCE_PACKAGE_STORAGE=s3

# Security
JWT_SECRET=YOUR_JWT_SECRET_HERE
SANCTUM_STATEFUL_DOMAINS=esign.yourdomain.com
SESSION_DOMAIN=.yourdomain.com

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=warning
```

**Frontend (.env):**

```bash
cd ../frontend
cp .env.example .env
```

Edit `frontend/.env`:

```env
# API Configuration
VITE_API_URL=https://esign.yourdomain.com/api
VITE_APP_URL=https://esign.yourdomain.com

# WebSocket (Reverb)
VITE_REVERB_APP_KEY=YOUR_REVERB_KEY
VITE_REVERB_HOST=esign.yourdomain.com
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=wss

# Environment
VITE_APP_ENV=production
```

### 3. Generate Secrets

```bash
# Laravel APP_KEY
cd backend
php artisan key:generate

# JWT Secret
php artisan jwt:secret

# Reverb Keys
openssl rand -base64 32  # Use for REVERB_APP_KEY
openssl rand -base64 64  # Use for REVERB_APP_SECRET
```

---

## Docker Deployment

### 1. Build Images

```bash
# From project root
docker-compose build
```

### 2. Start Services

```bash
docker-compose up -d
```

### 3. Initialize Application

```bash
# Install backend dependencies
docker-compose exec app composer install --optimize-autoloader --no-dev

# Run migrations
docker-compose exec app php artisan migrate --force

# Seed database (optional, for demo data)
docker-compose exec app php artisan db:seed

# Create storage link
docker-compose exec app php artisan storage:link

# Optimize Laravel
docker-compose exec app php artisan config:cache
docker-compose exec app php artisan route:cache
docker-compose exec app php artisan view:cache

# Install frontend dependencies
docker-compose exec app npm install --prefix frontend

# Build frontend
docker-compose exec app npm run build --prefix frontend
```

### 4. Start Queue Workers

```bash
# Queue worker for background jobs
docker-compose exec -d app php artisan queue:work --queue=default,high --tries=3 --timeout=300

# Reverb WebSocket server
docker-compose exec -d app php artisan reverb:start --host=0.0.0.0 --port=8080
```

---

## Production Docker Compose

Create `docker-compose.prod.yml`:

```yaml
version: '3.9'

services:
## Nginx Configuration (Docker)

Since your production server uses a global Nginx reverse proxy, the Docker Nginx container is configured to:
1. Listen on `8000` (mapped to `80` inside container)
2. Handle HTTP traffic only (SSL termination is done by the host)
3. Proxy requests to the Backend and Frontend

**Host Nginx Configuration (Reference):**

Ensure your host's Nginx configuration includes:

```nginx
location / {
    proxy_pass http://127.0.0.1:8000;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

**Docker Nginx Config:**
The file `docker/nginx/conf.d/esign.conf` is already configured to accept these headers.

  # Laravel App (PHP-FPM)
  app:
    build:
      context: .
      dockerfile: docker/php/Dockerfile.prod
    container_name: esign_app
    restart: unless-stopped
    working_dir: /var/www/html
    volumes:
      - ./backend:/var/www/html/backend
      - ./storage:/var/www/html/backend/storage
      - ./docker/php/php.ini:/usr/local/etc/php/php.ini:ro
    environment:
      APP_ENV: production
      APP_DEBUG: "false"
    depends_on:
      - postgres
      - redis
    networks:
      - esign_network

  # Queue Worker
  queue:
    build:
      context: .
      dockerfile: docker/php/Dockerfile.prod
    container_name: esign_queue
    restart: unless-stopped
    command: php artisan queue:work --queue=default,high --tries=3 --timeout=300
    volumes:
      - ./backend:/var/www/html/backend
      - ./storage:/var/www/html/backend/storage
    depends_on:
      - app
      - redis
    networks:
      - esign_network

  # Reverb WebSocket
  reverb:
    build:
      context: .
      dockerfile: docker/php/Dockerfile.prod
    container_name: esign_reverb
    restart: unless-stopped
    command: php artisan reverb:start --host=0.0.0.0 --port=8080
    ports:
      - "8080:8080"
    volumes:
      - ./backend:/var/www/html/backend
    depends_on:
      - app
      - redis
    networks:
      - esign_network

  # PostgreSQL
  postgres:
    image: postgres:15-alpine
    container_name: esign_postgres
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${DB_DATABASE}
      POSTGRES_USER: ${DB_USERNAME}
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes:
      - postgres_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME}"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - esign_network

  # Redis
  redis:
    image: redis:7-alpine
    container_name: esign_redis
    restart: unless-stopped
    command: redis-server --requirepass ${REDIS_PASSWORD}
    volumes:
      - redis_data:/data
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - esign_network

  # MinIO (S3-compatible storage)
  minio:
    image: minio/minio:latest
    container_name: esign_minio
    restart: unless-stopped
    command: server /data --console-address ":9091"
    environment:
      MINIO_ROOT_USER: ${MINIO_ACCESS_KEY}
      MINIO_ROOT_PASSWORD: ${MINIO_SECRET_KEY}
    volumes:
      - minio_data:/data
    ports:
      - "9000:9000"
      - "9091:9091"
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:9000/minio/health/live"]
      interval: 30s
      timeout: 20s
      retries: 3
    networks:
      - esign_network

networks:
  esign_network:
    driver: bridge

volumes:
  postgres_data:
  redis_data:
  minio_data:
```

---

## Nginx Configuration

Create `docker/nginx/nginx.conf`:

```nginx
user nginx;
worker_processes auto;
error_log /var/log/nginx/error.log warn;
pid /var/run/nginx.pid;

events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';

    access_log /var/log/nginx/access.log main;

    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;
    client_max_body_size 20M;

    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript application/json application/javascript application/xml+rss application/rss+xml font/truetype font/opentype application/vnd.ms-fontobject image/svg+xml;

    include /etc/nginx/conf.d/*.conf;
}
```

Create `docker/nginx/conf.d/esign.conf`:

```nginx
# Backend API
upstream backend {
    server app:9000;
}

# Frontend
server {
    listen 80;
    listen [::]:80;
    server_name esign.yourdomain.com;

    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name esign.yourdomain.com;

    # SSL Configuration
    ssl_certificate /etc/nginx/ssl/fullchain.pem;
    ssl_certificate_key /etc/nginx/ssl/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Frontend (SPA)
    root /var/www/html/frontend/dist;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    # Backend API
    location /api {
        try_files $uri $uri/ /index.php?$query_string;
        fastcgi_pass backend;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME /var/www/html/backend/public/index.php;
        include fastcgi_params;
    }

    # Storage
    location /storage {
        alias /var/www/html/backend/storage/app/public;
        try_files $uri =404;
    }

    # PHP-FPM status (optional, for monitoring)
    location ~ ^/(status|ping)$ {
        access_log off;
        fastcgi_pass backend;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }
}

# WebSocket Server (Reverb)
server {
    listen 8080;
    listen [::]:8080;
    server_name esign.yourdomain.com;

    location / {
        proxy_pass http://reverb:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 86400;
    }
}
```

---

## SSL/TLS Setup (Let's Encrypt)

```bash
# Install Certbot
sudo apt-get update
sudo apt-get install certbot python3-certbot-nginx

# Obtain certificate
sudo certbot --nginx -d esign.yourdomain.com

# Auto-renewal (already configured by certbot)
sudo certbot renew --dry-run
```

---

## Health Checks

Create `backend/routes/api.php` addition:

```php
// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'services' => [
            'database' => DB::connection()->getPdo() ? 'up' : 'down',
            'cache' => Cache::has('health_check') || Cache::put('health_check', true, 10) ? 'up' : 'down',
            'storage' => Storage::disk('s3')->exists('health_check.txt') ? 'up' : 'down',
        ],
    ]);
});
```

---

## Monitoring & Logging

### Application Monitoring

```bash
# Laravel Telescope (already included)
# Access at: https://esign.yourdomain.com/telescope

# Enable in production (optional)
php artisan telescope:publish
```

### Log Aggregation

```bash
# View logs
docker-compose logs -f app
docker-compose logs -f nginx
docker-compose logs -f queue

# Log rotation (configure in docker-compose.yml)
logging:
  driver: "json-file"
  options:
    max-size: "10m"
    max-file: "3"
```

---

## Backup Strategy

### Database Backup

```bash
# Create backup script: docker/scripts/backup-db.sh
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
docker-compose exec -T postgres pg_dump -U sadc_esign sadc_esign > backups/db_$DATE.sql
gzip backups/db_$DATE.sql

# Keep only last 30 days
find backups/ -name "db_*.sql.gz" -mtime +30 -delete
```

### Storage Backup

```bash
# MinIO data backup
docker-compose exec minio mc mirror /data backups/minio/
```

### Automated Backups (Cron)

```bash
# Add to crontab
crontab -e

# Daily database backup at 2 AM
0 2 * * * /var/www/esign-platform/docker/scripts/backup-db.sh

# Weekly storage backup
0 3 * * 0 /var/www/esign-platform/docker/scripts/backup-storage.sh
```

---

## Deployment Checklist

### Pre-Deployment

- [ ] All environment variables set
- [ ] SSL certificates obtained
- [ ] Domain DNS configured
- [ ] Firewall rules set (80, 443, 8080)
- [ ] Database backup created

### Deployment

- [ ] `docker-compose build` successful
- [ ] `docker-compose up -d` running
- [ ] Migrations executed
- [ ] Storage linked
- [ ] Queue workers running
- [ ] Reverb WebSocket running
- [ ] Frontend build completed

### Post-Deployment

- [ ] Health check endpoint returns 200
- [ ] Can access frontend (HTTPS)
- [ ] Can access API (`/api/health`)
- [ ] WebSocket connection works
- [ ] Upload document test successful
- [ ] Email sending works
- [ ] Phase 10 features functional

### Smoke Tests

```bash
# Test API
curl https://esign.yourdomain.com/api/health

# Test frontend
curl -I https://esign.yourdomain.com

# Test WebSocket
 wscat -c wss://esign.yourdomain.com:8080

# Test file upload
curl -X POST https://esign.yourdomain.com/api/documents \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@test.pdf" \
  -F "title=Test Document"
```

---

## Troubleshooting

### Issue: Container Won't Start

```bash
# Check logs
docker-compose logs app

# Check dependencies
docker-compose ps

# Restart specific service
docker-compose restart app
```

### Issue: Database Connection Failed

```bash
# Verify PostgreSQL is running
docker-compose exec postgres psql -U sadc_esign -d sadc_esign -c "SELECT 1"

# Check connection from app
docker-compose exec app php artisan tinker
>>> DB::connection()->getPdo()
```

### Issue: Storage Not Working

```bash
# Verify MinIO is accessible
curl http://localhost:9000/minio/health/live

# Create bucket if missing
docker-compose exec minio mc alias set myminio http://localhost:9000 minioadmin minioadmin
docker-compose exec minio mc mb myminio/documents
```

### Issue: WebSocket Not Connecting

```bash
# Check Reverb is running
docker-compose logs reverb

# Test WebSocket (via main domain)
wscat -c wss://esign.sadcpf.org/app/sadc_esign_key?protocol=7&client=js&version=8.4.0&flash=false

# Verify nginx proxy config
docker-compose exec nginx nginx -t
```

---

## Scaling for Production

### Horizontal Scaling

```yaml
# docker-compose.scale.yml
services:
  app:
    deploy:
      replicas: 3
  
  queue:
    deploy:
      replicas: 2
```

```bash
docker-compose -f docker-compose.yml -f docker-compose.scale.yml up -d
```

### Load Balancing

Use Traefik or nginx upstream for multiple app containers.

---

## Security Hardening

1. **Firewall (UFW)**
```bash
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 8080/tcp
sudo ufw enable
```

2. **Fail2Ban**
```bash
sudo apt-get install fail2ban
sudo systemctl enable fail2ban
```

3. **Regular Updates**
```bash
# Update Docker images
docker-compose pull
docker-compose up -d

# Update dependencies
composer update
npm update
```

---

## Rollback Procedure

```bash
# Stop current deployment
docker-compose down

# Restore previous version
git checkout previous-tag
docker-compose build
docker-compose up -d

# Restore database
docker-compose exec -T postgres psql -U sadc_esign sadc_esign < backups/db_backup.sql
```

---

## Support & Maintenance

- **Logs:** `/var/log/nginx/`, `storage/logs/laravel.log`
- **Monitoring:** Telescope at `/telescope`
- **Backups:** `/backups/` directory
- **Updates:** Weekly dependency updates, monthly security patches

**Production Platform Ready! 🚀**
