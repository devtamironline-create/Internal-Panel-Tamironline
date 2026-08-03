<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * لاگِ هر پوشی که به یک توکن فرستاده می‌شود — یک ردیف به ازای هر توکن،
 * نه به ازای هر درخواست.
 *
 * چرا به ازای هر توکن: نجوا در یک درخواست تا هزار توکن می‌گیرد و برای
 * هرکدام وضعیت و هزینهٔ جداگانه برمی‌گرداند. اگر یک ردیف برای کلِ
 * درخواست بنویسیم، «کدام تکنسین اعلانش نرسید» و «هزینهٔ هر تکنسین» — دو
 * ستونِ اصلیِ گزارشِ پنل ادمین — قابلِ استخراج نیست.
 *
 * `token` عمداً به‌صورت رشتهٔ خام هم ذخیره می‌شود و نه فقط FK: توکن ممکن
 * است بعداً باطل و پاک شود، ولی گزارشِ هزینهٔ ماهِ گذشته نباید ستونِ خالی
 * نشان دهد.
 *
 * کلیدهای خارجی بدونِ constraint تعریف شده‌اند — همان کاری که مهاجرتِ
 * `crm_technician_push_tokens` کرد. لاگ نباید دلیلِ شکستِ حذفِ یک سفارش
 * یا کاربر شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_push_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('technician_id')->nullable()->index();
            $table->unsignedBigInteger('push_token_id')->nullable()->index();
            $table->string('token', 255);

            // کلیدِ رویداد (order_assigned_tech, announcement, …) — برای
            // ارسالِ دستیِ کمپین خالی می‌ماند.
            $table->string('event_key', 50)->nullable();
            $table->unsignedBigInteger('order_id')->nullable()->index();

            $table->string('title', 250);
            $table->text('body');
            $table->string('click_url', 500)->nullable();

            // وضعیتِ خودِ نجوا: Sent | Scheduled | InvalidToken
            // یا `failed` وقتی درخواست اصلاً به مقصد نرسید.
            $table->string('status', 30)->default('failed');
            $table->unsignedInteger('cost')->default(0);

            // برای پیگیریِ بعدی از GET /v1/report/token و لغوِ زمان‌بندی.
            $table->string('request_id', 64)->nullable()->index();

            // کدِ HTTPِ خطا (۴۰۳/۴۱۶/۴۱۸/…) — مبنای بنرِ قرمزِ پنل ادمین.
            $table->unsignedSmallInteger('error_code')->nullable();
            $table->text('response')->nullable();

            // null یعنی ارسالِ خودکار؛ پرشده یعنی ادمین دستی فرستاده.
            $table->unsignedBigInteger('sent_by')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['technician_id', 'created_at']);
            $table->index(['event_key', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_push_logs');
    }
};
