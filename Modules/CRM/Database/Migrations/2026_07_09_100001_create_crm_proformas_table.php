<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * پیش‌فاکتور (proforma / quote) — برآوردِ قیمتِ قبل از انجامِ کار.
 *
 * مستقل از crm_invoices: پیش‌فاکتور یک «پیشنهادِ قیمت» است که تکنسین یا
 * ادمین برای مشتری صادر می‌کند تا مشتری قبل از تعمیر، هزینه را ببیند و
 * تأیید کند. اقلام (items) به‌صورت JSON روی همین ردیف نگه‌داری می‌شوند
 * (خودکفا، بدون جدولِ دوم — مثلِ الگوی buy_price_list سفارش).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_proformas')) {
            return;
        }

        Schema::create('crm_proformas', function (Blueprint $table) {
            $table->id();
            $table->string('proforma_code', 40)->unique();       // PF-YYMM-NNNNN
            $table->string('public_token', 64)->unique();        // لینکِ عمومیِ غیرقابل‌حدس

            // اتصالِ اختیاری به سفارش/مشتری/تکنسین — پیش‌فاکتور می‌تواند مستقل هم باشد.
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('technician_id')->nullable()->index();

            // اطلاعاتِ نمایشیِ مشتری/دستگاه (snapshot — مستقل از تغییرِ بعدیِ سفارش).
            $table->string('customer_name', 150)->nullable();
            $table->string('customer_mobile', 20)->nullable();
            $table->string('device_name', 120)->nullable();
            $table->string('brand_name', 120)->nullable();

            // اقلام: [{title, quantity, unit_price, total}] — مبالغ به تومان (integer).
            $table->json('items');
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('total')->default(0);

            $table->text('description')->nullable();             // توضیحِ کلیِ برآورد
            $table->date('valid_until')->nullable();             // اعتبارِ پیشنهاد

            // draft | sent | accepted | rejected | expired | converted
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedBigInteger('invoice_id')->nullable();  // اگر به فاکتور تبدیل شد

            // منشأ: چه کسی ساخت (ادمین یا تکنسین) — برای audit.
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('created_by_tech_id')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_proformas');
    }
};
