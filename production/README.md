# Production Deployment Guide

This guide describes how to deploy the secured eSign platform to your production server.

## Architecture

- **Host Nginx**: Acts as the Gateway, handling SSL termination (Quic/HTTP2) and Public IP binding.
- **Docker Nginx** (`127.0.0.1:8000`): Internal reverse proxy for the application.
- **App Services** (Postgres, Redis, MinIO): Locked down in the internal Docker network.

## Prerequisites

1. **Host Nginx** configured with the provided Reverse Proxy block.
2. **Docker & Docker Compose** installed.
3. **SSL Certificates** managed on the host.

## Deployment Steps

### 1. Prepare Environment

Navigate to the production directory:
```bash
cd production
```

Create your `.env` file from the example:
```bash
cp .env.production.example .env
```

> **Note:** All internal keys (DB, Redis, MinIO, App Key) have been pre-generated for you.
> **Action Required:** Update `MAIL_*` settings with your actual SMTP credentials.

### 2. Start Services

Before rebuilding, verify the frontend manifest and lock file in the deployed checkout are in sync:
```bash
cd ../frontend
npm run check:lock-sync
cd ..
```

Run the application stack:
```bash
docker-compose up -d --build
```

After boot, force a clean runtime so stale frontend chunks do not survive:
```bash
docker compose down
docker compose up -d --build
docker compose ps
```

### 3. Verify Deployment

Check that services are running:
```bash
docker-compose ps
```
*Expected:*
- `esign_nginx`: Up (127.0.0.1:8000->80/tcp)
- `esign_app`: Up
- `esign_postgres`: Up (market as healthy)
- `esign_redis`: Up (marked as healthy)
- `esign_minio`: Up (127.0.0.1:9001->9001/tcp)

### 4. Admin Access (Optional)

To access the internal tools (like MinIO Console) from your local machine, use an SSH Tunnel:
```bash
ssh -L 9001:127.0.0.1:9001 user@your-server-ip
```
Then open `http://localhost:9001` in your browser.

## Maintenance

- **Logs**: `docker-compose logs -f app`
- **Update**: `git pull && docker-compose build && docker-compose up -d`
- **Frontend dependency drift**: If the production build fails in the frontend stage, run `cd frontend && npm run check:lock-sync`. If it reports a mismatch, regenerate the lock file with `npm install`, commit both `package.json` and `package-lock.json`, redeploy, then rebuild.

## Post-Deploy Verification Checklist

Run these checks after every deploy that touches upload, prepare, or realtime behavior:

1. **Container/runtime parity**
   - `docker compose ps` shows `esign_app` and `esign_nginx` as `Up`.
   - `docker compose logs --since 5m app` has no repeated crash loop for php-fpm, queue, or reverb.

2. **Bundle freshness check**
   - Open browser DevTools -> Network and hard-reload with cache disabled.
   - Confirm `/prepare/:id` page JavaScript chunks are fetched fresh (status 200/304, not stale from old service worker/chunk map).
   - Confirm timeout text is the new stage-aware variant, not legacy `(x/60)` wording.

3. **Upload -> Prepare smoke test**
   - Upload a 3-page PDF from `/upload`.
   - Confirm no 503 on `/upload` page load.
   - Confirm prepare view progresses and preview renders.
   - Confirm old message `Document is taking too long to process. Please try again later.` is not shown.

4. **Realtime non-blocking check**
   - If WebSocket fails, ensure document list/load/prepare still works via API polling.
   - Realtime errors should not block preview loading.

## Rollback Guardrails

If 5xx increases after deploy:

1. Identify previous image:
   - `docker images | rg esign-app-prod`
2. Roll back app image (example tag placeholder):
   - `docker compose down`
   - update image tag to previous known-good
   - `docker compose up -d`
3. Validate:
   - `/upload` loads without 503
   - upload -> prepare -> preview works
   - queue/reverb logs stable for at least 10 minutes

### Intermittent 502 / Connection refused

If Nginx reports `Connection refused` to `fastcgi://...:9000` or `http://...:8080`, the app container (PHP-FPM or Reverb) is down or restarting. If you see 502 on login (e.g. from Flutter web on localhost), ensure the app is healthy and config is cached; 502 can occur briefly during deploys.

1. **Restart the app:** `docker compose restart app`
2. **Check logs:** `docker compose logs -f app` for PHP errors or OOM.
3. **Ensure restart policy:** In `docker-compose.yml`, the `app` service should have `restart: unless-stopped` so it recovers after a crash.

### Why did the app container restart?

502s often occur **during the startup window** (entrypoint running before Supervisor starts). To see why the container restarted:

- **Events:** `docker events --filter type=container --filter container=esign_app` (or `docker compose events`) — look for `die`/`oom` vs. `restart`/`create`.
- **Logs before restart:** `docker compose logs app --since 30m` — check the last lines **before** the entrypoint output (e.g. "Syncing public assets...") for OOM, PHP fatals, or Reverb/worker exits.
