<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            if (!Schema::hasColumn('technician_registrations', 'doc_photo_3x4')) {
                $table->string('doc_photo_3x4')->nullable()->after('doc_criminal_record');
            }
            if (!Schema::hasColumn('technician_registrations', 'doc_lease_agreement')) {
                $table->string('doc_lease_agreement')->nullable()->after('doc_photo_3x4');
            }
            if (!Schema::hasColumn('technician_registrations', 'doc_utility_bill')) {
                $table->string('doc_utility_bill')->nullable()->after('doc_lease_agreement');
            }
            if (!Schema::hasColumn('technician_registrations', 'documents_uploaded')) {
                $table->boolean('documents_uploaded')->default(false)->after('doc_utility_bill');
            }
        });
    }

    public function down(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $columns = ['doc_photo_3x4', 'doc_lease_agreement', 'doc_utility_bill', 'documents_uploaded'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('technician_registrations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
