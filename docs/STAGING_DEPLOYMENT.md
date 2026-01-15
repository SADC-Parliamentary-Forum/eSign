# Staging Deployment - Step-by-Step Guide

## Overview

This guide will walk you through deploying the eSign platform to a staging environment for testing before production.

**Estimated Time:** 2-3 hours  
**Difficulty:** Intermediate

---

## Prerequisites

### Server Requirements

**Recommended Specs:**
- **Provider:** AWS EC2, DigitalOcean, Linode, or similar
- **Instance Type:** t3.medium (2 vCPU, 4GB RAM) or equivalent
- **OS:** Ubuntu 22.04 LTS
- **Storage:** 40GB SSD
- **Network:** Public IP address

### Local Requirements

- Git installed
- SSH client
- Access to domain DNS settings (for subdomain)

---

## Step 1: Server Provisioning

### Option A: DigitalOcean (Recommended for Staging)

1. **Create Droplet**
   - Log in to DigitalOcean
   - Click "Create" → "Droplets"
   - Choose **Ubuntu 22.04 LTS**
   - Select **Basic** plan
   - Choose **Regular** CPU: $24/month (2 vCPU, 4GB RAM)
   - Select datacenter region (closest to users)
   - Add SSH key (create one if needed)
   - Hostname: `esign-staging`
   - Click "Create Droplet"

2. **Note IP Address**
   ```
   Droplet IP: 123.456.789.012
   ```

### Option B: AWS EC2

1. **Launch Instance**
   - EC2 Console → "Launch Instance"
   - Name: `esign-staging`
   - AMI: Ubuntu Server 22.04 LTS
   - Instance type: `t3.medium`
   - Key pair: Create or select existing
   - Network: Default VPC, Auto-assign public IP
   - Storage: 40GB gp3
   - Security group: Create new (we'll configure below)
   - Launch instance

2. **Configure Security Group**
   ```
   Inbound Rules:
   - SSH (22) - Your IP
   - HTTP (80) - 0.0.0.0/0
   - HTTPS (443) - 0.0.0.0/0
   - WebSocket (8080) - 0.0.0.0/0
   ```

---

## Step 2: Domain Configuration

### Set Up Subdomain

1. **Access DNS Provider** (e.g., Cloudflare, Route53, Namecheap)

2. **Add A Record**
   ```
   Type: A
   Name: staging
   Value: YOUR_SERVER_IP
   TTL: 300 (5 minutes)
   ```

3. **Add CNAME for WebSocket (optional)**
   ```
   Type: CNAME
   Name: ws.staging
   Value: staging.esign.yourdomain.com
   TTL: 300
   ```

4. **Wait for DNS Propagation** (5-15 minutes)
   ```bash
   # Test DNS propagation
   nslookup staging.esign.yourdomain.com
   ```

---

## Step 3: Server Initial Setup

### Connect to Server

```bash
# Replace with your server IP and key
ssh -i ~/.ssh/your_key.pem ubuntu@YOUR_SERVER_IP
```

### Run Setup Script

Save this as `staging-setup.sh` and run on the server:

```bash
#!/bin/bash
set -e

echo "========================================="
echo "  eSign Platform - Staging Setup"
echo "========================================="

# Update system
echo "Updating system packages..."
sudo apt-get update
sudo apt-get upgrade -y

# Install dependencies
echo "Installing dependencies..."
sudo apt-get install -y \
    ca-certificates \
    curl \
    gnupg \
    lsb-release \
    git \
    ufw \
    fail2ban

# Install Docker
echo "Installing Docker..."
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER

# Install Docker Compose
echo "Installing Docker Compose..."
sudo curl -L "https://github.com/docker/compose/releases/download/v2.24.0/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Configure firewall
echo "Configuring firewall..."
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp   # SSH
sudo ufw allow 80/tcp   # HTTP
sudo ufw allow 443/tcp  # HTTPS
sudo ufw allow 8080/tcp # WebSocket
sudo ufw --force enable

# Configure fail2ban
echo "Configuring fail2ban..."
sudo systemctl enable fail2ban
sudo systemctl start fail2ban

# Create deploy user
echo "Creating deploy user..."
sudo adduser --disabled-password --gecos "" deploy
sudo usermod -aG docker deploy
sudo usermod -aG sudo deploy

# Set up project directory
echo "Setting up project directory..."
sudo mkdir -p /var/www/esign-platform
sudo chown -R deploy:deploy /var/www/esign-platform

echo "Setup complete! Please logout and login again for group changes to take effect."
echo "Next: Switch to deploy user with 'sudo su - deploy'"
```

### Run the Script

```bash
# Make executable
chmod +x staging-setup.sh

# Run
./staging-setup.sh

# Logout and login again
exit
ssh -i ~/.ssh/your_key.pem ubuntu@YOUR_SERVER_IP

# Switch to deploy user
sudo su - deploy
```

---

## Step 4: Clone and Configure Project

### Clone Repository

```bash
cd /var/www
git clone https://github.com/your-org/esign-platform.git
cd esign-platform
git checkout develop  # Use develop branch for staging
```

### Create Environment Files

**Backend Environment:**

```bash
cd backend
cp .env.example .env
nano .env
```

**Configuration:** (Press Ctrl+X, Y, Enter to save)

```env
APP_NAME="eSign Platform (Staging)"
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://staging.esign.yourdomain.com

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=sadc_esign
DB_USERNAME=sadc_esign
DB_PASSWORD=GENERATE_SECURE_PASSWORD_HERE

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=redis
REDIS_PORT=6379

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=GENERATE_SECURE_PASSWORD_HERE
AWS_BUCKET=documents
AWS_ENDPOINT=http://minio:9000
AWS_USE_PATH_STYLE_ENDPOINT=true

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_mailtrap_username
MAIL_PASSWORD=your_mailtrap_password
MAIL_FROM_ADDRESS=noreply@staging.esign.yourdomain.com

BROADCAST_DRIVER=reverb
REVERB_APP_ID=esign-staging
REVERB_APP_KEY=GENERATE_KEY_HERE
REVERB_APP_SECRET=GENERATE_SECRET_HERE

FRONTEND_URL=https://staging.esign.yourdomain.com
```

**Frontend Environment:**

```bash
cd ../frontend
cp .env.example .env
nano .env
```

```env
VITE_API_URL=https://staging.esign.yourdomain.com/api
VITE_APP_URL=https://staging.esign.yourdomain.com
VITE_REVERB_APP_KEY=SAME_AS_BACKEND_REVERB_APP_KEY
VITE_REVERB_HOST=staging.esign.yourdomain.com
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=wss
VITE_APP_ENV=staging
```

### Generate Secrets

```bash
cd /var/www/esign-platform/backend

# Generate APP_KEY
docker run --rm -v $(pwd):/app -w /app php:8.2-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"

# Generate REVERB keys
openssl rand -base64 32  # Use for REVERB_APP_KEY
openssl rand -base64 64  # Use for REVERB_APP_SECRET

# Generate DB_PASSWORD
openssl rand -base64 32

# Generate AWS_SECRET_ACCESS_KEY
openssl rand -base64 32
```

Update `.env` files with generated values.

---

## Step 5: SSL Certificate Setup

### Install Certbot

```bash
sudo apt-get install -y certbot python3-certbot-nginx
```

### Obtain Certificate

```bash
# Stop any running services on port 80
sudo systemctl stop nginx || true

# Obtain certificate
sudo certbot certonly --standalone -d staging.esign.yourdomain.com

# Certificates saved to:
# /etc/letsencrypt/live/staging.esign.yourdomain.com/fullchain.pem
# /etc/letsencrypt/live/staging.esign.yourdomain.com/privkey.pem
```

### Copy Certificates

```bash
sudo mkdir -p /var/www/esign-platform/docker/ssl
sudo cp /etc/letsencrypt/live/staging.esign.yourdomain.com/fullchain.pem /var/www/esign-platform/docker/ssl/
sudo cp /etc/letsencrypt/live/staging.esign.yourdomain.com/privkey.pem /var/www/esign-platform/docker/ssl/
sudo chown -R deploy:deploy /var/www/esign-platform/docker/ssl
```

---

## Step 6: Start Application

### Build and Start Containers

```bash
cd /var/www/esign-platform

# Build images
docker-compose build

# Start services
docker-compose up -d

# Wait for services to be healthy
sleep 30

# Check status
docker-compose ps
```

**Expected Output:** All services should be "Up" and "healthy"

### Initialize Application

```bash
# Install backend dependencies
docker-compose exec app composer install --no-dev --optimize-autoloader

# Generate application key (if not set)
docker-compose exec app php artisan key:generate

# Run migrations
docker-compose exec app php artisan migrate --force

# Create storage link
docker-compose exec app php artisan storage:link

# Cache configuration
docker-compose exec app php artisan config:cache
docker-compose exec app php artisan route:cache
docker-compose exec app php artisan view:cache

# Install frontend dependencies
docker-compose exec app npm install --prefix frontend

# Build frontend
docker-compose exec app npm run build --prefix frontend
```

### Seed Test Data (Optional)

```bash
docker-compose exec app php artisan db:seed --class=DemoSeeder
```

### Start Background Services

```bash
# Queue worker
docker-compose exec -d app php artisan queue:work --tries=3 --timeout=300

# Reverb WebSocket
docker-compose exec -d app php artisan reverb:start --host=0.0.0.0 --port=8080
```

---

## Step 7: Verification

### Health Checks

```bash
# Test API
curl https://staging.esign.yourdomain.com/api/health

# Expected: {"status":"healthy","timestamp":"..."}

# Test frontend
curl -I https://staging.esign.yourdomain.com

# Expected: HTTP/2 200

# Test WebSocket
nc -zv staging.esign.yourdomain.com 8080

# Expected: Connection succeeded
```

### Browser Tests

1. **Open Frontend**
   - Navigate to: `https://staging.esign.yourdomain.com`
   - Verify homepage loads
   - No console errors

2. **Create Account**
   - Click "Sign Up"
   - Create test account
   - Verify email sent (check Mailpit: `http://YOUR_SERVER_IP:8025`)

3. **Upload Document**
   - Log in
   - Upload test PDF
   - Select signature level
   - Add signer
   - Send

4. **WebSocket Test**
   - Open browser console
   - Check for WebSocket connection: `ws://staging.esign.yourdomain.com:8080`
   - Should show "connected"

### Monitoring

```bash
# View logs
docker-compose logs -f app
docker-compose logs -f nginx

# Check resource usage
docker stats

# Check disk space
df -h
```

---

## Step 8: Configure GitHub Actions

### Create Deploy SSH Key

```bash
# On your local machine
ssh-keygen -t ed25519 -C "github-actions-staging" -f ~/.ssh/github_actions_staging

# Copy public key to staging server
ssh-copy-id -i ~/.ssh/github_actions_staging.pub deploy@staging.esign.yourdomain.com

# Copy private key content
cat ~/.ssh/github_actions_staging
```

### Add GitHub Secrets

1. Go to GitHub repository → Settings → Secrets → Actions
2. Add these secrets:

```
STAGING_HOST = staging.esign.yourdomain.com
STAGING_USER = deploy
STAGING_SSH_KEY = [paste private key from above]
DOCKER_HUB_USERNAME = your_dockerhub_username
DOCKER_HUB_TOKEN = your_dockerhub_token
```

### Test CI/CD

```bash
# On your local machine
git checkout develop
git commit --allow-empty -m "Test staging deployment"
git push origin develop

# Watch GitHub Actions
# https://github.com/your-org/esign-platform/actions
```

---

## Step 9: Monitoring Setup

### Application Monitoring

Access Laravel Telescope:
```
https://staging.esign.yourdomain.com/telescope
```

### System Monitoring

```bash
# Install Netdata (optional)
sudo apt-get install -y netdata

# Configure firewall
sudo ufw allow 19999/tcp

# Access: http://YOUR_SERVER_IP:19999
```

### Log Viewing

```bash
# Application logs
docker-compose exec app tail -f storage/logs/laravel.log

# Nginx logs
docker-compose exec nginx tail -f /var/log/nginx/access.log
docker-compose exec nginx tail -f /var/log/nginx/error.log
```

---

## Step 10: Backup Configuration

### Automated Backups

Create backup script: `/var/www/esign-platform/scripts/backup-staging.sh`

```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/www/backups"

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup database
docker-compose exec -T postgres pg_dump -U sadc_esign sadc_esign > $BACKUP_DIR/db_$DATE.sql
gzip $BACKUP_DIR/db_$DATE.sql

# Backup MinIO data
docker-compose exec minio mc mirror /data $BACKUP_DIR/minio_$DATE/

# Keep only last 7 days
find $BACKUP_DIR -name "db_*.sql.gz" -mtime +7 -delete
find $BACKUP_DIR -name "minio_*" -mtime +7 -exec rm -rf {} \;

echo "Backup completed: $DATE"
```

### Schedule Backups

```bash
# Make executable
chmod +x /var/www/esign-platform/scripts/backup-staging.sh

# Add to crontab
crontab -e

# Add this line (daily at 2 AM)
0 2 * * * /var/www/esign-platform/scripts/backup-staging.sh >> /var/log/backup-staging.log 2>&1
```

---

## Step 11: Testing Checklist

Run through all test scenarios from `TESTING_GUIDE.md`:

- [ ] SIMPLE signature flow
- [ ] ADVANCED signature flow
- [ ] QUALIFIED signature flow
- [ ] Template creation
- [ ] Real-time notifications
- [ ] Mobile responsiveness
- [ ] Performance test (Lighthouse)
- [ ] Security test (session timeout)

---

## Troubleshooting

### Issue: Cannot Connect to Server

```bash
# Check server status
ssh ubuntu@YOUR_SERVER_IP

# Check firewall
sudo ufw status

# Check if Docker is running
docker ps
```

### Issue: SSL Certificate Error

```bash
# Verify certificate
sudo certbot certificates

# Renew if needed
sudo certbot renew

# Update Docker SSL files
sudo cp /etc/letsencrypt/live/staging.esign.yourdomain.com/* /var/www/esign-platform/docker/ssl/
docker-compose restart nginx
```

### Issue: Database Migration Failed

```bash
# Check PostgreSQL
docker-compose exec postgres psql -U sadc_esign -d sadc_esign -c "SELECT 1;"

# Reset database (WARNING: Deletes all data)
docker-compose exec app php artisan migrate:fresh --force
```

### Issue: Frontend Not Building

```bash
# Check build logs
docker-compose exec app npm run build --prefix frontend

# Verify .env file
cat frontend/.env

# Clear cache
docker-compose exec app npm cache clean --force --prefix frontend
```

---

## Maintenance

### Update Application

```bash
cd /var/www/esign-platform
git pull origin develop
docker-compose build
docker-compose up -d
docker-compose exec app composer install --no-dev
docker-compose exec app php artisan migrate --force
docker-compose exec app npm run build --prefix frontend
docker-compose exec app php artisan config:cache
```

### Monitor Resource Usage

```bash
# Check disk space
df -h

# Check memory
free -h

# Check Docker resources
docker stats
```

### Rotate Logs

```bash
# Configure logrotate
sudo nano /etc/logrotate.d/esign
```

```
/var/www/esign-platform/backend/storage/logs/*.log {
    daily
    rotate 14
    compress
    missingok
    notifempty
}
```

---

## Success Criteria

✅ **Staging deployment successful when:**

- [ ] Frontend accessible at https://staging.esign.yourdomain.com
- [ ] API health check returns 200 OK
- [ ] WebSocket connection established
- [ ] Can create account and login
- [ ] Can upload and sign document (all 3 levels)
- [ ] Email delivery working (Mailpit or SMTP)
- [ ] Real-time notifications functioning
- [ ] Evidence package generation working
- [ ] Mobile responsive
- [ ] SSL certificate valid
- [ ] Automated backups running
- [ ] CI/CD pipeline deploying successfully

---

## Next Steps

After staging validation:

1. **User Acceptance Testing (UAT)** - Invite team to test
2. **Performance Testing** - Load testing with realistic data
3. **Security Audit** - Run security scans
4. **Documentation Review** - Update any gaps
5. **Production Deployment** - Use same process for production

---

**Staging Environment Ready! 🚀**

**URL:** https://staging.esign.yourdomain.com  
**Admin:** /telescope  
**Mailpit:** http://YOUR_SERVER_IP:8025
