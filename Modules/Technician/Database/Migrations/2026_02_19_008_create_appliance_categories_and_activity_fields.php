<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appliance_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->string('activity_type')->nullable()->after('other_provinces_cities');
            $table->json('appliance_categories')->nullable()->after('activity_type');
            $table->string('transportation_method')->nullable()->after('appliance_categories');
        });
    }

    public function down(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->dropColumn(['activity_type', 'appliance_categories', 'transportation_method']);
        });

        Schema::dropIfExists('appliance_categories');
    }
};
