<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تاکسونومیِ «موضوعاتِ» انجمن — برای فیلترِ واقعیِ «جستجو بر اساس موضوع».
 *
 *   - site_forum_topics: slug/label/description/icon/tone/sort_order/is_active
 *   - site_forum_questions.topic_id: انتسابِ موضوع (از پنل هنگامِ داوری)
 *
 * موضوع‌های پیش‌فرض seed می‌شوند تا بدونِ کارِ دستی قابلِ استفاده باشد.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_forum_topics')) {
            Schema::create('site_forum_topics', function (Blueprint $t) {
                $t->id();
                $t->string('slug', 80)->unique();
                $t->string('label', 120);
                $t->string('description', 300)->nullable();
                $t->string('icon', 60)->nullable();   // alert-circle | settings | ...
                $t->string('tone', 30)->nullable();   // blue | rose | green | ...
                $t->unsignedInteger('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });

            $now = now();
            DB::table('site_forum_topics')->insert([
                ['slug' => 'errors', 'label' => 'ارورها و کدخطاها', 'description' => 'تفسیر کدهای خطای دستگاه‌ها', 'icon' => 'alert-circle', 'tone' => 'rose', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['slug' => 'troubleshooting', 'label' => 'عیب‌یابی', 'description' => 'تشخیصِ مشکل قبل از تعمیر', 'icon' => 'clipboard-check', 'tone' => 'amber', 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['slug' => 'maintenance', 'label' => 'نگهداری و سرویس', 'description' => 'سرویسِ دوره‌ای و افزایشِ عمرِ دستگاه', 'icon' => 'settings', 'tone' => 'blue', 'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['slug' => 'installation', 'label' => 'نصب و راه‌اندازی', 'description' => 'نصبِ صحیح و تنظیماتِ اولیه', 'icon' => 'wrench', 'tone' => 'violet', 'sort_order' => 4, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['slug' => 'electrical', 'label' => 'برق و ایمنی', 'description' => 'مشکلاتِ برقی و نکاتِ ایمنی', 'icon' => 'zap', 'tone' => 'cyan', 'sort_order' => 5, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['slug' => 'warranty', 'label' => 'گارانتی و خدمات', 'description' => 'گارانتی، قطعات و خدماتِ پس از فروش', 'icon' => 'shield-check', 'tone' => 'green', 'sort_order' => 6, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        Schema::table('site_forum_questions', function (Blueprint $t) {
            if (! Schema::hasColumn('site_forum_questions', 'topic_id')) {
                $t->unsignedBigInteger('topic_id')->nullable()->after('brand_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_forum_questions', function (Blueprint $t) {
            if (Schema::hasColumn('site_forum_questions', 'topic_id')) {
                $t->dropColumn('topic_id');
            }
        });
        Schema::dropIfExists('site_forum_topics');
    }
};
