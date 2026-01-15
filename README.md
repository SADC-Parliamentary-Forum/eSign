# SADC-eSign Platform

A secure, multilingual document signing and contract management platform.

## Architecture
- **Backend**: Laravel 12 (PHP 8.2) + Postgres 15 + Redis 7 + MinIO
- **Frontend**: Vue.js 3 + Vite
- **Mobile**: Flutter
- **Real-time**: Laravel Reverb
- **Infrastructure**: Docker Compose (Unified Container)

## Getting Started

### 1. Prerequisites
- Docker Desktop
- Flutter SDK (for mobile)

### 2. Backend & Frontend (Unified)
Start the services:
```bash
docker compose up --build -d
```

Run database migrations:
```bash
docker compose exec app php artisan migrate --seed
```
*Note: This creates an Admin user: `admin@sadcpf.org` / `password`*

Install Frontend dependencies (if not part of build):
```bash
docker compose exec app npm install
```

Access the application:
- **Web App**: http://localhost:5173 (or via Gateway http://localhost:80)
- **API**: http://localhost:8000/api
- **MinIO Console**: http://localhost:9001
- **Mailpit**: http://localhost:8025

### 3. Mobile App
 Navigate to the mobile directory:
 ```bash
 cd mobile
 flutter pub get
 flutter run
 ```
 *Note: Login with `admin@sadcpf.org` / `password`*

## Key Features Implemented
- **Authentication**: JWT (Sanctum), MFA (Email), Magic Links.
- **Documents**: Upload, Convert to PDF, SHA-256 Hash, MinIO Storage.
- **Signing**: Canvas-based signature capture, Verification.
- **Workflow**: Role-based approval (Admin -> Finance -> SG).
- **Notifications**: Real-time (Reverb) and Email.
- **Audit**: Immutable audit logs.
- **Contracts**: Lifecycle management.

## Support
For issues, check `docker-compose logs -f`.
