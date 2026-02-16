<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Warehouse\Models\WarehouseProduct;
use Modules\Warehouse\Models\WarehouseSetting;
use Illuminate\Support\Facades\Http;

echo "🔧 رفع وزن variations...\n\n";

// گرفتن تنظیمات ووکامرس
$siteUrl = WarehouseSetting::get('wc_site_url');
$consumerKey = WarehouseSetting::get('wc_consumer_key');
$consumerSecret = WarehouseSetting::get('wc_consumer_secret');

if (!$siteUrl || !$consumerKey || !$consumerSecret) {
    echo "❌ تنظیمات ووکامرس کامل نیست!\n";
    exit(1);
}

// گرفتن واحد وزن
echo "📊 دریافت واحد وزن از ووکامرس...\n";
$response = Http::timeout(15)
    ->withBasicAuth($consumerKey, $consumerSecret)
    ->get(rtrim($siteUrl, '/') . '/wp-json/wc/v3/settings/products/woocommerce_weight_unit');

$weightUnit = 'g';
if ($response->successful()) {
    $weightUnit = $response->json()['value'] ?? 'g';
    echo "✅ واحد وزن: {$weightUnit}\n\n";
}

// تابع تبدیل به گرم
function toGrams($weight, $unit) {
    if ($weight <= 0) return 0;
    return match($unit) {
        'kg' => round($weight * 1000, 2),
        'lbs' => round($weight * 453.592, 2),
        'oz' => round($weight * 28.3495, 2),
        default => round($weight, 2),
    };
}

// مرحله 1: Sync variable products
echo "📦 مرحله 1: آپدیت وزن variable products از ووکامرس...\n";
$variableProducts = WarehouseProduct::where('type', 'variable')->get();
$synced = 0;

foreach ($variableProducts as $product) {
    echo "  → {$product->wc_product_id}: {$product->name}\n";

    $response = Http::timeout(15)
        ->withBasicAuth($consumerKey, $consumerSecret)
        ->get(rtrim($siteUrl, '/') . '/wp-json/wc/v3/products/' . $product->wc_product_id);

    if ($response->successful()) {
        $wcProduct = $response->json();
        $rawWeight = (float)($wcProduct['weight'] ?? 0);
        $weightGrams = toGrams($rawWeight, $weightUnit);

        if ($weightGrams > 0) {
            $product->weight = $weightGrams;
            $product->save();
            echo "    ✅ وزن آپدیت شد: {$weightGrams}g\n";
            $synced++;
        } else {
            echo "    ⚠️  وزن صفر\n";
        }
    } else {
        echo "    ❌ خطا در دریافت\n";
    }

    usleep(200000); // 200ms delay
}

echo "\n✅ {$synced} variable product آپدیت شد\n\n";

// مرحله 2: کپی وزن به variations
echo "📦 مرحله 2: کپی وزن از parent به variations...\n";
$variations = WarehouseProduct::where('type', 'variation')
    ->where('weight', 0)
    ->whereNotNull('parent_id')
    ->get();

$fixed = 0;
$failed = 0;

foreach ($variations as $variation) {
    $parent = WarehouseProduct::where('wc_product_id', $variation->parent_id)->first();

    if ($parent && $parent->weight > 0) {
        $variation->weight = $parent->weight;
        $variation->save();
        echo "  ✅ {$variation->wc_product_id}: {$variation->name} → {$parent->weight}g\n";
        $fixed++;
    } else {
        echo "  ❌ {$variation->wc_product_id}: parent وزن نداره\n";
        $failed++;
    }
}

echo "\n";
echo "✅ {$fixed} variation رفع شد\n";
if ($failed > 0) {
    echo "⚠️  {$failed} variation رفع نشد\n";
}

echo "\n✅ تمام!\n";
