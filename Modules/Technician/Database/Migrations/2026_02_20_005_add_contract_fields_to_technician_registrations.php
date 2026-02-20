<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->timestamp('contract_signed_at')->nullable()->after('rejection_reason');
            $table->longText('contract_signature')->nullable()->after('contract_signed_at');
        });
    }

    public function down(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->dropColumn(['contract_signed_at', 'contract_signature']);
        });
    }
};
