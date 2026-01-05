<?php

namespace Modules\Staff\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class StaffController extends Controller
{
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
        return view('staff::edit', compact('staff', 'roles'));
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
