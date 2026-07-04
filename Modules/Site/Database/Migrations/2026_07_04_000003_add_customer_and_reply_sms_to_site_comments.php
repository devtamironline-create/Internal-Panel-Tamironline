<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * پیوندِ کامنت به مشتری (برای پیامکِ اطلاع‌رسانی) + نشانگرِ ارسالِ پیامکِ پاسخ.
 *
 * تا الان کامنتِ عمومی customer_id را ذخیره نمی‌کرد (ستون نبود)، پس شماره‌ای
 * برای اطلاع‌رسانی در دست نبود. حالا:
 *   - customer_id: مشتریِ ثبت‌کنندهٔ کامنت (برای گرفتنِ موبایل).
 *   - reply_sms_sent_at: زمانِ ارسالِ پیامکِ «پاسخ داده شد» (جلوگیری از تکرار).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_comments', function (Blueprint $table) {
            if (! Schema::hasColumn('site_comments', 'customer_id')) {
                $table->unsignedBigInteger('customer_id')->nullable()->after('user_id');
                $table->index('customer_id');
            }
            if (! Schema::hasColumn('site_comments', 'reply_sms_sent_at')) {
                $table->timestamp('reply_sms_sent_at')->nullable()->after('approved_by_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_comments', function (Blueprint $table) {
            if (Schema::hasColumn('site_comments', 'customer_id')) {
                $table->dropIndex(['customer_id']);
                $table->dropColumn('customer_id');
            }
            if (Schema::hasColumn('site_comments', 'reply_sms_sent_at')) {
                $table->dropColumn('reply_sms_sent_at');
            }
        });
    }
};
