<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private array $permissions = [
        'view-site-contact-messages',
        'manage-site-contact-messages',
        'manage-site-testimonials',
        'manage-site-faqs',
        'manage-site-pages',
        'manage-site-banners',
        'manage-site-settings',
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
