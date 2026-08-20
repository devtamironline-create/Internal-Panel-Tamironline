<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Permission «اصلاح مبلغ فاکتور» — دکمهٔ اختصاصی ادمین برای اصلاح
 * فاکتورهای با مبلغ اشتباه (باطل‌کردن + برگشت خودکار کمیسیون + صدور
 * فاکتور جدید). عمداً جدا از manage-crm-financial تا فقط ادمین ارشد
 * بگیرد — مثل الگوی delete-wallet-transaction.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! class_exists(\Spatie\Permission\Models\Permission::class)) {
            return;
        }

        \Spatie\Permission\Models\Permission::findOrCreate('correct-invoices', 'web');

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // حذف permission ردیف‌های تخصیص را هم پاک می‌کند — در پروداکشن
        // چیزی حذف نمی‌کنیم (قانون پروژه). no-op.
    }
};
