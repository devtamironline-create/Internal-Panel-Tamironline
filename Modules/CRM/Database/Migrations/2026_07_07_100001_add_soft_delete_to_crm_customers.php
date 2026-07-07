<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حذفِ نرمِ حسابِ مشتری (درخواستِ خودِ کاربر از اپ) — رکورد حفظ می‌شود تا
 * ادمین اطلاعات را ببیند و بتواند حساب را بازگرداند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_customers', function (Blueprint $t) {
            if (! Schema::hasColumn('crm_customers', 'deleted_at')) {
                $t->softDeletes();
            }
            if (! Schema::hasColumn('crm_customers', 'delete_reason')) {
                $t->string('delete_reason', 500)->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_customers', function (Blueprint $t) {
            if (Schema::hasColumn('crm_customers', 'deleted_at')) {
                $t->dropSoftDeletes();
            }
            if (Schema::hasColumn('crm_customers', 'delete_reason')) {
                $t->dropColumn('delete_reason');
            }
        });
    }
};
