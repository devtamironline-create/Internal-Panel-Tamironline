<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * رأیِ انجمن از IP-based به account-based:
 *
 * درخواست‌های رأی از سرورِ فرانت (BFF) می‌آیند و همهٔ کاربران یک IP دارند —
 * uniqueِ (id, ip) عملاً یعنی «فقط اولین رأیِ کلِ سایت ثبت شود». حالا:
 *   - customer_id به هر ۴ جدولِ رأی اضافه می‌شود.
 *   - uniqueِ قدیمیِ IP حذف و uniqueِ (id, customer_id) جایگزین می‌شود.
 *   - ردیف‌های قدیمیِ IP-based (customer_id=NULL) برای حفظِ شمارنده‌ها می‌مانند.
 */
return new class extends Migration
{
    /** @var array<string, array{fk:string, oldUnique:string, newUnique:string}> */
    private array $tables = [
        'site_forum_question_upvotes' => ['fk' => 'question_id', 'oldUnique' => 'uk_forum_q_ip', 'newUnique' => 'uk_forum_q_up_customer'],
        'site_forum_answer_upvotes' => ['fk' => 'answer_id', 'oldUnique' => 'uk_forum_ans_ip', 'newUnique' => 'uk_forum_a_up_customer'],
        'site_forum_question_downvotes' => ['fk' => 'question_id', 'oldUnique' => 'uk_forum_q_down_ip', 'newUnique' => 'uk_forum_q_down_customer'],
        'site_forum_answer_downvotes' => ['fk' => 'answer_id', 'oldUnique' => 'uk_forum_a_down_ip', 'newUnique' => 'uk_forum_a_down_customer'],
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $cfg) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table, $cfg) {
                if (! Schema::hasColumn($table, 'customer_id')) {
                    $t->unsignedBigInteger('customer_id')->nullable()->after($cfg['fk']);
                }
                // ایندکسِ ساده روی FK — تا حذفِ uniqueِ قدیمی (که ایندکسِ FK بود در
                // MySQL) شکست نخورد.
                $t->index($cfg['fk'], $table.'_fk_idx');
            });

            Schema::table($table, function (Blueprint $t) use ($cfg) {
                try {
                    $t->dropUnique($cfg['oldUnique']);
                } catch (\Throwable $e) {
                    // ایندکس قبلاً حذف شده — idempotent.
                }
            });

            Schema::table($table, function (Blueprint $t) use ($cfg) {
                // NULLهای قدیمی (IP-based) در uniqueِ MySQL آزادند — فقط ردیف‌های
                // customerدار یکتا می‌شوند.
                $t->unique([$cfg['fk'], 'customer_id'], $cfg['newUnique']);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table => $cfg) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table, $cfg) {
                try {
                    $t->dropUnique($cfg['newUnique']);
                } catch (\Throwable $e) {
                }
                if (Schema::hasColumn($table, 'customer_id')) {
                    $t->dropColumn('customer_id');
                }
            });
        }
    }
};
