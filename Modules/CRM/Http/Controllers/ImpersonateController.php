<?php

namespace Modules\CRM\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\CRM\Models\Technician;

/**
 * ورود ادمین به پنل تکنسین (impersonation).
 *
 * - فقط برای کاربر با permission edit-crm-technician.
 * - اگر تکنسین کاربر سیستمی نداشته باشد، on-the-fly یکی ساخته می‌شود
 *   با نقش crm-technician (همان منطقی که در provisionUser وجود دارد،
 *   با این فرق که رمز یکباره نمایش داده نمی‌شود — برای impersonate
 *   لازم نیست).
 * - id ادمین در session.impersonator_id ذخیره می‌شود تا بعداً قابل
 *   بازگشت باشد.
 */
class ImpersonateController extends Controller
{
    public function start(Technician $technician): RedirectResponse
    {
        if (empty($technician->mobile)) {
            return back()->with('error', 'تکنسین موبایل ندارد؛ امکان ورود به پنلش نیست.');
        }

        $admin = Auth::user();
        $user = $this->ensureUser($technician);

        // ترتیب مهم: اول id ادمین را برمی‌داریم، login می‌کنیم، سپس
        // در session جدید impersonator_id را می‌گذاریم.
        $adminId = $admin->id;

        Auth::login($user);
        session(['impersonator_id' => $adminId]);

        Log::info('crm.impersonate.start', [
            'admin_id' => $adminId,
            'technician_id' => $technician->id,
            'as_user_id' => $user->id,
        ]);

        return redirect()->route('crm.tech.dashboard')
            ->with('success', 'به پنل تکنسین «' . $technician->full_name . '» وارد شدید.');
    }

    public function leave(): RedirectResponse
    {
        $original = (int) session('impersonator_id');
        if ($original <= 0) {
            return redirect()->route('crm.dashboard');
        }

        $impersonatedId = Auth::id();

        session()->forget('impersonator_id');
        Auth::loginUsingId($original);

        Log::info('crm.impersonate.leave', [
            'admin_id' => $original,
            'was_user_id' => $impersonatedId,
        ]);

        return redirect()->route('crm.technicians.index')
            ->with('success', 'به حساب ادمین بازگشتید.');
    }

    /** اگر تکنسین user_id ندارد، یک User با نقش crm-technician می‌سازد. */
    protected function ensureUser(Technician $technician): User
    {
        if ($technician->user_id) {
            $existing = User::find($technician->user_id);
            if ($existing) {
                if (! $existing->hasRole('crm-technician')) {
                    $existing->assignRole('crm-technician');
                }
                return $existing;
            }
        }

        return DB::transaction(function () use ($technician) {
            $user = User::where('mobile', $technician->mobile)->first();

            if (! $user) {
                $user = User::create([
                    'name' => $technician->full_name,
                    'first_name' => $technician->first_name,
                    'mobile' => $technician->mobile,
                    'password' => Hash::make(Str::random(32)),
                    'is_staff' => true,
                    'mobile_verified_at' => now(),
                ]);
            }

            if (! $user->hasRole('crm-technician')) {
                $user->assignRole('crm-technician');
                // pe cache permissions را پاک کن تا روی request جدید
                // نقش تازه assign‌شده فوراً معتبر باشد.
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            }

            $technician->update(['user_id' => $user->id]);

            return $user;
        });
    }
}
