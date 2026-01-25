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

Run the application stack:
```bash
docker-compose up -d --build
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
