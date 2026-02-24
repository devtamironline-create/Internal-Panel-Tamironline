<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->decimal('commission_percent', 5, 2)->nullable()->after('contract_signature');
            $table->string('promissory_note_amount')->nullable()->after('commission_percent');
        });
    }

    public function down(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->dropColumn(['commission_percent', 'promissory_note_amount']);
        });
    }
};
