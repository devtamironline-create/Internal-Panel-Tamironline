<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ایندکس‌های گمشدهٔ چت.
 *
 * هر سه کوئریِ پرتکرارِ چت — بجِ سایدبار، /admin/chat/tick و
 * /admin/chat/unread-recent — با شرطِ `conversation_participants.user_id`
 * شروع می‌شوند. ولی تنها ایندکسِ آن جدول `(conversation_id, user_id)`
 * است و چون ستونِ اولش conversation_id است، فیلترِ user_id از آن استفاده
 * نمی‌کند: MySQL کلِ جدول را می‌پیماید.
 *
 * تا وقتی جدول کوچک بود کسی متوجه نمی‌شد. با زیاد شدنِ گفتگوها و
 * پیام‌ها، همین اسکن روی *هر رندرِ صفحه* تکرار می‌شود (بجِ سایدبار) و
 * کندی به کلِ پنل سرایت می‌کند، نه فقط به پیام‌رسان.
 *
 * ایندکس دوم — messages(conversation_id, id) — برای `MAX(id)` هر گفتگو
 * است که در ضربانِ ۵ ثانیه‌ای اجرا می‌شود. ایندکسِ موجود روی
 * (conversation_id, created_at) برای مرتب‌سازیِ زمانی خوب است ولی
 * بیشینهٔ id را از آن نمی‌شود مستقیم خواند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conversation_participants')
            && ! $this->hasIndex('conversation_participants', 'cp_user_left_idx')) {
            Schema::table('conversation_participants', function (Blueprint $table) {
                $table->index(['user_id', 'left_at'], 'cp_user_left_idx');
            });
        }

        if (Schema::hasTable('messages')
            && ! $this->hasIndex('messages', 'messages_conv_id_idx')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->index(['conversation_id', 'id'], 'messages_conv_id_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('conversation_participants')
            && $this->hasIndex('conversation_participants', 'cp_user_left_idx')) {
            Schema::table('conversation_participants', function (Blueprint $table) {
                $table->dropIndex('cp_user_left_idx');
            });
        }

        if (Schema::hasTable('messages')
            && $this->hasIndex('messages', 'messages_conv_id_idx')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropIndex('messages_conv_id_idx');
            });
        }
    }

    /** ساختنِ دوبارهٔ ایندکسِ موجود خطا می‌دهد، پس اول می‌پرسیم. */
    private function hasIndex(string $table, string $index): bool
    {
        try {
            return ! empty(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]));
        } catch (\Throwable) {
            // sqlite و امثالش SHOW INDEX ندارند — آن‌جا ساختنِ ایندکس
            // بی‌خطر است چون جدول تازه ساخته شده.
            return false;
        }
    }
};
