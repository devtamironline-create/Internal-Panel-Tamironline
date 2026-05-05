<?php

namespace Modules\CRM\Http\Controllers\Tech;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Modules\CRM\Models\Technician;
use Modules\SMS\Services\KavenegarService;

/**
 * Auth کنترلر پنل تکنسین — guard 'tech' روی crm_technicians.
 *
 * دو مسیر ورود: OTP پیامکی (پیش‌فرض) و رمز عبور (اگر تکنسین در پروفایل
 * تنظیم کرده باشد). cache key مجزا (tech_otp_*) تا با OTP ادمین/مشتری
 * که از OTPService مشترک استفاده می‌کنند تداخل نکند.
 */
class AuthController extends Controller
{
    private const OTP_TTL_SECONDS = 300;       // ۵ دقیقه
    private const OTP_RESEND_DELAY = 60;       // ۱ دقیقه
    private const OTP_MAX_ATTEMPTS = 5;

    public function __construct(protected KavenegarService $sms)
    {
    }

    public function showLoginForm()
    {
        if (Auth::guard('tech')->check()) {
            return redirect()->route('tech.dashboard');
        }
        return view('crm::tech-panel.login');
    }

    public function sendOtp(Request $request)
    {
        $request->validate([
            'mobile' => ['required', 'regex:/^09[0-9]{9}$/'],
        ], [
            'mobile.required' => 'شماره موبایل الزامی است',
            'mobile.regex' => 'فرمت شماره موبایل صحیح نیست',
        ]);

        $mobile = $request->input('mobile');

        $technician = Technician::where('mobile', $mobile)->first();
        if (! $technician) {
            return response()->json([
                'success' => false,
                'message' => 'شماره شما در سامانه ثبت نیست. به پشتیبانی تماس بگیرید.',
            ], 403);
        }

        $lastSentKey = "tech_otp_last_{$mobile}";
        if ($lastSent = Cache::get($lastSentKey)) {
            $wait = self::OTP_RESEND_DELAY - (time() - (int) $lastSent);
            if ($wait > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "لطفاً {$wait} ثانیه صبر کنید",
                    'wait_time' => $wait,
                ], 429);
            }
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put("tech_otp_{$mobile}", [
            'code' => $code,
            'attempts' => 0,
        ], self::OTP_TTL_SECONDS);
        Cache::put($lastSentKey, time(), self::OTP_RESEND_DELAY);

        if (app()->environment('local')) {
            Log::info("Tech OTP for {$mobile}: {$code}");
        }

        try {
            // همان تمپلیتی که OTPService برای ورود اپراتور استفاده می‌کند
            // (config sms.templates.otp). تکنسین تمپلیت جداگانه نمی‌خواهد.
            $template = config('sms.templates.otp', 'verify');
            $this->sms->sendTemplate($mobile, $template, ['token' => $code]);
        } catch (\Throwable $e) {
            Log::warning('tech OTP SMS failed', [
                'mobile' => $mobile,
                'error' => $e->getMessage(),
            ]);
            // در local خطا باعث جلوگیری از تست نشود
            if (! app()->environment('local')) {
                Cache::forget("tech_otp_{$mobile}");
                return response()->json([
                    'success' => false,
                    'message' => 'خطا در ارسال پیامک',
                ], 500);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'کد تایید ارسال شد',
            'expires_in' => self::OTP_TTL_SECONDS,
            'debug_code' => app()->environment('local') ? $code : null,
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'mobile' => ['required', 'regex:/^09[0-9]{9}$/'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $mobile = $request->input('mobile');
        $code = $request->input('code');
        $cacheKey = "tech_otp_{$mobile}";
        $data = Cache::get($cacheKey);

        if (! $data) {
            return response()->json([
                'success' => false,
                'message' => 'کد تایید منقضی شده است',
            ], 422);
        }

        if (($data['attempts'] ?? 0) >= self::OTP_MAX_ATTEMPTS) {
            Cache::forget($cacheKey);
            return response()->json([
                'success' => false,
                'message' => 'تعداد تلاش‌های مجاز تمام شد',
            ], 422);
        }

        if (($data['code'] ?? null) !== $code) {
            $data['attempts'] = ($data['attempts'] ?? 0) + 1;
            Cache::put($cacheKey, $data, self::OTP_TTL_SECONDS);
            $remaining = self::OTP_MAX_ATTEMPTS - $data['attempts'];
            return response()->json([
                'success' => false,
                'message' => "کد تایید اشتباه است ({$remaining} تلاش باقی‌مانده)",
            ], 422);
        }

        Cache::forget($cacheKey);

        return $this->loginTechnician($mobile);
    }

    public function loginWithPassword(Request $request)
    {
        $request->validate([
            'mobile' => ['required', 'regex:/^09[0-9]{9}$/'],
            'password' => ['required', 'string'],
        ], [
            'mobile.required' => 'شماره موبایل الزامی است',
            'password.required' => 'رمز عبور الزامی است',
        ]);

        $mobile = $request->input('mobile');
        $technician = Technician::where('mobile', $mobile)->first();

        if (! $technician || ! $technician->password) {
            return response()->json([
                'success' => false,
                'message' => 'مشخصات وارد شده اشتباه است',
            ], 422);
        }

        if (! Hash::check($request->input('password'), $technician->password)) {
            return response()->json([
                'success' => false,
                'message' => 'مشخصات وارد شده اشتباه است',
            ], 422);
        }

        return $this->loginTechnician($mobile);
    }

    public function logout(Request $request)
    {
        Auth::guard('tech')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('tech.login');
    }

    /** ورود تکنسین پس از موفقیت OTP/پسورد. */
    protected function loginTechnician(string $mobile)
    {
        $technician = Technician::where('mobile', $mobile)->first();
        if (! $technician) {
            return response()->json([
                'success' => false,
                'message' => 'تکنسین یافت نشد',
            ], 403);
        }

        if (strtolower((string) $technician->status) === 'inactive') {
            return response()->json([
                'success' => false,
                'message' => 'حساب کاربری شما غیرفعال شده است',
            ], 403);
        }

        Auth::guard('tech')->login($technician, true);
        $technician->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'success' => true,
            'message' => 'ورود موفق',
            'redirect' => route('tech.dashboard'),
        ]);
    }
}
