#!/bin/bash

# =====================================================
# SSH Deployment Script for Internal Panel Tamironline
# =====================================================

# Server Configuration
SSH_HOST="45.11.185.148"
SSH_USER="panel"
SSH_PORT="45450"
DEPLOY_PATH="public_html"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}   Starting Deployment Process${NC}"
echo -e "${YELLOW}========================================${NC}"

# Check if sshpass is installed
if ! command -v sshpass &> /dev/null; then
    echo -e "${RED}sshpass is not installed. Install it with: sudo apt install sshpass${NC}"
    exit 1
fi

# Ask for password
echo -e "${YELLOW}Enter SSH password:${NC}"
read -s SSH_PASSWORD

echo -e "${GREEN}[1/5] Building assets...${NC}"
npm run build 2>/dev/null || echo "Skipping npm build"

echo -e "${GREEN}[2/5] Creating deployment package...${NC}"
# Create a clean deployment package
rm -f deploy.tar.gz
tar --exclude='.git' \
    --exclude='node_modules' \
    --exclude='.env' \
    --exclude='tests' \
    --exclude='.github' \
    --exclude='*.log' \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    -czf deploy.tar.gz .

echo -e "${GREEN}[3/5] Uploading to server...${NC}"
sshpass -p "$SSH_PASSWORD" scp -P $SSH_PORT deploy.tar.gz ${SSH_USER}@${SSH_HOST}:~/deploy.tar.gz

echo -e "${GREEN}[4/5] Deploying on server...${NC}"
sshpass -p "$SSH_PASSWORD" ssh -p $SSH_PORT ${SSH_USER}@${SSH_HOST} << 'ENDSSH'
    cd ~

    # Backup .env and .htaccess (these should NEVER be overwritten)
    echo "Backing up protected files..."
    if [ -f public_html/.env ]; then cp public_html/.env ~/env_backup; fi
    if [ -f public_html/.htaccess ]; then cp public_html/.htaccess ~/htaccess_backup; fi
    if [ -f public_html/public/.htaccess ]; then cp public_html/public/.htaccess ~/public_htaccess_backup; fi

    # Extract new files
    cd public_html
    tar -xzf ~/deploy.tar.gz
    rm ~/deploy.tar.gz

    # Restore protected files
    echo "Restoring protected files..."
    if [ -f ~/env_backup ]; then mv ~/env_backup .env; fi
    if [ -f ~/htaccess_backup ]; then mv ~/htaccess_backup .htaccess; fi
    if [ -f ~/public_htaccess_backup ]; then mv ~/public_htaccess_backup public/.htaccess; fi

    # Set permissions
    chmod -R 755 storage bootstrap/cache

    # Run Laravel commands
    php artisan migrate --force 2>/dev/null || true
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan storage:link 2>/dev/null || true

    echo "Server deployment completed!"
ENDSSH

echo -e "${GREEN}[5/5] Cleaning up...${NC}"
rm -f deploy.tar.gz

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}   Deployment Completed Successfully!${NC}"
echo -e "${GREEN}========================================${NC}"
