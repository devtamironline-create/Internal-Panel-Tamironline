<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * لاگِ دلیلِ تخصیص تکنسین — برای همهٔ سفارش‌ها و همهٔ مسیرها
 * (دستی، پیشنهاد هوشمند، تخصیص گروهی، تخصیص خودکار، لغو تخصیص).
 *
 * چرا جدول جدا و نه OrderStatusLog: آنجا فقط گذارِ وضعیت ثبت می‌شود و
 * جای ساختارمندی برای امتیاز/breakdown/رقبا ندارد. اینجا snapshot لحظهٔ
 * تصمیم نگه داشته می‌شود تا بعداً — حتی اگر بدهی یا ظرفیت تکنسین عوض
 * شده باشد — بشود گفت «در آن لحظه به این دلیل به او داده شد».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_order_assignment_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('technician_id')->nullable()->index();
            $table->unsignedBigInteger('previous_technician_id')->nullable();

            // manual | suggestion | group | auto | sticky | unassign
            $table->string('mode', 20)->index();

            // snapshot امتیاز لحظهٔ تصمیم
            $table->unsignedTinyInteger('score')->nullable();
            $table->json('breakdown')->nullable();
            $table->json('reasons')->nullable();
            $table->json('alternatives')->nullable();

            // زمینهٔ گروهی
            $table->unsignedSmallInteger('group_size')->default(1);
            $table->unsignedSmallInteger('covered_count')->default(1);
            $table->json('group_order_ids')->nullable();

            // متنِ فارسیِ آمادهٔ نمایش
            $table->text('note')->nullable();

            $table->unsignedBigInteger('assigned_by')->nullable()->index();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_order_assignment_logs');
    }
};
