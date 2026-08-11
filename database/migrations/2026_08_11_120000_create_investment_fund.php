<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * صندوق سرمایه — یک صندوقِ مشترک برای ثبت خریدهای طلا/سکه/ارز و
 * ارزش‌گذاریِ لحظه‌ای با وب‌سرویس نوسان.
 *
 * دسترسی عمداً از Spatie جداست: فلگ users.investment_access فقط با
 * کامندِ investment:grant روشن می‌شود، در صفحهٔ مدیریت دسترسی‌ها دیده
 * نمی‌شود و نقش admin هم دورش نمی‌زند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'investment_access')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('investment_access')->default(false);
            });
        }

        Schema::create('investment_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset', 30)->index();          // کلید از config/investment.php
            $table->decimal('amount', 20, 8);              // گرم / عدد / کوین
            $table->unsignedBigInteger('buy_unit_price');  // تومان به‌ازای هر واحد
            $table->date('bought_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_assets');

        if (Schema::hasColumn('users', 'investment_access')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('investment_access');
            });
        }
    }
};
