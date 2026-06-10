<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * crm_objections — ایرادات قابل انتخاب توسط مشتری در فرم ثبت سفارش اپ.
 *
 * هر ایراد به یک یا چند دستگاه متصل می‌شود (M:N با crm_devices). فرانت موبایل
 * بر اساس device_id که کاربر انتخاب می‌کند، لیست objection های مرتبط را
 * نمایش می‌دهد.
 *
 * problem_title / problem_description روی crm_orders دست‌نخورده می‌ماند —
 * در فاز Order API (Block 1) سفارش هم به objection_ids و هم به متن آزاد
 * objection_description اشاره می‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_objections', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 100)->unique();
            $t->string('name', 200);                // مثلاً «صدای غیرعادی»
            $t->text('description')->nullable();
            $t->string('icon', 60)->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true)->index();
            $t->timestamps();
        });

        Schema::create('crm_device_objection', function (Blueprint $t) {
            $t->foreignId('device_id')->constrained('crm_devices')->cascadeOnDelete();
            $t->foreignId('objection_id')->constrained('crm_objections')->cascadeOnDelete();
            $t->unsignedInteger('sort_order')->default(0);
            $t->primary(['device_id', 'objection_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_device_objection');
        Schema::dropIfExists('crm_objections');
    }
};
