#!/bin/bash

#===========================================
# CRM Hostlino - Deploy Script
#===========================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
APP_DIR=$(dirname "$(readlink -f "$0")")
BRANCH="${1:-claude/review-codebase-2dzJD}"
PHP_PATH="${PHP_PATH:-php}"
COMPOSER_PATH="${COMPOSER_PATH:-composer}"

echo -e "${BLUE}=========================================${NC}"
echo -e "${BLUE}   CRM Hostlino - Deploy Script${NC}"
echo -e "${BLUE}=========================================${NC}"
echo ""

cd "$APP_DIR"

# Step 1: Enable maintenance mode
echo -e "${YELLOW}[1/8] Enabling maintenance mode...${NC}"
$PHP_PATH artisan down --message="در حال بروزرسانی سیستم..." --retry=60 || true

# Step 2: Pull latest changes
echo -e "${YELLOW}[2/8] Pulling latest changes from git...${NC}"
git fetch origin "$BRANCH"
git checkout "$BRANCH"
git pull origin "$BRANCH"

# Step 3: Install composer dependencies
echo -e "${YELLOW}[3/8] Installing composer dependencies...${NC}"
$COMPOSER_PATH install --no-dev --optimize-autoloader --no-interaction

# Step 4: Run database migrations
echo -e "${YELLOW}[4/8] Running database migrations...${NC}"
$PHP_PATH artisan migrate --force

# Step 5: Clear all caches
echo -e "${YELLOW}[5/8] Clearing caches...${NC}"
$PHP_PATH artisan config:clear
$PHP_PATH artisan cache:clear
$PHP_PATH artisan view:clear
$PHP_PATH artisan route:clear

# Step 6: Rebuild caches for production
echo -e "${YELLOW}[6/8] Building production caches...${NC}"
$PHP_PATH artisan config:cache
$PHP_PATH artisan route:cache
$PHP_PATH artisan view:cache

# Step 7: Restart queue workers (if using supervisor)
echo -e "${YELLOW}[7/8] Restarting queue workers...${NC}"
$PHP_PATH artisan queue:restart || true

# Check if supervisor is available and restart
if command -v supervisorctl &> /dev/null; then
    echo -e "${YELLOW}Restarting supervisor processes...${NC}"
    sudo supervisorctl reread || true
    sudo supervisorctl update || true
    sudo supervisorctl restart all || true
fi

# Step 8: Disable maintenance mode
echo -e "${YELLOW}[8/8] Disabling maintenance mode...${NC}"
$PHP_PATH artisan up

echo ""
echo -e "${GREEN}=========================================${NC}"
echo -e "${GREEN}   Deploy completed successfully!${NC}"
echo -e "${GREEN}=========================================${NC}"
echo ""
echo -e "Branch: ${BLUE}$BRANCH${NC}"
echo -e "Time: ${BLUE}$(date)${NC}"
echo ""
