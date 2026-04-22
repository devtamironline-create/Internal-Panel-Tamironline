<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_customers', function (Blueprint $table) {
            $table->id();

            // شناسه و تماس
            $table->string('mobile', 20)->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('national_code', 20)->nullable();

            // آدرس
            $table->foreignId('province_id')->nullable()->constrained('crm_provinces')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('crm_cities')->nullOnDelete();
            $table->text('address')->nullable();
            $table->string('postal_code', 20)->nullable();

            // یادداشت و وضعیت
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            // منبع ثبت و ردگیری اپلیکیشن
            $table->enum('registered_via', ['app', 'panel', 'import'])->default('panel');
            $table->string('app_user_id')->nullable();
            $table->timestamp('last_app_login_at')->nullable();

            $table->timestamps();

            $table->index('is_active');
            $table->index('registered_via');
            $table->index(['first_name', 'last_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_customers');
    }
};
