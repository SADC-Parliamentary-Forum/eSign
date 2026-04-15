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

---

## Production troubleshooting (e.g. https://esign.sadcpf.org)

### 502 Bad Gateway / Unable to login

If the frontend shows **502** on `/api/auth/login`, `/api/auth/me`, or other API calls, the problem is on the **server** (reverse proxy or Laravel/PHP), not the app code.

**On the server, check:**

1. **PHP-FPM / Laravel is running**
   - If using Docker: `docker compose ps` and `docker compose logs -d app`.
   - If using system PHP-FPM: `systemctl status php*-fpm` and Nginx/Apache error logs.

2. **Laravel logs**
   - `backend/storage/logs/laravel.log` — look for PHP errors, memory limits, or timeouts.

3. **Permissions**
   - `storage/` and `bootstrap/cache/` must be writable by the web server user.

4. **Environment**
   - `.env` exists and `APP_KEY` is set; run `php artisan config:clear` and `php artisan config:cache` after changes.

5. **Reverse proxy (Nginx/Apache)**
   - Proxy target (e.g. `http://127.0.0.1:8000` or PHP-FPM socket) is correct and the backend process is listening.
   - Timeouts (e.g. `proxy_read_timeout`) are high enough for slow requests.

6. **WebSocket (optional)**
   - Reverb/broadcasting 502 or WebSocket errors only affect real-time features; login and API still work if the main API is healthy.

Once the API responds with 200 (or 401 for bad credentials) instead of 502, login will work again.

### Intermittent 502 / "Connection refused" to upstream

If Nginx logs show **connection refused** to the app container, e.g.:

- `connect() failed (111: Connection refused) while connecting to upstream`
- `upstream: "fastcgi://...:9000"` (PHP-FPM) or `upstream: "http://...:8080/..."` (Reverb)

then the **app container** (PHP-FPM or Reverb) is sometimes not accepting connections — the process may be crashing or restarting.

**Immediate fix (Docker):**

```bash
cd production   # or your compose directory
docker compose restart app
docker compose ps   # ensure app is Up
```

**Then investigate:**

- `docker compose logs -f app` — look for PHP fatal errors, OOM kills, or Reverb crashes.
- Ensure the app service has a **restart policy** (e.g. `restart: unless-stopped`) in `docker-compose.yml` so it comes back after a crash.
- Consider adding a **healthcheck** for the app container and increasing PHP-FPM `pm.max_requests` or memory limits if you see repeated restarts.

### 403 on login (Bot protection / CSP)

If **POST /api/auth/login** returns **403**, the server is rejecting the request. Common causes:

- **Bot protection:** The backend expects the **X-Human-Token** header (reCAPTCHA). If the Vue app can’t load or run reCAPTCHA (e.g. blocked by CSP or ad blocker), no token is sent and the server may return 403. See **"Bot protection token missing"** below for the env option to allow requests without a token.
- **CSP:** If your environment (e.g. Cloudflare, Nginx, or meta tags) sends a **Content-Security-Policy** that restricts scripts, reCAPTCHA or app scripts may not run, so no bot token is sent and login fails with 403. Ease the policy (or set `BOT_PROTECTION_BLOCK_WHEN_TOKEN_MISSING=false`) so login can succeed.

### "Bot protection token missing" (BOT_TOKEN_MISSING)

If login or other protected actions return **403** with `code: "BOT_TOKEN_MISSING"`, the server expects the **X-Human-Token** header (reCAPTCHA token). The **Vue web app** sends it when reCAPTCHA is loaded; the **mobile app** does not.

**Options:**

- **Allow mobile / no-token clients:** Set in your backend `.env` (or in production env):
  ```bash
  BOT_PROTECTION_BLOCK_WHEN_TOKEN_MISSING=false
  ```
  Then clear config cache: `php artisan config:clear` and `php artisan config:cache`. Requests without `X-Human-Token` will no longer be blocked (rate limiting still applies).
- **Keep strict (web only):** Ensure the Vue app has reCAPTCHA loaded and the correct `VITE_RECAPTCHA_SITE_KEY`; the web app will send the token and verification will run as usual.

### Content Security Policy (CSP) and script-src

If the console shows **"violates the following Content Security Policy directive: script-src 'none'"** (or similar), the policy is often set **outside** this repo (e.g. Cloudflare Security, Nginx, or a meta tag). The message may say **"report-only"** — in that case the policy is only logging, not blocking. If scripts are actually blocked, the app or reCAPTCHA may not run and login can return 403.

To allow the app and reCAPTCHA to work when you use an **enforcing** CSP, include at least:

- **script-src / script-src-elem:** your app origin plus reCAPTCHA hosts (https://www.google.com, https://www.gstatic.com, https://www.recaptcha.net).
- **worker-src:** 'self' so PDF.js worker can load from bundled assets.
- **style-src / style-src-elem:** 'self' and https://fonts.googleapis.com (MDI is served locally).
- **font-src:** 'self' and https://fonts.gstatic.com.
- **frame-src** (if using reCAPTCHA checkbox): https://www.recaptcha.net and/or https://www.google.com.

Where to change it depends on your setup (e.g. Cloudflare Dashboard → Security → Settings, or Nginx `add_header`, or Laravel middleware). This repo sets CSP in `production/nginx.conf` and `docker/nginx/conf.d/esign.conf`; upstream proxies (e.g. Cloudflare) can still override it.

### Mobile / Flutter web: "Failed to fetch" when logging in from localhost

If the **mobile app** (Flutter web in Chrome, e.g. `http://localhost:60094`) or any local dev app gets **"Failed to fetch"** or **"Login Error: ClientException"** when calling `https://esign.sadcpf.org/api/auth/login`, the browser is blocking the request due to **CORS**: the server must allow your origin.

The backend already allows `http://localhost:*` and `http://127.0.0.1:*` via `config/cors.php` (`allowed_origins_patterns`). On the **production server** you must:

1. **Deploy the latest backend** so `backend/config/cors.php` includes the `allowed_origins_patterns` entries.
2. **Clear and rebuild config cache** so Laravel uses the new CORS config:
   ```bash
   docker compose exec app php artisan config:clear
   docker compose exec app php artisan config:cache
   ```
   (If you don’t use Docker, run these in the Laravel app directory.)

After that, requests from `http://localhost:60094` (or any localhost port) to `https://esign.sadcpf.org/api/*` and `/sanctum/csrf-cookie` should be allowed by CORS. If you see **502** on login, ensure the app is healthy and config is cached; 502 can occur briefly during deploys.

### Why did the app container restart?

502s in Nginx logs often appear **during the startup window**: the app container has restarted, the entrypoint (sync, cache, migrate) is still running, and PHP-FPM / Reverb are not listening yet — so Nginx gets "connection refused". That does not by itself mean a recurring crash.

To find the **cause** of the restart:

- **Recent restarts:** Run `docker events --filter type=container --filter container=esign_app` (or `docker compose -f docker-compose.prod.yml events`) and look for `die`/`oom` vs. `restart`/`create` to see if the container exited or was recreated manually.
- **Logs from before the restart:** Run `docker compose logs app --since 30m` and look at the **last lines before** the entrypoint output (e.g. "Syncing public assets...", "Starting Supervisor..."). Look for OOM, PHP fatals, or Reverb/worker exits.

### Production build fails at `npm ci`

The production image uses `npm ci --ignore-scripts` in [`docker/production/Dockerfile`](/C:/dev/esign/docker/production/Dockerfile:7), so the deployed `frontend/package.json` and `frontend/package-lock.json` must come from the same commit. If Docker reports that a package is missing from the lock file, verify the server checkout first:

```bash
cd frontend
npm run check:lock-sync
```

If that check fails, regenerate the lock file with `npm install`, commit both files together, redeploy the updated checkout, and rebuild with `docker compose -f docker-compose.prod.yml up -d --build`.

