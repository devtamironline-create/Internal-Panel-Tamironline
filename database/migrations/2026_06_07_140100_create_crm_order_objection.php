<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pivot سفارش ↔ ایراد (M:N).
 *
 * هر سفارش می‌تواند چند ایراد انتخاب‌شده داشته باشد. problem_title و
 * problem_description روی crm_orders همچنان برای متن آزاد توصیف مشکل
 * استفاده می‌شوند — این pivot ایرادات منوی dropdown است.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_order_objection', function (Blueprint $t) {
            $t->foreignId('order_id')->constrained('crm_orders')->cascadeOnDelete();
            $t->foreignId('objection_id')->constrained('crm_objections')->cascadeOnDelete();
            $t->unsignedTinyInteger('sort_order')->default(0);
            $t->primary(['order_id', 'objection_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_order_objection');
    }
};
