<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_technicians', function (Blueprint $table) {
            // بستانکار تکنسین (مثبت = شرکت بدهکار است، منفی = تکنسین بدهکار است)
            $table->bigInteger('wallet_balance')->default(0)->after('bank_account');
        });
    }

    public function down(): void
    {
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->dropColumn('wallet_balance');
        });
    }
};
