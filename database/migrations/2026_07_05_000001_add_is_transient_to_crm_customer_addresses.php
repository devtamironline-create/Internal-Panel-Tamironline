<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * آدرس‌های «یک‌بارمصرف» (transient): وقتی کاربر هنگام ثبت سفارش آدرسِ جدید
 * می‌دهد ولی «ذخیره برای بعد» را نمی‌زند، آدرس با is_transient=true ساخته
 * می‌شود. چنین آدرسی فقط برای همان سفارش مصرف می‌شود و در GET /addresses
 * (دفترچه‌ی آدرسِ کاربر) نمایش داده نمی‌شود تا لیست شلوغ نشود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_customer_addresses', function (Blueprint $t) {
            if (! Schema::hasColumn('crm_customer_addresses', 'is_transient')) {
                $t->boolean('is_transient')->default(false)->after('is_default')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_customer_addresses', function (Blueprint $t) {
            if (Schema::hasColumn('crm_customer_addresses', 'is_transient')) {
                $t->dropIndex(['is_transient']);
                $t->dropColumn('is_transient');
            }
        });
    }
};
