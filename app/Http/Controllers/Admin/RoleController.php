<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:manage-permissions');
    }

    /**
     * نمایش لیست نقش‌ها
     */
    public function index()
    {
        $roles = Role::withCount('users', 'permissions')->get();

        return view('admin.roles.index', compact('roles'));
    }

    /**
     * فرم ایجاد نقش
     */
    public function create()
    {
        $permissions = $this->getGroupedPermissions();

        return view('admin.roles.create', compact('permissions'));
    }

    /**
     * ذخیره نقش جدید
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:roles,name',
            'label' => 'required|string|max:100',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'web',
        ]);

        // Store label in a custom way (we'll add this to a config or use a convention)
        // For now, sync permissions
        $role->syncPermissions($request->input('permissions', []));

        return redirect()->route('admin.roles.index')
            ->with('success', "نقش «{$request->label}» با موفقیت ایجاد شد");
    }

    /**
     * ویرایش نقش
     */
    public function edit(Role $role)
    {
        $permissions = $this->getGroupedPermissions();
        $rolePermissions = $role->permissions->pluck('name')->toArray();

        return view('admin.roles.edit', compact('role', 'permissions', 'rolePermissions'));
    }

    /**
     * به‌روزرسانی نقش
     */
    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:roles,name,' . $role->id,
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role->update([
            'name' => $request->name,
        ]);

        $role->syncPermissions($request->input('permissions', []));

        return redirect()->route('admin.roles.index')
            ->with('success', 'نقش با موفقیت بروزرسانی شد');
    }

    /**
     * حذف نقش
     */
    public function destroy(Role $role)
    {
        // Don't allow deleting built-in roles
        $builtInRoles = ['admin', 'manager', 'supervisor', 'staff'];
        if (in_array($role->name, $builtInRoles)) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'نقش‌های پیش‌فرض قابل حذف نیستند');
        }

        // Remove role from all users first
        $role->users()->each(function ($user) use ($role) {
            $user->removeRole($role);
        });

        $role->delete();

        return redirect()->route('admin.roles.index')
            ->with('success', 'نقش با موفقیت حذف شد');
    }

    /**
     * گروه‌بندی دسترسی‌ها
     */
    protected function getGroupedPermissions()
    {
        return Permission::all()->groupBy(function ($permission) {
            $name = $permission->name;
            if (str_contains($name, 'staff')) return 'پرسنل';
            if (str_contains($name, 'attendance')) return 'حضور و غیاب';
            if (str_contains($name, 'leave')) return 'مرخصی';
            if (str_contains($name, 'task')) return 'تسک';
            if (str_contains($name, 'team')) return 'تیم';
            if (str_contains($name, 'report')) return 'گزارش';
            if (str_contains($name, 'okr')) return 'OKR';
            if (str_contains($name, 'salary')) return 'حقوق';
            if (str_contains($name, 'messenger')) return 'پیام‌رسان';
            if (str_contains($name, 'setting') || str_contains($name, 'permission')) return 'تنظیمات';
            return 'سایر';
        });
    }

    /**
     * لیبل فارسی نقش
     */
    public static function getRoleLabel(string $name): string
    {
        $labels = [
            'admin' => 'مدیر سیستم',
            'manager' => 'مدیر',
            'supervisor' => 'سرپرست',
            'staff' => 'کارمند',
        ];

        return $labels[$name] ?? $name;
    }
}
