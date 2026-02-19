<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('birth_date');
            $table->string('last_name', 100)->nullable()->after('first_name');
            $table->string('father_name', 100)->nullable()->after('last_name');
            $table->timestamp('mobile_verified_at')->nullable()->after('father_name');
            $table->boolean('identity_verified')->default(false)->after('mobile_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'father_name', 'mobile_verified_at', 'identity_verified']);
        });
    }
};
