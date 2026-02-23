#!/bin/bash
# Database Backup Script with Encryption
# Security: Encrypts backups with AES-256 to protect sensitive data

set -e

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="backups/db"
BACKUP_KEY_FILE="${BACKUP_KEY_FILE:-/run/secrets/backup_encryption_key}"
ENCRYPT_BACKUPS="${ENCRYPT_BACKUPS:-true}"

mkdir -p "$BACKUP_DIR"

echo "[$(date)] Starting database backup..."

# Create backup
BACKUP_FILE="$BACKUP_DIR/db_$DATE.sql"
docker-compose exec -T postgres pg_dump -U sadc_esign sadc_esign > "$BACKUP_FILE"

if [ ! -s "$BACKUP_FILE" ]; then
    echo "[$(date)] ERROR: Backup file is empty!"
    rm -f "$BACKUP_FILE"
    exit 1
fi

# Compress backup
gzip "$BACKUP_FILE"
BACKUP_FILE="$BACKUP_FILE.gz"

# Encrypt if enabled and key file exists
if [ "$ENCRYPT_BACKUPS" = "true" ]; then
    if [ -f "$BACKUP_KEY_FILE" ]; then
        echo "[$(date)] Encrypting backup..."
        openssl enc -aes-256-cbc -salt -pbkdf2 -iter 100000 \
            -in "$BACKUP_FILE" \
            -out "$BACKUP_FILE.enc" \
            -pass file:"$BACKUP_KEY_FILE"

        # Remove unencrypted backup
        rm -f "$BACKUP_FILE"
        BACKUP_FILE="$BACKUP_FILE.enc"
        echo "[$(date)] Backup encrypted: $(basename $BACKUP_FILE)"
    else
        echo "[$(date)] WARNING: Encryption key not found at $BACKUP_KEY_FILE"
        echo "[$(date)] WARNING: Backup saved WITHOUT encryption!"
        echo "[$(date)] To enable encryption, create a key file or set BACKUP_KEY_FILE environment variable"
    fi
else
    echo "[$(date)] Encryption disabled. Backup saved without encryption."
fi

# Calculate checksum for integrity verification
sha256sum "$BACKUP_FILE" > "$BACKUP_FILE.sha256"

echo "[$(date)] Backup completed: $(basename $BACKUP_FILE)"
echo "[$(date)] Size: $(du -h "$BACKUP_FILE" | cut -f1)"

# Keep only last 30 days of backups
find "$BACKUP_DIR" -name "db_*.sql.gz*" -mtime +30 -delete
find "$BACKUP_DIR" -name "db_*.sha256" -mtime +30 -delete

echo "[$(date)] Old backups cleaned up (keeping 30 days)"
