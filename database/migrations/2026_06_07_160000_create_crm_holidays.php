<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * crm_holidays — تعطیلات رسمی و سفارشی.
 *
 * استفاده‌های اصلی:
 *   - اپ موبایل: GET /v1/customer/holidays?from=&to= تا کاربر در تاریخ‌های
 *     تعطیل سفارش ثبت نکند یا هشدار ببیند
 *   - ادمین: مدیریت لیست — تعطیلات رسمی سال + موارد خاص (انتخابات، ...)
 *
 * type:
 *   official  — تعطیل رسمی (شنبه/جمعه‌ها لازم نیست — این برای موارد خاص است)
 *   religious — مذهبی (عاشورا، ...)
 *   national  — ملی (نوروز، ...)
 *   custom    — موردی توسط ادمین
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_holidays', function (Blueprint $t) {
            $t->id();
            $t->date('date')->unique();                    // YYYY-MM-DD
            $t->string('label', 200);                       // مثلاً «عید فطر»
            $t->string('type', 20)->default('custom')->index();
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true)->index();
            $t->timestamps();

            $t->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_holidays');
    }
};
