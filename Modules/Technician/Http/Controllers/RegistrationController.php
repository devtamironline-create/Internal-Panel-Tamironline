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

        $provinces = require base_path('Modules/Technician/Data/iran_provinces.php');

        return view('technician::register', [
            'brand_name' => $brandName,
            'brand_logo' => $brandLogo,
            'provinces'  => $provinces,
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

        // فقط ثبت‌نام‌های تایید شده نهایی بلاک شوند
        $existing = TechnicianRegistration::where('mobile', $request->mobile)->first();
        if ($existing && $existing->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'ثبت‌نام شما قبلاً تکمیل و تایید شده است.',
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

        // اگر OTP تایید شد، بررسی ثبت‌نام قبلی برای ادامه از مرحله مناسب
        if ($result['success'] ?? false) {
            $registration = TechnicianRegistration::where('mobile', $request->mobile)->first();
            if ($registration && $registration->identity_verified) {
                $result['resume'] = [
                    'current_step'      => $registration->current_step,
                    'first_name'        => $registration->first_name,
                    'last_name'         => $registration->last_name,
                    'father_name'       => $registration->father_name,
                    'shenasname_number' => $registration->shenasname_number,
                    'gender'            => $registration->gender,
                    'marital_status'    => $registration->marital_status,
                    'province'          => $registration->province,
                    'city'              => $registration->city,
                ];
            }
        }

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
            'success'     => true,
            'message'     => 'اطلاعات هویتی با موفقیت تایید شد.',
            'first_name'  => $identity['first_name'],
            'last_name'   => $identity['last_name'],
            'father_name' => $identity['father_name'],
        ]);
    }

    /**
     * ذخیره مرحله دوم: اطلاعات شخصی
     */
    public function storeStep2(Request $request)
    {
        $provinces = require base_path('Modules/Technician/Data/iran_provinces.php');
        $provinceNames = array_keys($provinces);

        $request->validate([
            'mobile'            => ['required', 'regex:/^09[0-9]{9}$/'],
            'shenasname_number' => ['required', 'regex:/^[0-9]{1,10}$/'],
            'gender'            => ['required', 'in:male,female'],
            'marital_status'    => ['required', 'in:single,married'],
            'province'          => ['required', 'string'],
            'city'              => ['required', 'string'],
        ], [
            'mobile.required'            => 'شماره موبایل الزامی است.',
            'shenasname_number.required' => 'شماره شناسنامه الزامی است.',
            'shenasname_number.regex'    => 'شماره شناسنامه باید عددی باشد.',
            'gender.required'            => 'انتخاب جنسیت الزامی است.',
            'gender.in'                  => 'جنسیت انتخاب شده معتبر نیست.',
            'marital_status.required'    => 'انتخاب وضعیت تاهل الزامی است.',
            'marital_status.in'          => 'وضعیت تاهل انتخاب شده معتبر نیست.',
            'province.required'          => 'انتخاب استان الزامی است.',
            'city.required'              => 'انتخاب شهر الزامی است.',
        ]);

        // اعتبارسنجی استان
        if (!in_array($request->province, $provinceNames)) {
            return response()->json([
                'success' => false,
                'message' => 'استان انتخاب شده معتبر نیست.',
                'field'   => 'province',
            ], 422);
        }

        // اعتبارسنجی شهر
        $cities = $provinces[$request->province] ?? [];
        if (!in_array($request->city, $cities)) {
            return response()->json([
                'success' => false,
                'message' => 'شهر انتخاب شده معتبر نیست.',
                'field'   => 'city',
            ], 422);
        }

        // پیدا کردن رکورد ثبت‌نام
        $registration = TechnicianRegistration::where('mobile', $request->mobile)
            ->where('identity_verified', true)
            ->first();

        if (!$registration) {
            return response()->json([
                'success' => false,
                'message' => 'ابتدا مرحله احراز هویت را تکمیل کنید.',
            ], 422);
        }

        // به‌روزرسانی اطلاعات
        $registration->update([
            'shenasname_number' => $request->shenasname_number,
            'gender'            => $request->gender,
            'marital_status'    => $request->marital_status,
            'province'          => $request->province,
            'city'              => $request->city,
            'current_step'      => 3,
        ]);

        Log::info('Technician registration step 2 completed', [
            'registration_id' => $registration->id,
            'mobile' => $request->mobile,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'اطلاعات شخصی با موفقیت ثبت شد.',
        ]);
    }

    /**
     * ذخیره مرحله سوم: اطلاعات تکمیلی
     */
    public function storeStep3(Request $request)
    {
        $request->validate([
            'mobile'               => ['required', 'regex:/^09[0-9]{9}$/'],
            'field_of_study'       => ['nullable', 'string', 'max:255'],
            'has_business_license' => ['required', 'in:0,1'],
            'has_shop'             => ['required', 'in:0,1'],
            'shop_address'         => ['nullable', 'required_if:has_shop,1', 'string', 'max:1000'],
            'shop_phone'           => ['nullable', 'required_if:has_shop,1', 'string', 'max:20'],
            'work_experiences'     => ['nullable', 'array', 'max:10'],
            'work_experiences.*.title'    => ['required', 'string', 'max:255'],
            'work_experiences.*.company'  => ['required', 'string', 'max:255'],
            'work_experiences.*.duration' => ['required', 'string', 'max:100'],
            'certificates'         => ['nullable', 'array', 'max:10'],
            'certificates.*.title'       => ['required', 'string', 'max:255'],
            'certificates.*.institution' => ['required', 'string', 'max:255'],
        ], [
            'mobile.required'              => 'شماره موبایل الزامی است.',
            'has_business_license.required' => 'وضعیت پروانه کسب الزامی است.',
            'has_shop.required'             => 'وضعیت مغازه/دفتر الزامی است.',
            'shop_address.required_if'      => 'آدرس مغازه/دفتر الزامی است.',
            'shop_phone.required_if'        => 'تلفن مغازه/دفتر الزامی است.',
            'work_experiences.*.title.required'    => 'عنوان شغل الزامی است.',
            'work_experiences.*.company.required'  => 'محل کار الزامی است.',
            'work_experiences.*.duration.required' => 'مدت فعالیت الزامی است.',
            'certificates.*.title.required'        => 'عنوان مدرک/دوره الزامی است.',
            'certificates.*.institution.required'  => 'نام موسسه/مرکز الزامی است.',
        ]);

        // پیدا کردن رکورد ثبت‌نام
        $registration = TechnicianRegistration::where('mobile', $request->mobile)
            ->where('current_step', '>=', 3)
            ->first();

        if (!$registration) {
            return response()->json([
                'success' => false,
                'message' => 'ابتدا مراحل قبلی را تکمیل کنید.',
            ], 422);
        }

        // به‌روزرسانی اطلاعات
        $registration->update([
            'field_of_study'       => $request->field_of_study,
            'has_business_license' => (bool) $request->has_business_license,
            'has_shop'             => (bool) $request->has_shop,
            'shop_address'         => $request->has_shop ? $request->shop_address : null,
            'shop_phone'           => $request->has_shop ? $request->shop_phone : null,
            'work_experiences'     => $request->work_experiences ?? [],
            'certificates'         => $request->certificates ?? [],
            'current_step'         => 4,
        ]);

        Log::info('Technician registration step 3 completed', [
            'registration_id' => $registration->id,
            'mobile' => $request->mobile,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'اطلاعات تکمیلی با موفقیت ثبت شد.',
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
