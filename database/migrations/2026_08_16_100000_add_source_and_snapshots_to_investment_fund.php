<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * صندوق سرمایه — فاز ۲:
 *
 *  - source: منبعِ سرمایهٔ هر خرید («tamir» یا «ganje»). خریدهای قدیمی
 *    null می‌مانند و در نمودار با برچسبِ «نامشخص» می‌آیند.
 *  - investment_snapshots: یک ردیف برای هر روز — ارزشِ روزِ کلِ سبد +
 *    تفکیکِ هر دارایی، تا روندِ ارزش روی نمودارِ روز/ماه/سال قابلِ نمایش
 *    باشد. نوسان قیمتِ تاریخی نمی‌دهد، پس این جدول تنها حافظهٔ تاریخیِ
 *    ماست و ردیف‌هایش نباید حذف/بازنویسیِ گذشته شوند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_assets', function (Blueprint $table) {
            $table->string('source', 20)->nullable()->index()->after('bought_at');
        });

        Schema::create('investment_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snap_date')->unique();
            $table->unsignedBigInteger('total_value');   // فقط دارایی‌های قیمت‌دار
            $table->unsignedBigInteger('total_cost');
            $table->json('breakdown')->nullable();       // asset => {amount, value}
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_snapshots');

        Schema::table('investment_assets', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
