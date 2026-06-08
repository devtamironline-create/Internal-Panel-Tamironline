<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * crm_review_tags — تگ‌های نقاط قوت/ضعف برای نظرسنجی مشتری.
 *
 * type:
 *   - 'pro' → نقطه‌ی قوت (مثلاً «وقت‌شناسی»)
 *   - 'con' → نقطه‌ی ضعف (مثلاً «تأخیر در آمدن»)
 *
 * فرانت معمولاً بر اساس rating تصمیم می‌گیرد کدام دسته نمایش دهد:
 *   rating 1-2 → cons
 *   rating 3   → both
 *   rating 4-5 → pros
 * ولی سرور هیچ محدودیتی روی انتخاب کاربر اعمال نمی‌کند — می‌تواند هر تگی
 * (pro یا con) را برای هر rating انتخاب کند.
 *
 * pivot crm_order_review_tags ربط M:N بین نظر و تگ‌های انتخابی.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_review_tags', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 80)->unique();
            $t->string('label', 200);
            $t->enum('type', ['pro', 'con'])->index();
            $t->string('icon', 60)->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true)->index();
            $t->timestamps();
        });

        Schema::create('crm_order_review_tags', function (Blueprint $t) {
            $t->foreignId('review_id')->constrained('crm_order_reviews')->cascadeOnDelete();
            $t->foreignId('tag_id')->constrained('crm_review_tags')->cascadeOnDelete();
            $t->primary(['review_id', 'tag_id']);
        });

        // seed اولیه — ۶ نقطه قوت + ۶ نقطه ضعف (تعداد برابر طبق درخواست)
        $now = now();
        $rows = [
            // ─── pros (نقاط قوت) ─────────────────────────────────
            ['slug' => 'pro-punctual', 'label' => 'وقت‌شناسی', 'type' => 'pro', 'icon' => 'clock', 'sort_order' => 1],
            ['slug' => 'pro-respectful', 'label' => 'برخورد محترمانه', 'type' => 'pro', 'icon' => 'smile', 'sort_order' => 2],
            ['slug' => 'pro-expert', 'label' => 'تخصص بالا', 'type' => 'pro', 'icon' => 'award', 'sort_order' => 3],
            ['slug' => 'pro-clean', 'label' => 'تمیزکاری پس از کار', 'type' => 'pro', 'icon' => 'sparkles', 'sort_order' => 4],
            ['slug' => 'pro-fair-price', 'label' => 'قیمت منصفانه', 'type' => 'pro', 'icon' => 'wallet', 'sort_order' => 5],
            ['slug' => 'pro-clear-explain', 'label' => 'توضیح روشن فرآیند تعمیر', 'type' => 'pro', 'icon' => 'message-square', 'sort_order' => 6],

            // ─── cons (نقاط ضعف) ─────────────────────────────────
            ['slug' => 'con-late', 'label' => 'تأخیر در مراجعه', 'type' => 'con', 'icon' => 'clock-alert', 'sort_order' => 1],
            ['slug' => 'con-rude', 'label' => 'برخورد نامناسب', 'type' => 'con', 'icon' => 'frown', 'sort_order' => 2],
            ['slug' => 'con-low-quality', 'label' => 'کیفیت پایین کار', 'type' => 'con', 'icon' => 'thumbs-down', 'sort_order' => 3],
            ['slug' => 'con-messy', 'label' => 'شلختگی محل کار', 'type' => 'con', 'icon' => 'trash-2', 'sort_order' => 4],
            ['slug' => 'con-overpriced', 'label' => 'قیمت بالا', 'type' => 'con', 'icon' => 'badge-dollar-sign', 'sort_order' => 5],
            ['slug' => 'con-poor-explain', 'label' => 'توضیح ناکافی', 'type' => 'con', 'icon' => 'message-square-off', 'sort_order' => 6],
        ];

        $insertRows = array_map(fn ($r) => array_merge($r, [
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]), $rows);

        DB::table('crm_review_tags')->insert($insertRows);
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_order_review_tags');
        Schema::dropIfExists('crm_review_tags');
    }
};
