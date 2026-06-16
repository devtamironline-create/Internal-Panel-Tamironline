<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private array $permissions = [
        'manage-seo',
    ];

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $admin = Role::where('name', 'admin')->first();
        $manager = Role::where('name', 'manager')->first();

        foreach ($this->permissions as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            foreach ([$admin, $manager] as $role) {
                if ($role && ! $role->hasPermissionTo($name)) {
                    $role->givePermissionTo($permission);
                }
            }
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', $this->permissions)->delete();
    }
};
