<?php

use Illuminate\Database\Migrations\Migration;

/**
 * مجوزِ مدیریتِ کیف‌پولِ مشتری و درخواست‌های برداشت — یک مجوزِ واحد.
 * به نقشِ admin و نقش‌های مالیِ مدیریتی (manage-permissions / manage-crm-financial)
 * داده می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $perm = \Spatie\Permission\Models\Permission::firstOrCreate(
                ['name' => 'manage-customer-wallet', 'guard_name' => 'web']
            );

            \Spatie\Permission\Models\Role::query()
                ->where('name', 'admin')
                ->orWhereHas('permissions', fn ($q) => $q->whereIn('name', ['manage-permissions', 'manage-crm-financial']))
                ->get()
                ->each(fn ($role) => $role->givePermissionTo($perm));

            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // جدول‌های permission آماده نیستند — seeder می‌سازد.
        }
    }

    public function down(): void
    {
        // permission عمداً حذف نمی‌شود.
    }
};
