#!/bin/sh

echo "🚀 Starting CRM Tamironline..."

# Debug: Show environment
echo "📋 Database Config:"
echo "   Host: $DB_HOST"
echo "   Port: ${DB_PORT:-3306}"
echo "   Database: $DB_DATABASE"
echo "   User: $DB_USERNAME"

echo "📋 Redis Config:"
echo "   Host: $REDIS_HOST"
echo "   Port: ${REDIS_PORT:-6379}"

# Wait for database using PHP (more reliable than mysqladmin)
echo "⏳ Waiting for database..."
COUNTER=0
MAX_TRIES=30

while [ $COUNTER -lt $MAX_TRIES ]; do
    if php -r "try { new PDO('mysql:host='.\$_SERVER['DB_HOST'].';port='.(\$_SERVER['DB_PORT']??3306), \$_SERVER['DB_USERNAME'], \$_SERVER['DB_PASSWORD']); echo 'OK'; exit(0); } catch(Exception \$e) { exit(1); }" 2>/dev/null; then
        echo "✅ Database is ready!"
        break
    fi
    COUNTER=$((COUNTER + 1))
    echo "   Attempt $COUNTER/$MAX_TRIES - Database not ready, waiting..."
    sleep 2
done

if [ $COUNTER -eq $MAX_TRIES ]; then
    echo "⚠️ Warning: Could not connect to database after $MAX_TRIES attempts"
fi

# Run migrations
echo "📦 Running migrations..."
php artisan migrate --force 2>&1 || echo "⚠️ Migration completed with warnings"

# Seed default data if needed
php artisan db:seed --class=RoleSeeder --force 2>/dev/null || true

# Clear and cache config
echo "🔧 Optimizing application..."
php artisan config:cache 2>&1 || true
php artisan route:cache 2>&1 || true
php artisan view:cache 2>&1 || true

# Create storage link
php artisan storage:link 2>/dev/null || true

# Set permissions
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache

echo "✅ Application is ready!"
echo "🌐 Starting services..."

# Start supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
