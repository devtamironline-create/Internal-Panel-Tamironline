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
            'view-customers', 'create-customers', 'edit-customers', 'delete-customers',
            'view-tickets', 'reply-tickets', 'close-tickets', 'delete-tickets', 'assign-tickets',
            'view-invoices', 'create-invoices', 'edit-invoices', 'delete-invoices', 'mark-invoices-paid',
            'view-products', 'create-products', 'edit-products', 'delete-products',
            'view-services', 'create-services', 'edit-services', 'delete-services', 'suspend-services', 'terminate-services',
            'manage-staff', 'manage-settings',
            'view-reports', 'view-transactions', 'manage-wallets',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $supportRole = Role::firstOrCreate(['name' => 'support', 'guard_name' => 'web']);
        $salesRole = Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);

        $adminRole->syncPermissions(Permission::all());

        $managerRole->syncPermissions([
            'view-customers', 'create-customers', 'edit-customers',
            'view-tickets', 'reply-tickets', 'close-tickets', 'assign-tickets',
            'view-invoices', 'create-invoices', 'edit-invoices', 'mark-invoices-paid',
            'view-products', 'create-products', 'edit-products',
            'view-services', 'create-services', 'edit-services', 'suspend-services',
            'view-reports', 'view-transactions',
        ]);

        $supportRole->syncPermissions([
            'view-customers',
            'view-tickets', 'reply-tickets', 'close-tickets',
            'view-services',
        ]);

        $salesRole->syncPermissions([
            'view-customers', 'create-customers', 'edit-customers',
            'view-invoices', 'create-invoices',
            'view-products',
            'view-services', 'create-services',
        ]);
    }
}
