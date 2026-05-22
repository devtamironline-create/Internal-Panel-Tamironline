<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_devices', function (Blueprint $table) {
            $table->string('thumbnail', 500)->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('crm_devices', function (Blueprint $table) {
            $table->dropColumn('thumbnail');
        });
    }
};
