<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * فیلدهای auth برای پنل تکنسین — تکنسین مستقیم با Eloquent guard
     * 'tech' لاگین می‌کند بدون نیاز به جدول users.
     *
     * password nullable است چون تکنسین‌ها به‌صورت پیش‌فرض با OTP وارد
     * می‌شوند و فقط در صورت تنظیم در پروفایل، password پیدا می‌کنند.
     */
    public function up(): void
    {
        Schema::table('crm_technicians', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_technicians', 'password')) {
                $table->string('password')->nullable()->after('mobile');
            }
            if (! Schema::hasColumn('crm_technicians', 'remember_token')) {
                $table->rememberToken()->after('password');
            }
            if (! Schema::hasColumn('crm_technicians', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('remember_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_technicians', function (Blueprint $table) {
            foreach (['password', 'remember_token', 'last_login_at'] as $col) {
                if (Schema::hasColumn('crm_technicians', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
