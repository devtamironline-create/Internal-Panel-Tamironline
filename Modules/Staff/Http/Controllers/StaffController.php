<?php

namespace Modules\Staff\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class StaffController extends Controller
{
    // Permission labels for Persian display
    private static $permissionLabels = [
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
        'use-messenger' => 'استفاده از پیام‌رسان',
        'manage-settings' => 'مدیریت تنظیمات',
        'manage-permissions' => 'مدیریت دسترسی‌ها',
    ];

    public static function getPermissionLabel(string $permission): string
    {
        return self::$permissionLabels[$permission] ?? $permission;
    }

    public function index(Request $request)
    {
        $query = User::staff()->with('roles');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('mobile', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->role($role);
        }

        $staff = $query->latest()->paginate(20);
        $roles = Role::all();

        return view('staff::index', compact('staff', 'roles'));
    }

    public function create()
    {
        $roles = Role::all();
        return view('staff::create', compact('roles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'mobile' => 'required|regex:/^09[0-9]{9}$/|unique:users',
            'email' => 'nullable|email|unique:users',
            'password' => 'required|min:8|confirmed',
            'role' => 'required|exists:roles,name',
            'is_active' => 'boolean',
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'mobile' => $validated['mobile'],
            'email' => $validated['email'] ?? null,
            'password' => Hash::make($validated['password']),
            'mobile_verified_at' => now(),
            'is_staff' => true,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $user->assignRole($validated['role']);

        return redirect()->route('admin.staff.index')->with('success', 'پرسنل جدید ایجاد شد');
    }

    public function edit(User $staff)
    {
        if (!$staff->is_staff) abort(404);
        $roles = Role::all();

        // Get all permissions grouped by category
        $allPermissions = Permission::all();
        $permissions = [
            'پرسنل' => $allPermissions->filter(fn($p) => str_contains($p->name, 'staff')),
            'حضور و غیاب' => $allPermissions->filter(fn($p) => str_contains($p->name, 'attendance')),
            'مرخصی' => $allPermissions->filter(fn($p) => str_contains($p->name, 'leave')),
            'تسک‌ها' => $allPermissions->filter(fn($p) => str_contains($p->name, 'task')),
            'تیم‌ها' => $allPermissions->filter(fn($p) => str_contains($p->name, 'team')),
            'سایر' => $allPermissions->filter(fn($p) =>
                !str_contains($p->name, 'staff') &&
                !str_contains($p->name, 'attendance') &&
                !str_contains($p->name, 'leave') &&
                !str_contains($p->name, 'task') &&
                !str_contains($p->name, 'team')
            ),
        ];

        // Get user's direct permissions
        $userPermissions = $staff->getDirectPermissions()->pluck('name')->toArray();

        return view('staff::edit', compact('staff', 'roles', 'permissions', 'userPermissions'));
    }

    public function update(Request $request, User $staff)
    {
        if (!$staff->is_staff) abort(404);

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'mobile' => 'required|regex:/^09[0-9]{9}$/|unique:users,mobile,' . $staff->id,
            'email' => 'nullable|email|unique:users,email,' . $staff->id,
            'password' => 'nullable|min:8|confirmed',
            'role' => 'required|exists:roles,name',
            'is_active' => 'boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $staff->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'mobile' => $validated['mobile'],
            'email' => $validated['email'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (!empty($validated['password'])) {
            $staff->update(['password' => Hash::make($validated['password'])]);
        }

        $staff->syncRoles([$validated['role']]);

        // Sync direct permissions
        $staff->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('admin.staff.index')->with('success', 'اطلاعات بروزرسانی شد');
    }

    public function destroy(User $staff)
    {
        if (!$staff->is_staff) abort(404);
        if ($staff->id === auth()->id()) {
            return back()->with('error', 'نمی‌توانید حساب خود را حذف کنید');
        }
        $staff->delete();
        return redirect()->route('admin.staff.index')->with('success', 'پرسنل حذف شد');
    }

    public function toggleStatus(User $staff)
    {
        if (!$staff->is_staff) abort(404);
        if ($staff->id === auth()->id()) {
            return back()->with('error', 'نمی‌توانید حساب خود را غیرفعال کنید');
        }
        $staff->update(['is_active' => !$staff->is_active]);
        return back()->with('success', 'وضعیت تغییر کرد');
    }
}
