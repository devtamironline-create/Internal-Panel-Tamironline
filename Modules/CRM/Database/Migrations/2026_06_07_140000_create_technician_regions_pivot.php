<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول crm_technician_regions: لیست مناطق پوشش هر تکنسین.
 *
 * تا پیش از این، پوشش جغرافیایی فقط در سطح شهر (crm_technician_cities)
 * بود. حالا که مفهوم منطقه اضافه شده، تکنسین می‌تواند فقط مناطق خاصی
 * از یک شهر بزرگ (مثل تهران مناطق ۱، ۲، ۳) را پوشش دهد و سرویس
 * پیشنهاد هوشمند آن را در نظر بگیرد.
 *
 * منطق فعلی: اگر تکنسین هیچ منطقه‌ای انتخاب نکرد، فرض می‌کنیم همهٔ
 * مناطق آن شهر را پوشش می‌دهد (سازگاری به عقب با تکنسین‌های قدیمی که
 * فقط شهر را انتخاب کرده‌اند). فقط وقتی منطقه‌ای انتخاب شده باشد،
 * تکنسین به اون مناطق محدود می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_technician_regions')) {
            Schema::create('crm_technician_regions', function (Blueprint $t) {
                $t->foreignId('technician_id')->constrained('crm_technicians')->cascadeOnDelete();
                $t->foreignId('region_id')->constrained('crm_regions')->cascadeOnDelete();
                $t->timestamp('created_at')->nullable();
                $t->primary(['technician_id', 'region_id']);
                $t->index('region_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_technician_regions');
    }
};
