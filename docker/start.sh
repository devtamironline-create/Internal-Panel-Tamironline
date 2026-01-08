#!/bin/sh
set -e

echo "🚀 Starting CRM Tamironline..."

# Debug: Show environment
echo "📋 Database Config:"
echo "   Host: $DB_HOST"
echo "   Port: $DB_PORT"
echo "   Database: $DB_DATABASE"
echo "   User: $DB_USERNAME"

# Wait for database with timeout
echo "⏳ Waiting for database..."
COUNTER=0
MAX_TRIES=30

while [ $COUNTER -lt $MAX_TRIES ]; do
    if mysqladmin ping -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" --silent 2>/dev/null; then
        echo "✅ Database is ready!"
        break
    fi
    COUNTER=$((COUNTER + 1))
    echo "   Attempt $COUNTER/$MAX_TRIES - Database not ready, waiting..."
    sleep 2
done

if [ $COUNTER -eq $MAX_TRIES ]; then
    echo "⚠️ Warning: Could not connect to database after $MAX_TRIES attempts"
    echo "   Continuing anyway... migrations may fail"
fi

# Run migrations
echo "📦 Running migrations..."
php artisan migrate --force || echo "⚠️ Migration failed or skipped"

# Clear and cache config
echo "🔧 Optimizing application..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Create storage link
php artisan storage:link 2>/dev/null || true

# Set permissions
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache

echo "✅ Application is ready!"
echo "🌐 Starting services..."

# Start supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
