<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * صفحاتِ سئوِ شهری (SEO-024): برای هر «شهرِ اصلی» یک درختِ کاملِ صفحات
 * به‌صورت خودکار و «پیش‌نویس» ساخته می‌شود تا مدیر آن‌ها را منتشر کند.
 *
 * انواع (type):
 *   city     → /{city}                                (تعمیرات لوازم خانگی در مشهد)
 *   services → /{city}/services                       (خدمات تعمیرآنلاین در مشهد)
 *   device   → /{city}/services/{device}              (تعمیر لباسشویی در مشهد)
 *   brands   → /{city}/brands                         (برندهای تحت پوشش … در مشهد)
 *   brand    → /{city}/brands/{brand}                 (تعمیرات … بوش در مشهد)
 *   combo    → /{city}/services/{device}/{brand}      (تعمیر لباسشویی بوش در مشهد)
 *
 * فقط صفحاتِ status=published در sitemap و API عمومی می‌آیند؛ بقیه ۴۰۴.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_city_pages')) {
            return;
        }

        Schema::create('crm_city_pages', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('city_id')->index();
            // برای گروه‌بندیِ استانیِ آینده (بردنِ شهرها زیرِ استان) — denormalize.
            $table->unsignedBigInteger('province_id')->nullable()->index();

            $table->string('type', 20)->index(); // city|services|device|brands|brand|combo
            $table->unsignedBigInteger('device_id')->nullable()->index();
            $table->unsignedBigInteger('brand_id')->nullable()->index();

            // مسیرِ کاملِ عمومی روی فرانتِ Next.js — کلیدِ یکتاسازی/idempotency.
            $table->string('path')->unique();

            $table->string('title')->nullable();
            $table->string('h1')->nullable();
            $table->text('meta_description')->nullable();
            $table->longText('content')->nullable();

            // draft → published → archived. پیش‌نویس تا تاییدِ مدیر.
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable();

            // خودکار (تولیدشده) در برابرِ دستیِ ادمین.
            $table->boolean('auto_generated')->default(true);

            $table->timestamps();

            // یک ردیف به‌ازای هر ترکیبِ منطقی. device_id/brand_id برای
            // ردیف‌های نامربوط null است؛ path (unique) idempotency را تضمین می‌کند.
            $table->index(['city_id', 'type', 'device_id', 'brand_id'], 'crm_city_pages_combo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_city_pages');
    }
};
