<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ردیابیِ ارسالِ پیامکِ رسیدِ انتقال.
 *
 * پیامکِ رسید دیگر خودکار نیست؛ تکنسین آن را «دستی» و فقط «یک‌بار» می‌فرستد.
 * برای اجرای این قانون، زمان و فرستندهٔ ارسالِ پیامک روی خودِ رسید ذخیره
 * می‌شود. ادمین (با دسترسیِ مربوطه) می‌تواند «ارسالِ مجدد» را انجام دهد که
 * این مقدار را به‌روزرسانی می‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_transfer_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_transfer_receipts', 'sms_sent_at')) {
                $table->timestamp('sms_sent_at')->nullable()->after('description');
            }
            if (! Schema::hasColumn('crm_transfer_receipts', 'sms_sent_by')) {
                $table->unsignedBigInteger('sms_sent_by')->nullable()->after('sms_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_transfer_receipts', function (Blueprint $table) {
            foreach (['sms_sent_at', 'sms_sent_by'] as $col) {
                if (Schema::hasColumn('crm_transfer_receipts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
