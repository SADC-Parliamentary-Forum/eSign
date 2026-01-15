# GitHub Secrets Configuration Guide

This document lists all required secrets for the CI/CD pipeline.

## Required Secrets

### Docker Hub (for image storage)

```
DOCKER_HUB_USERNAME=your-dockerhub-username
DOCKER_HUB_TOKEN=your-dockerhub-access-token
```

**How to get:**
1. Sign up at https://hub.docker.com
2. Go to Account Settings → Security → New Access Token
3. Copy the token (shown only once)

---

### Staging Environment

```
STAGING_HOST=staging.esign.yourdomain.com
STAGING_USER=deploy
STAGING_SSH_KEY=-----BEGIN OPENSSH PRIVATE KEY-----
...your private key...
-----END OPENSSH PRIVATE KEY-----
```

**How to set up SSH key:**

```bash
# On your local machine
ssh-keygen -t ed25519 -C "github-actions-staging" -f ~/.ssh/github_actions_staging

# Copy public key to staging server
ssh-copy-id -i ~/.ssh/github_actions_staging.pub deploy@staging.esign.yourdomain.com

# Copy private key content to GitHub secret
cat ~/.ssh/github_actions_staging
```

---

### Production Environment

```
PRODUCTION_HOST=esign.yourdomain.com
PRODUCTION_USER=deploy
PRODUCTION_SSH_KEY=-----BEGIN OPENSSH PRIVATE KEY-----
...your private key...
-----END OPENSSH PRIVATE KEY-----
```

**How to set up SSH key:**

```bash
# On your local machine
ssh-keygen -t ed25519 -C "github-actions-production" -f ~/.ssh/github_actions_production

# Copy public key to production server
ssh-copy-id -i ~/.ssh/github_actions_production.pub deploy@esign.yourdomain.com

# Copy private key content to GitHub secret
cat ~/.ssh/github_actions_production
```

---

### Notifications (Optional)

```
SLACK_WEBHOOK=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

**How to get:**
1. Go to https://api.slack.com/apps
2. Create new app
3. Enable Incoming Webhooks
4. Create webhook for your channel
5. Copy webhook URL

---

## Adding Secrets to GitHub

1. Go to your repository on GitHub
2. Click **Settings** → **Secrets and variables** → **Actions**
3. Click **New repository secret**
4. Add each secret with exact name and value

### Example:

**Name:** `STAGING_SSH_KEY`  
**Value:** 
```
-----BEGIN OPENSSH PRIVATE KEY-----
b3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABAAAAMwAAAAtz
c2gtZWQyNTUxOQAAACDj3VKjXGkR5FTdXhH5T7z3kFGHGYPLZXFSH5VKH5VKHQAA
...
-----END OPENSSH PRIVATE KEY-----
```

---

## Environment-Specific Secrets

GitHub supports environment-specific secrets for staging and production.

**To set up:**

1. Go to **Settings** → **Environments**
2. Create environments: `staging` and `production`
3. Add environment-specific secrets
4. Enable required reviewers for production (optional)

---

## Server Setup

### Create deploy user on servers

```bash
# On staging/production server
sudo adduser deploy
sudo usermod -aG docker deploy
sudo mkdir -p /home/deploy/.ssh
sudo chmod 700 /home/deploy/.ssh

# Add GitHub Actions public key
sudo nano /home/deploy/.ssh/authorized_keys
# Paste public key, save

sudo chmod 600 /home/deploy/.ssh/authorized_keys
sudo chown -R deploy:deploy /home/deploy/.ssh

# Test SSH access
ssh deploy@your-server
```

### Grant deploy user permissions

```bash
# Allow deploy user to run docker commands
sudo usermod -aG docker deploy

# Grant permissions to project directory
sudo chown -R deploy:deploy /var/www/esign-platform
```

---

## Testing the Pipeline

### 1. Test SSH Connection

```bash
# From your local machine
ssh -i ~/.ssh/github_actions_staging deploy@staging.esign.yourdomain.com
```

### 2. Trigger Pipeline

```bash
# Push to develop (triggers staging deploy)
git checkout develop
git commit --allow-empty -m "Test CI/CD pipeline"
git push origin develop

# Push to main (triggers production deploy)
git checkout main
git merge develop
git push origin main
```

### 3. Monitor Pipeline

1. Go to GitHub repository → **Actions** tab
2. Click on the running workflow
3. Monitor each job in real-time
4. Check logs for any failures

---

## Troubleshooting

### Issue: SSH Connection Failed

**Solution:**
```bash
# Check SSH key format
cat ~/.ssh/github_actions_staging | head -n 1
# Should show: -----BEGIN OPENSSH PRIVATE KEY-----

# Test SSH manually
ssh -i ~/.ssh/github_actions_staging deploy@staging.esign.yourdomain.com

# Check server logs
sudo tail -f /var/log/auth.log
```

### Issue: Docker Permission Denied

**Solution:**
```bash
# On server, add deploy user to docker group
sudo usermod -aG docker deploy

# Logout and login again
exit
ssh deploy@your-server

# Test docker
docker ps
```

### Issue: Database Migration Failed

**Solution:**
```bash
# On server, check database connection
docker-compose exec app php artisan tinker
>>> DB::connection()->getPdo()

# Run migrations manually
docker-compose exec app php artisan migrate --force
```

---

## Security Best Practices

1. **Rotate SSH keys** every 90 days
2. **Use separate keys** for staging and production
3. **Enable branch protection** for main branch
4. **Require pull request reviews** before merging
5. **Use environment-specific secrets**
6. **Enable required reviewers** for production deployments
7. **Monitor audit logs** in GitHub

---

## Pipeline Workflow

```
┌─────────────────┐
│   Push Code     │
└────────┬────────┘
         │
    ┌────▼────┐
    │  Tests  │ (Backend + Frontend)
    └────┬────┘
         │
    ┌────▼────────┐
    │   Quality   │ (PHPStan, ESLint)
    └────┬────────┘
         │
    ┌────▼─────────┐
    │   Security   │ (Trivy, Audit)
    └────┬─────────┘
         │
    ┌────▼─────────┐
    │ Build Docker │
    └────┬─────────┘
         │
    ┌────▼────────────────┐
    │  Deploy to Staging  │ (develop branch)
    └────┬────────────────┘
         │
    ┌────▼──────────────────┐
    │ Deploy to Production  │ (main branch)
    └───────────────────────┘
```

---

**CI/CD Pipeline Ready! 🚀**
