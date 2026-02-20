<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->boolean('has_cooperation')->default(false)->after('shop_phone');
            $table->text('cooperation_companies')->nullable()->after('has_cooperation');
            $table->string('referrer_name', 255)->nullable()->after('cooperation_companies');
            $table->string('referrer_phone', 20)->nullable()->after('referrer_name');
            $table->string('colleague1_name', 255)->nullable()->after('referrer_phone');
            $table->string('colleague1_phone', 20)->nullable()->after('colleague1_name');
            $table->string('colleague2_name', 255)->nullable()->after('colleague1_phone');
            $table->string('colleague2_phone', 20)->nullable()->after('colleague2_name');
        });
    }

    public function down(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->dropColumn([
                'has_cooperation', 'cooperation_companies',
                'referrer_name', 'referrer_phone',
                'colleague1_name', 'colleague1_phone',
                'colleague2_name', 'colleague2_phone',
            ]);
        });
    }
};
