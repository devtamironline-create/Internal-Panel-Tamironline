<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدولِ رویدادهای تحلیلیِ سراسریِ سایت (analytics_events).
 *
 * یک رویدادنویسِ عمومی و سبک که همه‌ی بخش‌های سایت می‌توانند در آن رویداد ثبت
 * کنند (بازدیدِ برند، بازدیدِ دستگاه، ثبتِ سفارش، جست‌وجو، کلیکِ بنر، …). هدف:
 * داشتنِ آمارِ خام و همیشگی برای تحلیل — «کدام برند/دستگاه پراستفاده است؟»،
 * «کاربر از کدام منبع آمد؟»، «چه مسیری تا ثبتِ سفارش طی شد؟».
 *
 * طراحی:
 *   - event    : نوعِ رویداد (برچسبِ کوتاه، snake_case). ایندکس‌شده برای گروه‌بندی.
 *   - subject  : هدفِ رویداد به‌صورتِ polymorphic (type + id) — اختیاری.
 *   - device_id / brand_id : ایندکسِ مستقیم روی موجودیت‌های پرتکرارِ تحلیل، تا
 *     شمارش «پراستفاده‌ترین برند/دستگاه» بدونِ join سریع باشد.
 *   - session_id : شناسه‌ی جلسه‌ی ناشناس (از کلاینت) برای دنبال‌کردنِ مسیرِ کاربر.
 *   - customer_id : اگر کاربر واردشده باشد.
 *   - source   : app | site | panel — از کجا آمده.
 *   - ip_hash  : IP هش‌شده (حریمِ خصوصی؛ IPِ خام ذخیره نمی‌شود).
 *   - props    : JSONِ آزاد برای فیلدهای موردیِ هر رویداد بدونِ نیاز به مهاجرت.
 *   - occurred_at : زمانِ وقوع (ممکن است با created_at کمی فرق کند اگر batch آید).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $t) {
            $t->id();

            $t->string('event', 64)->index();
            $t->string('source', 16)->nullable()->index();

            // هدفِ polymorphic — اختیاری
            $t->string('subject_type', 64)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();

            // بُعدهای پرتکرارِ تحلیل — ایندکسِ مستقیم
            $t->unsignedBigInteger('device_id')->nullable()->index();
            $t->unsignedBigInteger('brand_id')->nullable()->index();

            // زمینه‌ی کاربر
            $t->unsignedBigInteger('customer_id')->nullable()->index();
            $t->string('session_id', 64)->nullable()->index();
            $t->string('ip_hash', 64)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->string('url', 512)->nullable();

            // فیلدهای آزادِ هر رویداد
            $t->json('props')->nullable();

            $t->timestamp('occurred_at')->nullable()->index();
            $t->timestamps();

            // ایندکسِ ترکیبی برای پرسش‌های رایج «رویدادِ X در بازه‌ی زمانی»
            $t->index(['event', 'occurred_at']);
            $t->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
