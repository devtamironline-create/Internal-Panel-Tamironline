<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دفترِ روزانهٔ گوگل ادز (زیرمجموعهٔ حسابداری) — هر روز یک ردیف. مقادیرِ
 * دستی: مبلغِ واریزیِ ادز (تومان)، تعداد لیر، قیمتِ هر لیر. شارژِ کیف‌پولِ
 * تکنسین و تعدادِ سفارشِ هر روز هنگامِ نمایش محاسبه می‌شوند (ذخیره نمی‌شوند).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_google_ads_entries')) {
            return;
        }

        Schema::create('crm_google_ads_entries', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();                 // روزِ ثبت (یکتا)
            $table->unsignedBigInteger('ad_amount')->default(0);      // مبلغِ واریزیِ ادز (تومان)
            $table->decimal('lira_count', 14, 2)->default(0);        // تعداد لیر
            $table->unsignedBigInteger('lira_unit_price')->default(0); // قیمتِ هر لیر (تومان)
            $table->text('note')->nullable();               // یادداشتِ همان روز
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_google_ads_entries');
    }
};
