<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Staff management
            'manage-staff',
            // Chat/Messenger
            'view-conversations', 'manage-conversations',
            // Attendance
            'manage-attendance',
            // Warehouse
            'view-warehouse', 'manage-warehouse',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $supportRole = Role::firstOrCreate(['name' => 'support', 'guard_name' => 'web']);

        // Admin gets all permissions
        $adminRole->syncPermissions(Permission::all());

        // Manager permissions
        $managerRole->syncPermissions([
            'view-conversations', 'manage-conversations',
            'manage-attendance',
        ]);

        // Support permissions
        $supportRole->syncPermissions([
            'view-conversations',
        ]);
    }
}
