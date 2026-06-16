<?php

namespace Modules\Seo\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Modules\Seo\Models\SeoChangeLog;

/**
 * Role Manager سئو — تعیین این‌که چه نقش‌هایی دسترسی manage-seo دارند.
 */
class SeoRoleController extends Controller
{
    private const PERMISSION = 'manage-seo';

    public function index()
    {
        Permission::findOrCreate(self::PERMISSION, 'web');

        $roles = Role::query()->orderBy('name')->get()->map(fn (Role $r) => [
            'id' => $r->id,
            'name' => $r->name,
            'has' => $r->hasPermissionTo(self::PERMISSION),
        ]);

        return view('seo::roles.index', compact('roles'));
    }

    public function update(Request $request)
    {
        $permission = Permission::findOrCreate(self::PERMISSION, 'web');
        $checked = array_map('intval', (array) $request->input('roles', []));

        foreach (Role::all() as $role) {
            if (in_array($role->id, $checked, true)) {
                $role->givePermissionTo($permission);
            } else {
                $role->revokePermissionTo($permission);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        SeoChangeLog::record('updated', 'roles', 'دسترسی نقش‌ها به مدیریت سئو به‌روزرسانی شد.');

        return back()->with('success', 'دسترسی نقش‌ها ذخیره شد.');
    }
}
