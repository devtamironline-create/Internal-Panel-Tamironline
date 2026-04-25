<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * هم‌سو سازی فیلدهای مشتری با CRM وردپرسی.
 *
 * در WP هر مشتری روی جدول users با متاهای مختصر (mobile, first_name, phone,
 * role=customer) ذخیره می‌شود. آدرس روی هر سفارش نوشته می‌شود نه روی مشتری.
 * بنابراین فیلدهای زیر که در نسخه اولیه Laravel اضافه شده بودند حذف می‌شوند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_customers', function (Blueprint $table) {
            // ابتدا foreign keys مرتبط با province/city
            $table->dropForeign(['province_id']);
            $table->dropForeign(['city_id']);
        });

        Schema::table('crm_customers', function (Blueprint $table) {
            // ایندکس‌های مرتبط با ستون‌های حذف‌شدنی
            $table->dropIndex(['is_active']);
            $table->dropIndex(['registered_via']);
            $table->dropIndex(['first_name', 'last_name']);
        });

        Schema::table('crm_customers', function (Blueprint $table) {
            $table->dropColumn([
                'last_name',
                'email',
                'national_code',
                'province_id',
                'city_id',
                'address',
                'postal_code',
                'is_active',
                'registered_via',
                'app_user_id',
                'last_app_login_at',
            ]);
        });

        Schema::table('crm_customers', function (Blueprint $table) {
            // ایندکس روی first_name برای جستجوی نام
            $table->index('first_name');
        });
    }

    public function down(): void
    {
        Schema::table('crm_customers', function (Blueprint $table) {
            $table->dropIndex(['first_name']);
        });

        Schema::table('crm_customers', function (Blueprint $table) {
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('email')->nullable()->after('phone');
            $table->string('national_code', 20)->nullable()->after('email');
            $table->foreignId('province_id')->nullable()->after('national_code')->constrained('crm_provinces')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->after('province_id')->constrained('crm_cities')->nullOnDelete();
            $table->text('address')->nullable()->after('city_id');
            $table->string('postal_code', 20)->nullable()->after('address');
            $table->boolean('is_active')->default(true)->after('notes');
            $table->enum('registered_via', ['app', 'panel', 'import'])->default('panel')->after('is_active');
            $table->string('app_user_id')->nullable()->after('registered_via');
            $table->timestamp('last_app_login_at')->nullable()->after('app_user_id');

            $table->index('is_active');
            $table->index('registered_via');
            $table->index(['first_name', 'last_name']);
        });
    }
};
