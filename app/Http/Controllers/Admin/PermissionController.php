<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:manage-permissions');
    }

    /**
     * Show staff permissions management page
     */
    public function index()
    {
        $staff = User::staff()->with('roles', 'permissions')->get();
        $roles = Role::all();
        $permissions = Permission::all()->groupBy(function ($permission) {
            // Group permissions by category
            $name = $permission->name;
            if (str_contains($name, 'staff')) return 'پرسنل';
            if (str_contains($name, 'attendance')) return 'حضور و غیاب';
            if (str_contains($name, 'leave')) return 'مرخصی';
            if (str_contains($name, 'task')) return 'تسک';
            if (str_contains($name, 'team')) return 'تیم';
            if (str_contains($name, 'report')) return 'گزارش';
            if (str_contains($name, 'warehouse')) return 'انبار';
            if (str_contains($name, 'messenger')) return 'پیام‌رسان';
            if (str_contains($name, 'setting') || str_contains($name, 'permission')) return 'تنظیمات';
            return 'سایر';
        });

        return view('admin.permissions.index', compact('staff', 'roles', 'permissions'));
    }

    /**
     * Edit user permissions
     */
    public function edit(User $user)
    {
        $roles = Role::all();
        $permissions = Permission::all()->groupBy(function ($permission) {
            $name = $permission->name;
            if (str_contains($name, 'staff')) return 'پرسنل';
            if (str_contains($name, 'attendance')) return 'حضور و غیاب';
            if (str_contains($name, 'leave')) return 'مرخصی';
            if (str_contains($name, 'task')) return 'تسک';
            if (str_contains($name, 'team')) return 'تیم';
            if (str_contains($name, 'report')) return 'گزارش';
            if (str_contains($name, 'warehouse')) return 'انبار';
            if (str_contains($name, 'messenger')) return 'پیام‌رسان';
            if (str_contains($name, 'setting') || str_contains($name, 'permission')) return 'تنظیمات';
            return 'سایر';
        });

        $userPermissions = $user->getDirectPermissions()->pluck('name')->toArray();
        $userRoles = $user->roles->pluck('name')->toArray();

        return view('admin.permissions.edit', compact('user', 'roles', 'permissions', 'userPermissions', 'userRoles'));
    }

    /**
     * Update user permissions
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        // Sync roles
        $user->syncRoles($request->input('roles', []));

        // Sync direct permissions (those not from roles)
        $user->syncPermissions($request->input('permissions', []));

        return redirect()->route('admin.permissions.index')
            ->with('success', 'دسترسی‌های کاربر با موفقیت بروزرسانی شد');
    }

    /**
     * Get permission labels in Persian
     */
    public static function getPermissionLabel(string $name): string
    {
        $labels = [
            'view-staff' => 'مشاهده پرسنل',
            'manage-staff' => 'مدیریت پرسنل',
            'view-attendance' => 'مشاهده حضور و غیاب',
            'manage-attendance' => 'مدیریت حضور و غیاب',
            'view-leave' => 'مشاهده مرخصی',
            'request-leave' => 'درخواست مرخصی',
            'manage-leave' => 'مدیریت مرخصی',
            'view-tasks' => 'مشاهده تسک‌ها',
            'create-tasks' => 'ایجاد تسک',
            'manage-tasks' => 'مدیریت تسک‌ها',
            'view-teams' => 'مشاهده تیم‌ها',
            'manage-teams' => 'مدیریت تیم‌ها',
            'view-reports' => 'مشاهده گزارش‌ها',
            'view-okr' => 'مشاهده OKR',
            'manage-okr' => 'مدیریت OKR',
            'view-salary' => 'مشاهده حقوق',
            'manage-salary' => 'مدیریت حقوق',
            'use-messenger' => 'پیام‌رسان',
            'view-warehouse' => 'مشاهده انبار',
            'manage-warehouse' => 'مدیریت انبار',
            'manage-settings' => 'مدیریت تنظیمات',
            'manage-permissions' => 'مدیریت دسترسی‌ها',
        ];

        return $labels[$name] ?? $name;
    }

    /**
     * Get role labels in Persian
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
