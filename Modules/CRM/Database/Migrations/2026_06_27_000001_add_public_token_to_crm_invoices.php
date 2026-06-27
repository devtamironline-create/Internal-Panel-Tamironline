<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * افزودن public_token غیرقابل‌حدس به crm_invoices.
 *
 * علت: لینک عمومیِ فاکتور (پیامک به مشتری) قبلاً از invoice_code ترتیبی
 * (INV-YYMM-NNNNN) استفاده می‌کرد؛ کاربر با تغییر شماره می‌توانست فاکتورِ
 * دیگران را ببیند (IDOR). از این پس لینک عمومی با این توکنِ تصادفی کار می‌کند
 * تا شمارش/حدس ممکن نباشد.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('crm_invoices', 'public_token')) {
            Schema::table('crm_invoices', function (Blueprint $table) {
                $table->string('public_token', 64)->nullable()->after('invoice_code');
            });
        }

        // backfill فاکتورهای موجود با توکنِ یکتا
        DB::table('crm_invoices')->whereNull('public_token')->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('crm_invoices')->where('id', $row->id)
                        ->update(['public_token' => Str::random(40)]);
                }
            });

        // unique بعد از backfill (collision در Str::random(40) عملاً صفر است)
        Schema::table('crm_invoices', function (Blueprint $table) {
            $table->unique('public_token', 'uk_invoice_public_token');
        });
    }

    public function down(): void
    {
        Schema::table('crm_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('crm_invoices', 'public_token')) {
                $table->dropUnique('uk_invoice_public_token');
                $table->dropColumn('public_token');
            }
        });
    }
};
