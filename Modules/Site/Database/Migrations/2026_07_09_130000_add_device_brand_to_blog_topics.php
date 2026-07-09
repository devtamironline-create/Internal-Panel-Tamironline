<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تاپیک‌های مقاله می‌توانند به یک «دستگاه» و/یا یک «برند» گره بخورند —
 * دقیقاً مثلِ صفحاتِ ترکیبی. هر دو اختیاری‌اند:
 *   - دستگاه + برند  → «خطاهای لباسشویی بوش»
 *   - فقط دستگاه      → «خطاهای ظرفشویی»
 *   - فقط برند        → «بهترین دستگاه‌های بوش»
 *   - هیچ‌کدام        → تاپیکِ عمومی (مثلِ قبل)
 *
 * URL/بردکرامبِ فرانت وقتی دستگاه هست بر اساسِ دستگاه ساخته می‌شود
 * (خانه › مقالات › مقالات {دستگاه} › {تاپیک}).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_blog_topics')) {
            return;
        }

        Schema::table('site_blog_topics', function (Blueprint $table) {
            if (! Schema::hasColumn('site_blog_topics', 'device_id')) {
                $table->foreignId('device_id')->nullable()->after('name')
                    ->constrained('crm_devices')->nullOnDelete();
            }
            if (! Schema::hasColumn('site_blog_topics', 'brand_id')) {
                $table->foreignId('brand_id')->nullable()->after('device_id')
                    ->constrained('crm_brands')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_blog_topics')) {
            return;
        }

        Schema::table('site_blog_topics', function (Blueprint $table) {
            if (Schema::hasColumn('site_blog_topics', 'brand_id')) {
                $table->dropConstrainedForeignId('brand_id');
            }
            if (Schema::hasColumn('site_blog_topics', 'device_id')) {
                $table->dropConstrainedForeignId('device_id');
            }
        });
    }
};
