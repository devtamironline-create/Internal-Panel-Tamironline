<?php

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\SMS\Services\OTPService;

class AuthController extends Controller
{
    protected OTPService $otpService;

    public function __construct(OTPService $otpService)
    {
        $this->otpService = $otpService;
    }

    public function showLoginForm()
    {
        return view('core::auth.login', ['isAdmin' => false]);
    }

    public function showAdminLoginForm()
    {
        return view('core::auth.login', ['isAdmin' => true]);
    }

    public function sendOTP(Request $request)
    {
        $request->validate([
            'mobile' => ['required', 'regex:/^09[0-9]{9}$/'],
        ], [
            'mobile.required' => 'شماره موبایل الزامی است',
            'mobile.regex' => 'فرمت شماره موبایل صحیح نیست',
        ]);

        $result = $this->otpService->send($request->input('mobile'));

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'expires_in' => $result['expires_in'] ?? 120,
                'debug_code' => $result['debug_code'] ?? null
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'],
            'wait_time' => $result['wait_time'] ?? 0
        ], 422);
    }

    public function verifyOTP(Request $request)
    {
        $request->validate([
            'mobile' => ['required', 'regex:/^09[0-9]{9}$/'],
            'code' => ['required', 'string', 'size:6'],
            'is_admin' => ['sometimes', 'boolean'],
        ]);

        $mobile = $request->input('mobile');
        $code = $request->input('code');
        $isAdmin = $request->boolean('is_admin');

        $result = $this->otpService->verify($mobile, $code);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 422);
        }

        $user = User::where('mobile', $mobile)->first();

        // Admin login - must be existing staff
        if ($isAdmin) {
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'کاربری با این شماره موبایل یافت نشد'
                ], 403);
            }

            if (!$user->isStaff()) {
                return response()->json([
                    'success' => false,
                    'message' => 'شما دسترسی به پنل مدیریت ندارید'
                ], 403);
            }
        } else {
            // Customer login - create user if not exists
            if (!$user) {
                $user = User::create([
                    'mobile' => $mobile,
                    'mobile_verified_at' => now(),
                    'is_staff' => false,
                    'is_active' => true,
                ]);
            }
        }

        if (!$user->isMobileVerified()) {
            $user->update(['mobile_verified_at' => now()]);
        }

        if (!$user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'حساب کاربری شما غیرفعال شده است'
            ], 403);
        }

        $user->recordLogin($request->ip());
        Auth::login($user, true);

        $redirectUrl = $user->isStaff()
            ? route('admin.dashboard')
            : route('panel.dashboard');

        // If customer and profile not complete, redirect to profile edit
        if (!$user->isStaff() && (!$user->first_name || !$user->last_name)) {
            $redirectUrl = route('panel.profile.edit');
        }

        return response()->json([
            'success' => true,
            'message' => 'ورود موفق',
            'redirect' => $redirectUrl
        ]);
    }

    /**
     * Login with username/mobile and password
     */
    public function loginWithPassword(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string|min:6',
            'is_admin' => ['sometimes', 'boolean'],
        ], [
            'username.required' => 'نام کاربری یا موبایل الزامی است',
            'password.required' => 'رمز عبور الزامی است',
            'password.min' => 'رمز عبور باید حداقل ۶ کاراکتر باشد',
        ]);

        $username = $request->input('username');
        $password = $request->input('password');
        $isAdmin = $request->boolean('is_admin');

        // Find user by mobile or email
        $user = User::where('mobile', $username)
            ->orWhere('email', $username)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'کاربری با این مشخصات یافت نشد'
            ], 422);
        }

        // Check password
        if (!$user->password || !\Hash::check($password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'رمز عبور اشتباه است'
            ], 422);
        }

        // Admin login checks
        if ($isAdmin) {
            if (!$user->isStaff()) {
                return response()->json([
                    'success' => false,
                    'message' => 'شما دسترسی به پنل مدیریت ندارید'
                ], 403);
            }
        }

        if (!$user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'حساب کاربری شما غیرفعال شده است'
            ], 403);
        }

        $user->recordLogin($request->ip());
        Auth::login($user, true);

        $redirectUrl = $user->isStaff()
            ? route('admin.dashboard')
            : route('panel.dashboard');

        return response()->json([
            'success' => true,
            'message' => 'ورود موفق',
            'redirect' => $redirectUrl
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
