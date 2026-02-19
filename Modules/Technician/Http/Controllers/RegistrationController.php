<?php

namespace Modules\Technician\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Technician\Models\TechnicianRegistration;
use Modules\Technician\Models\TechnicianSetting;

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
     * ذخیره مرحله اول: موبایل، کدملی، تاریخ تولد
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
            return back()->withErrors(['national_code' => 'کد ملی وارد شده معتبر نیست.'])->withInput();
        }

        // بررسی تکراری نبودن
        $existing = TechnicianRegistration::where('mobile', $request->mobile)
            ->orWhere('national_code', $request->national_code)
            ->first();

        if ($existing) {
            if ($existing->mobile === $request->mobile) {
                return back()->withErrors(['mobile' => 'این شماره موبایل قبلاً ثبت شده است.'])->withInput();
            }
            return back()->withErrors(['national_code' => 'این کد ملی قبلاً ثبت شده است.'])->withInput();
        }

        $registration = TechnicianRegistration::create([
            'mobile'        => $request->mobile,
            'national_code' => $request->national_code,
            'birth_date'    => $request->birth_date,
            'current_step'  => 2,
            'status'        => 'incomplete',
        ]);

        // فعلاً به صفحه موفقیت ریدایرکت می‌کنیم (مراحل بعدی اضافه خواهد شد)
        return redirect()->route('technician.register')
            ->with('success', 'مرحله اول با موفقیت ثبت شد.')
            ->with('registration_id', $registration->id);
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
