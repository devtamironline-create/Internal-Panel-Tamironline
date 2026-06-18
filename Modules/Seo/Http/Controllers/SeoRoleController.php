<?php

namespace Modules\Seo\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Seo\Models\SeoChangeLog;
use Modules\Seo\Support\SeoPermissions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Role Manager سئو — تعیین این‌که چه نقش‌هایی دسترسی manage-seo و دسترسی‌های ریزدانه دارند.
 */
class SeoRoleController extends Controller
{
    private const PERMISSION = SeoPermissions::MANAGE;

    public function index()
    {
        Permission::findOrCreate(self::PERMISSION, 'web');
        foreach (SeoPermissions::keys() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $granularLabels = SeoPermissions::labels();
        $granularKeys = SeoPermissions::keys();

        $roles = Role::query()->orderBy('name')->get()->map(function (Role $r) use ($granularKeys) {
            $granular = [];
            foreach ($granularKeys as $name) {
                $granular[$name] = $r->hasPermissionTo($name);
            }

            return [
                'id' => $r->id,
                'name' => $r->name,
                'has' => $r->hasPermissionTo(self::PERMISSION),
                'granular' => $granular,
            ];
        });

        return view('seo::roles.index', compact('roles', 'granularLabels'));
    }

    public function update(Request $request)
    {
        $managePermission = Permission::findOrCreate(self::PERMISSION, 'web');
        $checked = array_map('intval', (array) $request->input('roles', []));

        // ماتریسِ دسترسی‌های ریزدانه: granular[permissionKey][] = roleId
        $granularInput = (array) $request->input('granular', []);

        foreach (Role::all() as $role) {
            // manage-seo (مثل قبل)
            if (in_array($role->id, $checked, true)) {
                $role->givePermissionTo($managePermission);
            } else {
                $role->revokePermissionTo($managePermission);
            }

            // دسترسی‌های ریزدانه
            foreach (SeoPermissions::keys() as $name) {
                $permission = Permission::findOrCreate($name, 'web');
                $rolesForPerm = array_map('intval', (array) ($granularInput[$name] ?? []));
                if (in_array($role->id, $rolesForPerm, true)) {
                    $role->givePermissionTo($permission);
                } else {
                    $role->revokePermissionTo($permission);
                }
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        SeoChangeLog::record('updated', 'roles', 'دسترسی نقش‌ها به مدیریت سئو به‌روزرسانی شد.');

        return back()->with('success', 'دسترسی نقش‌ها ذخیره شد.');
    }
}
