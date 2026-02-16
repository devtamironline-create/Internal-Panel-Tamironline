<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Warehouse\Models\WarehouseOrder;
use Spatie\Permission\Models\Permission;

echo "🔧 راه‌اندازی سیستم چاپ جدید...\n\n";

// 1. اضافه کردن permission جدید
echo "📋 اضافه کردن permission جدید...\n";
try {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    $permission = Permission::firstOrCreate(
        ['name' => 'warehouse.reprint-invoice', 'guard_name' => 'web'],
        ['name' => 'warehouse.reprint-invoice', 'guard_name' => 'web']
    );

    echo "✅ Permission 'warehouse.reprint-invoice' اضافه شد\n\n";
} catch (\Exception $e) {
    echo "❌ خطا در اضافه کردن permission: " . $e->getMessage() . "\n\n";
}

// 2. صفر کردن همه print_count ها
echo "📊 صفر کردن تعداد چاپ‌های قبلی...\n";
try {
    $count = WarehouseOrder::where('print_count', '>', 0)->count();

    if ($count > 0) {
        WarehouseOrder::where('print_count', '>', 0)->update(['print_count' => 0]);
        echo "✅ {$count} سفارش صفر شد\n";
    } else {
        echo "✅ همه سفارشات قبلاً صفر بودند\n";
    }
} catch (\Exception $e) {
    echo "❌ خطا در صفر کردن: " . $e->getMessage() . "\n";
}

echo "\n✅ تمام! از الان به بعد:\n";
echo "   - باز کردن صفحه چاپ → هیچ تغییری نمیده\n";
echo "   - زدن دکمه چاپ → print_count++ و استیتش عوض میشه\n";
echo "   - چاپ مجدد → نیاز به permission 'warehouse.reprint-invoice' داره\n";
