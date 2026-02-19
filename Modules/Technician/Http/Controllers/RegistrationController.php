<?php

namespace Modules\Technician\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\SMS\Services\OTPService;
use Modules\Technician\Models\TechnicianRegistration;
use Modules\Technician\Models\TechnicianSetting;
use Modules\Technician\Services\ZohalService;

class RegistrationController extends Controller
{
    /**
     * نمایش فرم ثبت‌نام
     */
    public function showForm()
    {
        $defaults = TechnicianSetting::defaults();

        try {
            $brandName = TechnicianSetting::get('brand_name', $defaults['brand_name']);
            $brandLogo = TechnicianSetting::get('brand_logo', null);
        } catch (\Exception $e) {
            $brandName = $defaults['brand_name'];
            $brandLogo = null;
        }

        return view('technician::register', [
            'brand_name' => $brandName,
            'brand_logo' => $brandLogo,
        ]);
    }

    /**
     * ارسال کد OTP به شماره موبایل
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'mobile' => ['required', 'regex:/^09[0-9]{9}$/'],
        ], [
            'mobile.required' => 'شماره موبایل الزامی است.',
            'mobile.regex'    => 'شماره موبایل باید با 09 شروع شده و ۱۱ رقم باشد.',
        ]);

        // بررسی تکراری نبودن موبایل
        $existing = TechnicianRegistration::where('mobile', $request->mobile)->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'این شماره موبایل قبلاً ثبت شده است.',
            ], 422);
        }

        $otpService = app(OTPService::class);
        $result = $otpService->send($request->mobile);

        return response()->json($result);
    }

    /**
     * تایید کد OTP
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'mobile' => ['required', 'regex:/^09[0-9]{9}$/'],
            'code'   => ['required', 'string', 'size:6'],
        ], [
            'mobile.required' => 'شماره موبایل الزامی است.',
            'code.required'   => 'کد تایید الزامی است.',
            'code.size'       => 'کد تایید باید ۶ رقم باشد.',
        ]);

        $otpService = app(OTPService::class);
        $result = $otpService->verify($request->mobile, $request->code);

        return response()->json($result);
    }

    /**
     * ذخیره مرحله اول: تایید هویت + ثبت اطلاعات
     */
    public function storeStep1(Request $request)
    {
        $request->validate([
            'mobile'        => ['required', 'regex:/^09[0-9]{9}$/'],
            'national_code' => ['required', 'regex:/^[0-9]{10}$/'],
            'birth_date'    => ['required', 'regex:/^[0-9]{4}\/(0[1-9]|1[0-2])\/(0[1-9]|[12][0-9]|3[01])$/'],
        ], [
            'mobile.required'        => 'شماره موبایل الزامی است.',
            'mobile.regex'           => 'شماره موبایل باید با 09 شروع شده و ۱۱ رقم باشد.',
            'national_code.required' => 'کد ملی الزامی است.',
            'national_code.regex'    => 'کد ملی باید ۱۰ رقم باشد.',
            'birth_date.required'    => 'تاریخ تولد الزامی است.',
            'birth_date.regex'       => 'فرمت تاریخ تولد معتبر نیست.',
        ]);

        // اعتبارسنجی کد ملی
        if (!$this->validateNationalCode($request->national_code)) {
            return response()->json([
                'success' => false,
                'message' => 'کد ملی وارد شده معتبر نیست.',
                'field'   => 'national_code',
            ], 422);
        }

        // بررسی تکراری نبودن
        $existing = TechnicianRegistration::where('mobile', $request->mobile)
            ->orWhere('national_code', $request->national_code)
            ->first();

        if ($existing) {
            $field = $existing->mobile === $request->mobile ? 'mobile' : 'national_code';
            $msg = $field === 'mobile'
                ? 'این شماره موبایل قبلاً ثبت شده است.'
                : 'این کد ملی قبلاً ثبت شده است.';

            return response()->json([
                'success' => false,
                'message' => $msg,
                'field'   => $field,
            ], 422);
        }

        $zohal = new ZohalService();

        // ۱) شاهکار: تطابق موبایل و کد ملی
        $shahkar = $zohal->shahkar($request->mobile, $request->national_code);

        if (!$shahkar['success']) {
            return response()->json([
                'success' => false,
                'message' => $shahkar['message'],
            ], 503);
        }

        if (!$shahkar['matched']) {
            return response()->json([
                'success' => false,
                'message' => 'شماره موبایل و کد ملی با هم مطابقت ندارند.',
                'field'   => 'national_code',
            ], 422);
        }

        // ۲) استعلام هویت: دریافت نام و نام خانوادگی
        $identity = $zohal->nationalIdentityInquiry($request->national_code, $request->birth_date);

        if (!$identity['success']) {
            return response()->json([
                'success' => false,
                'message' => $identity['message'],
            ], 503);
        }

        if (!$identity['matched']) {
            return response()->json([
                'success' => false,
                'message' => 'کد ملی و تاریخ تولد با هم مطابقت ندارند.',
                'field'   => 'birth_date',
            ], 422);
        }

        // ذخیره اطلاعات
        $registration = TechnicianRegistration::create([
            'mobile'              => $request->mobile,
            'national_code'       => $request->national_code,
            'birth_date'          => $request->birth_date,
            'first_name'          => $identity['first_name'],
            'last_name'           => $identity['last_name'],
            'father_name'         => $identity['father_name'],
            'mobile_verified_at'  => now(),
            'identity_verified'   => true,
            'current_step'        => 2,
            'status'              => 'incomplete',
        ]);

        Log::info('Technician registration step 1 completed', [
            'registration_id' => $registration->id,
            'mobile' => $request->mobile,
        ]);

        return response()->json([
            'success'    => true,
            'message'    => 'اطلاعات هویتی با موفقیت تایید شد.',
            'first_name' => $identity['first_name'],
            'last_name'  => $identity['last_name'],
        ]);
    }

    /**
     * اعتبارسنجی کد ملی ایران
     */
    private function validateNationalCode(string $code): bool
    {
        if (strlen($code) !== 10) return false;
        if (preg_match('/^(\d)\1{9}$/', $code)) return false;

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += intval($code[$i]) * (10 - $i);
        }

        $remainder = $sum % 11;
        $checkDigit = intval($code[9]);

        return ($remainder < 2 && $checkDigit === $remainder) ||
               ($remainder >= 2 && $checkDigit === (11 - $remainder));
    }
}
