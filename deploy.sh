#!/bin/bash

# ===========================================
# Tamironline Panel - Deploy Script
# ===========================================

set -e

echo "🚀 Tamironline Panel Deployment"
echo "================================"

# Check if .env.prod exists
if [ ! -f ".env.prod" ]; then
    echo "❌ Error: .env.prod file not found!"
    echo "   Copy the template and configure it."
    exit 1
fi

# Load environment variables
export $(cat .env.prod | grep -v '^#' | xargs)

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" ]; then
    echo "🔑 Generating APP_KEY..."
    APP_KEY=$(docker run --rm php:8.4-cli php -r "echo 'base64:' . base64_encode(random_bytes(32));")
    sed -i "s|APP_KEY=.*|APP_KEY=$APP_KEY|" .env.prod
    export APP_KEY
    echo "   APP_KEY generated and saved to .env.prod"
fi

echo ""
echo "📋 Configuration:"
echo "   URL: $APP_URL"
echo ""

# Build and start
echo "🔨 Building Docker images..."
docker compose -f docker-compose.prod.yml build

echo ""
echo "🚀 Starting services..."
docker compose -f docker-compose.prod.yml up -d

echo ""
echo "⏳ Waiting for services to be ready..."
sleep 15

echo ""
echo "================================"
echo "✅ Deployment complete!"
echo ""
echo "📍 Access your panel at: $APP_URL"
echo ""
echo "📝 Useful commands:"
echo "   View logs:    docker compose -f docker-compose.prod.yml logs -f app"
echo "   Stop:         docker compose -f docker-compose.prod.yml down"
echo "   Restart:      docker compose -f docker-compose.prod.yml restart app"
echo "   DB Shell:     docker compose -f docker-compose.prod.yml exec db mysql -u tamironline -p"
echo ""
