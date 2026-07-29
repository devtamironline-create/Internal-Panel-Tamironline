<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * انبارِ توکنِ پوشِ اپِ تکنسین.
 *
 * امروز هیچ پوشی ارسال نمی‌شود — FCM در ایران در دسترس نیست و سرویسِ
 * داخلی (Pushe/چابک) هنوز انتخاب نشده. ولی اپ توکن را همین حالا تولید
 * می‌کند و اگر جایی برای فرستادنش نباشد، روزی که سرویس انتخاب شود باید
 * منتظرِ انتشارِ نسخهٔ جدیدِ اپ بمانیم تا کاربران توکن بفرستند. با ثبتِ
 * زودهنگام، آن روز فقط سمتِ سرور کار دارد.
 *
 * یکتایی روی خودِ توکن است نه (تکنسین، دستگاه): یک گوشی که بین دو
 * حساب دست‌به‌دست شود نباید دو ردیفِ زندهٔ همزمان بسازد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_technician_push_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('technician_id')->index();
            $table->string('token', 255)->unique();
            // expo | pushe | chabok — تا موقعِ ارسال معلوم باشد با کدام
            // سرویس باید فرستاده شود.
            $table->string('provider', 20)->default('expo');
            $table->string('platform', 10)->nullable();   // ios | android
            $table->string('device_name', 120)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_technician_push_tokens');
    }
};
