<?php

namespace Modules\Staff\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
        'view-okr' => 'مشاهده OKR',
        'manage-okr' => 'مدیریت OKR',
        'view-salary' => 'مشاهده حقوق خود',
        'manage-salary' => 'مدیریت حقوق',
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
        $query = User::staff()->with(['roles', 'presence']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('mobile', 'like', "%{$search}%");
            });
        }

        $staff = $query->latest()->paginate(20);

        return view('staff::index', compact('staff'));
    }

    public function create()
    {
        $roles = Role::with('permissions')->get();

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
            'is_active' => 'boolean',
            'can_add_group_members' => 'boolean',
            'role' => 'nullable|exists:roles,name',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'birth_date' => 'nullable|string',
        ]);

        $userData = [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'mobile' => $validated['mobile'],
            'email' => $validated['email'] ?? null,
            'password' => Hash::make($validated['password']),
            'mobile_verified_at' => now(),
            'is_staff' => true,
            'is_active' => $validated['is_active'] ?? true,
            'can_add_group_members' => $request->boolean('can_add_group_members'),
        ];

        // Convert Jalali birth_date to Gregorian
        if (!empty($validated['birth_date'])) {
            $userData['birth_date'] = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $validated['birth_date'])->toCarbon();
        }

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            $userData['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user = User::create($userData);

        // Assign role to user
        $user->syncRoles($validated['role'] ?? 'staff');

        return redirect()->route('admin.staff.index')->with('success', 'پرسنل جدید ایجاد شد');
    }

    public function edit(User $staff)
    {
        if (!$staff->is_staff) abort(404);

        $roles = Role::with('permissions')->get();
        $userRole = $staff->roles->first()?->name ?? 'staff';

        return view('staff::edit', compact('staff', 'roles', 'userRole'));
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
            'is_active' => 'boolean',
            'can_add_group_members' => 'boolean',
            'role' => 'nullable|exists:roles,name',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'birth_date' => 'nullable|string',
        ]);

        $updateData = [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'mobile' => $validated['mobile'],
            'email' => $validated['email'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'can_add_group_members' => $request->boolean('can_add_group_members'),
        ];

        // Convert Jalali birth_date to Gregorian
        if (!empty($validated['birth_date'])) {
            $updateData['birth_date'] = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $validated['birth_date'])->toCarbon();
        }

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($staff->avatar) {
                Storage::disk('public')->delete($staff->avatar);
            }
            $updateData['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $staff->update($updateData);

        if (!empty($validated['password'])) {
            $staff->update(['password' => Hash::make($validated['password'])]);
        }

        // Sync role
        $staff->syncRoles($validated['role'] ?? 'staff');

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
