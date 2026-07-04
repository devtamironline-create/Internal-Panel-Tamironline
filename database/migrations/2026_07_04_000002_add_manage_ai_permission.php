<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * دسترسیِ مدیریتِ AI — پیکربندیِ مدل‌ها و اجرای وظایفِ AI (مثلِ مودریشن).
 */
return new class extends Migration
{
    private array $permissions = [
        'manage-ai',
    ];

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $admin = Role::where('name', 'admin')->first();

        foreach ($this->permissions as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            if ($admin && ! $admin->hasPermissionTo($name)) {
                $admin->givePermissionTo($permission);
            }
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', $this->permissions)->delete();
    }
};
