<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * صندوق سرمایه — فاز ۳: هر ردیفِ investment_assets یک «تراکنش» است:
 * buy (افزایش سرمایه) یا sell (کاهش سرمایه/فروش). رکوردهای موجود همه
 * buy می‌مانند (default).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_assets', function (Blueprint $table) {
            $table->string('type', 10)->default('buy')->index()->after('asset');
        });
    }

    public function down(): void
    {
        Schema::table('investment_assets', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
