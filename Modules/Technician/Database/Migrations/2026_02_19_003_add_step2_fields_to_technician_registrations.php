<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->string('shenasname_number', 20)->nullable()->after('identity_verified');
            $table->string('province', 100)->nullable()->after('shenasname_number');
            $table->string('city', 100)->nullable()->after('province');
        });
    }

    public function down(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->dropColumn(['shenasname_number', 'province', 'city']);
        });
    }
};
