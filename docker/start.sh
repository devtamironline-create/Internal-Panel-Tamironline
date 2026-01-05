#!/bin/sh
set -e

echo "🚀 Starting CRM Hostlino..."

# Wait for database
echo "⏳ Waiting for database..."
while ! mysqladmin ping -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" --silent 2>/dev/null; do
    sleep 2
done
echo "✅ Database is ready!"

# Run migrations
echo "📦 Running migrations..."
php artisan migrate --force

# Clear and cache config
echo "🔧 Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage link
php artisan storage:link 2>/dev/null || true

# Set permissions
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache

echo "✅ Application is ready!"
echo "🌐 Starting services..."

# Start supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
