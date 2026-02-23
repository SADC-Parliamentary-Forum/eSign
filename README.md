# eSign – Installation

Run the app with **Docker** (recommended). You need **Docker** and **Docker Compose** installed.

---

## Development (local)

From the **repository root**:

```bash
# Build and start all services
docker compose -f docker-compose.dev.yml up -d --build
```

First run can take a few minutes (build, DB migrations, npm install). Then:

| What            | URL / Port   |
|-----------------|-------------|
| **App**         | http://localhost:8000 |
| **Vite (frontend)** | http://localhost:5173 |
| **MinIO console**  | http://localhost:9002 |
| **Mailpit (mail)** | http://localhost:8025 |

- Backend: Laravel in `backend/`, runs in container with Nginx + PHP-FPM.
- Frontend: Vite dev server; queue worker and Reverb run via Supervisor.
- DB: PostgreSQL, Redis, MinIO (and Mailpit) start automatically.

**Stop:**

```bash
docker compose -f docker-compose.dev.yml down
```

**Logs:**

```bash
docker compose -f docker-compose.dev.yml logs -f app
```

---

## Production

From the **`production`** directory:

### 1. Environment

```bash
cd production
cp .env.production.example .env.production
# Edit .env.production and set at least:
# - APP_URL (e.g. https://your-domain.com)
# - MAIL_* (SMTP for emails)
# - Any DB/Redis/MinIO secrets if you change them
```

### 2. Build and run

```bash
docker compose up -d --build
```

App is served internally on `127.0.0.1:8000`. Put a reverse proxy (e.g. host Nginx) in front with SSL and point it at that port.

### 3. Check services

```bash
docker compose ps
```

You should see `esign_nginx`, `esign_app`, `esign_postgres`, `esign_redis`, `esign_minio` running.

**Useful commands:**

- Logs: `docker compose logs -f app`
- Update: `git pull && docker compose build && docker compose up -d`

More detail: [production/README.md](production/README.md).

---

## Without Docker (backend only)

For running Laravel alone (e.g. on a host with PHP/Composer):

1. **Requirements:** PHP 8.4+, Composer, PostgreSQL, Redis, MinIO (or S3), Node 20+ (for frontend build).
2. **Backend:**

   ```bash
   cd backend
   cp .env.example .env
   php artisan key:generate
   # Set DB_*, REDIS_*, MINIO_* (or S3) in .env
   composer install
   php artisan migrate
   php artisan queue:work &
   ```
3. **Frontend (for dev):** `cd frontend && npm install && npm run dev`.  
   For production: `npm run build` and point your web server at `frontend/dist`.

Document conversion (Word/Excel/images to PDF) needs **LibreOffice** installed on the server when not using the Docker image that already includes it.
