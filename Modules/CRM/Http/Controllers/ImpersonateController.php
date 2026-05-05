<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\CRM\Models\Technician;

/**
 * ورود ادمین به پنل تکنسین (impersonation) — حالا با guard مجزای 'tech'.
 *
 * - ادمین روی web guard لاگین می‌ماند تا بدون login مجدد بتواند برگردد.
 * - تکنسین روی guard 'tech' لاگین می‌شود (مستقیم روی crm_technicians،
 *   بدون نیاز به ساختن User).
 * - ID ادمین در session.tech_impersonator_user_id ذخیره می‌شود تا layout
 *   پنل تکنسین بنر بازگشت را نشان دهد و leave() بتواند به همان صفحه
 *   مدیریت تکنسین‌ها برگردد.
 */
class ImpersonateController extends Controller
{
    public function start(Technician $technician): RedirectResponse
    {
        if (empty($technician->mobile)) {
            return back()->with('error', 'تکنسین موبایل ندارد؛ امکان ورود به پنلش نیست.');
        }

        $adminId = (int) Auth::id();

        Auth::guard('tech')->login($technician);
        session(['tech_impersonator_user_id' => $adminId]);

        $technician->forceFill(['last_login_at' => now()])->save();

        Log::info('crm.impersonate.start', [
            'admin_id' => $adminId,
            'technician_id' => $technician->id,
        ]);

        return redirect()->route('tech.dashboard')
            ->with('success', 'به پنل تکنسین «' . $technician->full_name . '» وارد شدید.');
    }

    public function leave(Request $request): RedirectResponse
    {
        $adminId = (int) $request->session()->get('tech_impersonator_user_id');

        if (Auth::guard('tech')->check()) {
            Auth::guard('tech')->logout();
        }
        $request->session()->forget('tech_impersonator_user_id');

        Log::info('crm.impersonate.leave', [
            'admin_id' => $adminId,
        ]);

        // اگر ادمین هنوز روی web guard لاگین است، به لیست تکنسین‌ها برمی‌گردیم.
        // اگر سشن web گم شده باشد، middleware auth کاربر را به login می‌فرستد.
        return redirect()->route('crm.technicians.index')
            ->with('success', 'از حالت ورود به پنل تکنسین خارج شدید.');
    }
}
