<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن مفهوم «منطقه» (Region/District) به سلسله‌مراتب جغرافیایی.
 *
 * تا قبل از این، استان→شهر کافی بود؛ ولی شهرهای بزرگ مثل تهران واقعاً
 * چند منطقهٔ شهری دارند که قبلاً به‌اشتباه به‌عنوان شهرهای جداگانه
 * («تهران منطقه ۱» تا «تهران منطقه ۲۲») ثبت می‌شدند. این migration
 * جدول منطقه را زیر شهر ایجاد می‌کند و یک FK اختیاری روی crm_orders
 * می‌گذارد. منطقه اختیاری است — شهرهای کوچک منطقه ندارند.
 *
 * تاریخچهٔ موجود لمس نمی‌شود — سفارش‌های قبلی region_id=NULL می‌مانند
 * و دیتای شهرهای قدیمی «تهران منطقه N» سرجایش باقی است تا backward
 * compatibility حفظ شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_regions')) {
            Schema::create('crm_regions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('city_id')->constrained('crm_cities')->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('slug', 120);
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                // slug در محدودهٔ هر شهر یکتا باشد (نه global).
                $table->unique(['city_id', 'slug']);
                $table->index(['city_id', 'sort_order'], 'regions_city_sort_idx');
                $table->index('is_active');
            });
        }

        Schema::table('crm_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_orders', 'region_id')) {
                $table->foreignId('region_id')->nullable()->after('city_id')
                    ->constrained('crm_regions')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_orders', function (Blueprint $table) {
            if (Schema::hasColumn('crm_orders', 'region_id')) {
                $table->dropConstrainedForeignId('region_id');
            }
        });

        Schema::dropIfExists('crm_regions');
    }
};
