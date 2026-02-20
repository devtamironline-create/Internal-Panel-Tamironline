<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->string('national_card_serial', 20)->nullable()->after('national_code');
            $table->string('biometric_status', 20)->nullable()->after('identity_verified'); // pending, verified, rejected
            $table->string('biometric_session_id')->nullable()->after('biometric_status');
            $table->string('biometric_media_id')->nullable()->after('biometric_session_id');
            $table->string('biometric_reject_reason')->nullable()->after('biometric_media_id');
            $table->timestamp('biometric_verified_at')->nullable()->after('biometric_reject_reason');
        });
    }

    public function down(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->dropColumn([
                'national_card_serial',
                'biometric_status',
                'biometric_session_id',
                'biometric_media_id',
                'biometric_reject_reason',
                'biometric_verified_at',
            ]);
        });
    }
};
