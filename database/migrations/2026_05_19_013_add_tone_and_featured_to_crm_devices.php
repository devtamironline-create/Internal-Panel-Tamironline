<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_devices', function (Blueprint $table) {
            $table->string('tone', 30)->nullable()->after('icon');
            $table->boolean('is_featured')->default(false)->after('is_active')->index();
        });
    }

    public function down(): void
    {
        Schema::table('crm_devices', function (Blueprint $table) {
            $table->dropIndex(['is_featured']);
            $table->dropColumn(['tone', 'is_featured']);
        });
    }
};
