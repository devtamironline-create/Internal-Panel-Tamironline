<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

/**
 * نقش پیش‌فرض customer برای کاربرانی که از طریق سیستم Identity (OTP)
 * ثبت‌نام می‌کنند. در IdentityService::verifyOtp روی هر کاربر جدید اعمال می‌شود.
 *
 * Idempotent — firstOrCreate تا چندبار اجرا مشکلی نسازد.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Role::query()->where('name', 'customer')->delete();
    }
};
