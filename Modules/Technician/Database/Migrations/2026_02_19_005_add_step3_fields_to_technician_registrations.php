<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->string('field_of_study')->nullable()->after('city');
            $table->boolean('has_business_license')->default(false)->after('field_of_study');
            $table->boolean('has_shop')->default(false)->after('has_business_license');
            $table->text('shop_address')->nullable()->after('has_shop');
            $table->string('shop_phone', 20)->nullable()->after('shop_address');
            $table->json('work_experiences')->nullable()->after('shop_phone');
            $table->json('certificates')->nullable()->after('work_experiences');
        });
    }

    public function down(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->dropColumn([
                'field_of_study',
                'has_business_license',
                'has_shop',
                'shop_address',
                'shop_phone',
                'work_experiences',
                'certificates',
            ]);
        });
    }
};
