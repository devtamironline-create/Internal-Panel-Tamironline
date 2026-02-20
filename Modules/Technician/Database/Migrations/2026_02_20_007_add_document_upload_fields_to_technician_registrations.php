<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->string('doc_national_card_front')->nullable()->after('contract_signature');
            $table->string('doc_national_card_back')->nullable()->after('doc_national_card_front');
            $table->string('doc_birth_certificate_p1')->nullable()->after('doc_national_card_back');
            $table->string('doc_birth_certificate_p2')->nullable()->after('doc_birth_certificate_p1');
            $table->string('doc_criminal_record')->nullable()->after('doc_birth_certificate_p2');
            $table->string('doc_photo_3x4')->nullable()->after('doc_criminal_record');
            $table->string('doc_lease_agreement')->nullable()->after('doc_photo_3x4');
            $table->string('doc_utility_bill')->nullable()->after('doc_lease_agreement');
            $table->boolean('documents_uploaded')->default(false)->after('doc_utility_bill');
        });
    }

    public function down(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->dropColumn([
                'doc_national_card_front',
                'doc_national_card_back',
                'doc_birth_certificate_p1',
                'doc_birth_certificate_p2',
                'doc_criminal_record',
                'doc_photo_3x4',
                'doc_lease_agreement',
                'doc_utility_bill',
                'documents_uploaded',
            ]);
        });
    }
};
