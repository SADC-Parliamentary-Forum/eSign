#!/bin/bash
#
# eSign Platform - Quick Staging Deploy Script
# Run this on your LOCAL machine after server is provisioned
#

set -e

echo "========================================="
echo "  eSign Platform - Staging Deployment"
echo "========================================="
echo ""

# Configuration
read -p "Enter staging server IP: " SERVER_IP
read -p "Enter domain (e.g., staging.esign.yourdomain.com): " DOMAIN
read -p "Enter GitHub repository URL: " REPO_URL
read -p "Enter your email for SSL certificate: " CERT_EMAIL

echo ""
echo "Configuration:"
echo "  Server IP: $SERVER_IP"
echo "  Domain: $DOMAIN"
echo "  Repository: $REPO_URL"
echo "  Email: $CERT_EMAIL"
echo ""
read -p "Continue? (y/n) " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]
then
    exit 1
fi

# Generate SSH key for GitHub Actions
echo "Generating SSH key for GitHub Actions..."
ssh-keygen -t ed25519 -C "github-actions-staging" -f ~/.ssh/github_actions_staging -N ""

# Copy server setup script
echo "Creating server setup script..."
cat > /tmp/server-setup.sh << 'SERVERSCRIPT'
#!/bin/bash
set -e

# Update system
apt-get update
apt-get upgrade -y

# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sh get-docker.sh
usermod -aG docker ubuntu

# Install Docker Compose
curl -L "https://github.com/docker/compose/releases/download/v2.24.0/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
chmod +x /usr/local/bin/docker-compose

# Install dependencies
apt-get install -y git ufw fail2ban certbot

# Configure firewall
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 8080/tcp
ufw --force enable

# Create deploy user
adduser --disabled-password --gecos "" deploy
usermod -aG docker deploy
usermod -aG sudo deploy

# Set up project directory
mkdir -p /var/www/esign-platform
chown -R deploy:deploy /var/www

echo "Server setup complete!"
SERVERSCRIPT

# Upload and run server setup
echo "Setting up server..."
scp /tmp/server-setup.sh ubuntu@$SERVER_IP:/tmp/
ssh ubuntu@$SERVER_IP "sudo bash /tmp/server-setup.sh"

# Copy SSH key to deploy user
echo "Configuring deploy user..."
ssh-copy-id -i ~/.ssh/github_actions_staging.pub deploy@$SERVER_IP

# Clone repository
echo "Cloning repository..."
ssh deploy@$SERVER_IP << SSHCOMMANDS
cd /var/www
git clone $REPO_URL esign-platform
cd esign-platform
git checkout develop
SSHCOMMANDS

# Generate secrets
echo "Generating secrets..."
APP_KEY=$(openssl rand -base64 32)
DB_PASSWORD=$(openssl rand -base64 32)
MINIO_SECRET=$(openssl rand -base64 32)
REVERB_KEY=$(openssl rand -base64 32)
REVERB_SECRET=$(openssl rand -base64 64)

# Create backend .env
echo "Creating backend .env..."
ssh deploy@$SERVER_IP << ENVBACKEND
cd /var/www/esign-platform/backend
cat > .env << 'EOF'
APP_NAME="eSign Platform (Staging)"
APP_ENV=staging
APP_KEY=base64:$APP_KEY
APP_DEBUG=false
APP_URL=https://$DOMAIN

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=sadc_esign
DB_USERNAME=sadc_esign
DB_PASSWORD=$DB_PASSWORD

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=redis
REDIS_PORT=6379

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=$MINIO_SECRET
AWS_BUCKET=documents
AWS_ENDPOINT=http://minio:9000
AWS_USE_PATH_STYLE_ENDPOINT=true

BROADCAST_DRIVER=reverb
REVERB_APP_ID=esign-staging
REVERB_APP_KEY=$REVERB_KEY
REVERB_APP_SECRET=$REVERB_SECRET

FRONTEND_URL=https://$DOMAIN
EOF
ENVBACKEND

# Create frontend .env
echo "Creating frontend .env..."
ssh deploy@$SERVER_IP << ENVFRONTEND
cd /var/www/esign-platform/frontend
cat > .env << 'EOF'
VITE_API_URL=https://$DOMAIN/api
VITE_APP_URL=https://$DOMAIN
VITE_REVERB_APP_KEY=$REVERB_KEY
VITE_REVERB_HOST=$DOMAIN
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=wss
VITE_APP_ENV=staging
EOF
ENVFRONTEND

# Obtain SSL certificate
echo "Obtaining SSL certificate..."
ssh ubuntu@$SERVER_IP "sudo certbot certonly --standalone -d $DOMAIN --email $CERT_EMAIL --agree-tos --non-interactive"

# Copy SSL certificates
ssh ubuntu@$SERVER_IP << SSLCOPY
sudo mkdir -p /var/www/esign-platform/docker/ssl
sudo cp /etc/letsencrypt/live/$DOMAIN/fullchain.pem /var/www/esign-platform/docker/ssl/
sudo cp /etc/letsencrypt/live/$DOMAIN/privkey.pem /var/www/esign-platform/docker/ssl/
sudo chown -R deploy:deploy /var/www/esign-platform/docker/ssl
SSLCOPY

# Start application
echo "Starting application..."
ssh deploy@$SERVER_IP << APPSTART
cd /var/www/esign-platform
docker-compose build
docker-compose up -d
sleep 30
docker-compose exec -T app composer install --no-dev --optimize-autoloader
docker-compose exec -T app php artisan key:generate
docker-compose exec -T app php artisan migrate --force
docker-compose exec -T app php artisan storage:link
docker-compose exec -T app php artisan config:cache
docker-compose exec -T app npm install --prefix frontend
docker-compose exec -T app npm run build --prefix frontend
docker-compose exec -d app php artisan queue:work --tries=3 --timeout=300
docker-compose exec -d app php artisan reverb:start --host=0.0.0.0 --port=8080
APPSTART

echo ""
echo "========================================="
echo "  Deployment Complete!"
echo "========================================="
echo ""
echo "Staging URL: https://$DOMAIN"
echo "API Health: https://$DOMAIN/api/health"
echo ""
echo "GitHub Secrets to add:"
echo "  STAGING_HOST: $DOMAIN"
echo "  STAGING_USER: deploy"
echo "  STAGING_SSH_KEY: (content below)"
echo ""
echo "--- SSH Private Key ---"
cat ~/.ssh/github_actions_staging
echo "--- End SSH Private Key ---"
echo ""
echo "Next steps:"
echo "1. Add GitHub secrets (see above)"
echo "2. Test deployment: git push origin develop"
echo "3. Access staging: https://$DOMAIN"
echo ""
