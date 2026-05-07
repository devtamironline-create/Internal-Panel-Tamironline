<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن parent_id به appliance_categories تا دسته‌بندی‌ها به‌صورت
 * درختی (والد و زیرمجموعه) قابل تعریف باشند. مرحله ثبت‌نام تکنسین
 * زیر هر دسته اصلی، چک‌باکس‌های زیرمجموعه را نشان می‌دهد تا انتخاب
 * تخصصی‌تر باشد.
 *
 * با ON DELETE SET NULL: اگر یک والد حذف شد، فرزندانش به ریشه
 * منتقل می‌شوند به‌جای cascade delete (حذف تصادفی زیرمجموعه‌های
 * پراستفاده).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appliance_categories', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('id')
                ->constrained('appliance_categories')
                ->nullOnDelete();

            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('appliance_categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
