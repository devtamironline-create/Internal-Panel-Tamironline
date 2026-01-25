#!/bin/bash

# ===========================================
# Tamironline Panel - cPanel Build Script
# ===========================================

set -e

echo "🔨 Building cPanel Package..."
echo "=============================="

PROJECT_DIR=$(pwd)
BUILD_DIR="/tmp/tamironline-build"
OUTPUT_FILE="$PROJECT_DIR/tamironline-cpanel.zip"

# Clean previous build
rm -rf "$BUILD_DIR"
rm -f "$OUTPUT_FILE"
mkdir -p "$BUILD_DIR"

echo "📦 Copying files..."

# Copy files
cp -r app "$BUILD_DIR/"
cp -r bootstrap "$BUILD_DIR/"
cp -r config "$BUILD_DIR/"
cp -r database "$BUILD_DIR/"
cp -r Modules "$BUILD_DIR/"
cp -r public "$BUILD_DIR/"
cp -r resources "$BUILD_DIR/"
cp -r routes "$BUILD_DIR/"
cp -r storage "$BUILD_DIR/"
cp -r vendor "$BUILD_DIR/"
cp artisan "$BUILD_DIR/"
cp composer.json "$BUILD_DIR/"
cp composer.lock "$BUILD_DIR/"
cp .env.example "$BUILD_DIR/" 2>/dev/null || true
cp modules_statuses.json "$BUILD_DIR/" 2>/dev/null || true

cd "$BUILD_DIR"

# Clean unnecessary files
find . -name ".DS_Store" -delete 2>/dev/null || true
find . -name ".git*" -exec rm -rf {} + 2>/dev/null || true
rm -rf storage/logs/*.log 2>/dev/null || true
rm -rf storage/framework/cache/data/* 2>/dev/null || true
rm -rf storage/framework/sessions/* 2>/dev/null || true
rm -rf storage/framework/views/* 2>/dev/null || true

# Create necessary directories
mkdir -p storage/framework/{sessions,views,cache/data}
mkdir -p storage/logs
mkdir -p bootstrap/cache

# Create .gitkeep files
touch storage/framework/sessions/.gitkeep
touch storage/framework/views/.gitkeep
touch storage/framework/cache/.gitkeep
touch storage/logs/.gitkeep

# Create .htaccess for root (redirect to public)
cat > .htaccess << 'HTACCESS'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
HTACCESS

# Create install.php for first-time setup
cat > public/install.php << 'INSTALL_PHP'
<?php
/**
 * Tamironline Panel - Installation Script
 * Run this once after uploading files
 */

echo "<html dir='rtl'><head><meta charset='UTF-8'><title>نصب پنل تعمیرآنلاین</title>";
echo "<style>body{font-family:Tahoma,sans-serif;padding:40px;background:#f5f5f5;}";
echo ".box{background:#fff;padding:30px;border-radius:10px;max-width:600px;margin:auto;box-shadow:0 2px 10px rgba(0,0,0,0.1);}";
echo "h1{color:#1a2d48;}.ok{color:green;}.err{color:red;}pre{background:#f0f0f0;padding:15px;border-radius:5px;}</style></head><body>";
echo "<div class='box'>";
echo "<h1>🔧 نصب پنل تعمیرآنلاین</h1>";

$errors = [];

// Check PHP version
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    $errors[] = "PHP 8.2+ نیاز است. نسخه فعلی: " . PHP_VERSION;
}

// Check required extensions
$required = ['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath'];
foreach ($required as $ext) {
    if (!extension_loaded($ext)) {
        $errors[] = "افزونه $ext نصب نیست";
    }
}

// Check GD or Imagick
if (!extension_loaded('gd') && !extension_loaded('imagick')) {
    $errors[] = "GD یا Imagick نیاز است";
}

if ($errors) {
    echo "<h2 class='err'>❌ خطاها:</h2><ul>";
    foreach ($errors as $e) echo "<li class='err'>$e</li>";
    echo "</ul></div></body></html>";
    exit;
}

echo "<p class='ok'>✅ PHP " . PHP_VERSION . " - OK</p>";
echo "<p class='ok'>✅ همه افزونه‌ها نصب هستند</p>";

// Create directories
$dirs = [
    '../storage/framework/sessions',
    '../storage/framework/views', 
    '../storage/framework/cache/data',
    '../storage/logs',
    '../bootstrap/cache'
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}
echo "<p class='ok'>✅ پوشه‌ها ایجاد شدند</p>";

// Check .env
if (!file_exists('../.env')) {
    if (file_exists('../.env.example')) {
        copy('../.env.example', '../.env');
        echo "<p class='ok'>✅ فایل .env ایجاد شد</p>";
    }
} else {
    echo "<p class='ok'>✅ فایل .env موجود است</p>";
}

echo "<hr>";
echo "<h2>📋 مراحل بعدی:</h2>";
echo "<pre>";
echo "1. فایل .env را ویرایش کنید:\n";
echo "   - APP_URL=https://your-domain.com\n";
echo "   - DB_HOST=localhost\n";
echo "   - DB_DATABASE=نام_دیتابیس\n";
echo "   - DB_USERNAME=نام_کاربری\n";
echo "   - DB_PASSWORD=رمز_عبور\n\n";
echo "2. در ترمینال SSH اجرا کنید:\n";
echo "   cd /home/YOUR_USER/public_html\n";
echo "   php artisan key:generate\n";
echo "   php artisan migrate --seed\n\n";
echo "3. این فایل install.php را حذف کنید\n";
echo "</pre>";
echo "<p style='color:orange;'><strong>⚠️ مهم:</strong> بعد از اتمام نصب، این فایل را حذف کنید!</p>";
echo "</div></body></html>";
?>
INSTALL_PHP

# Update .env.example for cPanel
cat > .env.example << 'ENV_EXAMPLE'
APP_NAME="تعمیرآنلاین"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=Asia/Tehran
APP_URL=https://your-domain.com

APP_LOCALE=fa
APP_FALLBACK_LOCALE=fa

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

SESSION_DRIVER=file
SESSION_LIFETIME=120
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local

BROADCAST_CONNECTION=log

SMS_KAVENEGAR_API_KEY=
SMS_KAVENEGAR_SENDER=
ENV_EXAMPLE

echo "📦 Creating ZIP archive..."
cd /tmp
zip -rq "$OUTPUT_FILE" tamironline-build

# Cleanup
rm -rf "$BUILD_DIR"

echo ""
echo "=============================="
echo "✅ Build complete!"
echo ""
echo "📦 Output: $OUTPUT_FILE"
echo "📊 Size: $(du -h "$OUTPUT_FILE" | cut -f1)"
echo ""
