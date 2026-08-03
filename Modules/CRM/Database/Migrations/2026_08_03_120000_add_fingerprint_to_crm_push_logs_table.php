<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اثرِ انگشتِ رویداد روی لاگِ پوش — منبعِ حقیقتِ ضدِتکرار.
 *
 * پیامک همین کار را با چسباندنِ یک برچسبِ کوتاه به ستونِ `body` لاگ انجام
 * می‌دهد و بعد با `like` دنبالش می‌گردد. برای پوش ستونِ جدا گرفتیم:
 * `body` این‌جا دقیقاً همان چیزی است که روی گوشیِ تکنسین دیده می‌شود و
 * آلوده‌کردنش با برچسبِ ماشینی درست نیست — ضمنِ اینکه `like '%…%'` هیچ
 * ایندکسی نمی‌گیرد.
 */
return new class extends Migration
{
    private const TABLE = 'crm_push_logs';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || Schema::hasColumn(self::TABLE, 'fingerprint')) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->string('fingerprint', 100)->nullable()->after('event_key');
            // ضدِتکرار همیشه با این سه ستون با هم می‌پرسد.
            $table->index(['technician_id', 'event_key', 'fingerprint'], 'push_logs_dedupe_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, 'fingerprint')) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropIndex('push_logs_dedupe_index');
            $table->dropColumn('fingerprint');
        });
    }
};
