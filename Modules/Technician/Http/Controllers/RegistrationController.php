<?php

namespace Modules\Technician\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\SMS\Services\KavenegarService;
use Modules\SMS\Services\OTPService;
use Modules\Technician\Models\ApplianceCategory;
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
        $applianceCategories = ApplianceCategory::active()->ordered()->get();

        return view('technician::register', [
            'brand_name'           => $brandName,
            'brand_logo'           => $brandLogo,
            'provinces'            => $provinces,
            'appliance_categories' => $applianceCategories,
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
                    'status'            => $registration->status,
                    'contract_signed'    => (bool) $registration->contract_signed_at,
                    'documents_uploaded' => (bool) $registration->documents_uploaded,
                    'rejection_reason'   => $registration->rejection_reason,
                    'biometric_status'   => $registration->biometric_status,
                    'first_name'        => $registration->first_name,
                    'last_name'         => $registration->last_name,
                    'father_name'       => $registration->father_name,
                    'shenasname_number' => $registration->shenasname_number,
                    'gender'            => $registration->gender,
                    'marital_status'    => $registration->marital_status,
                    'children_count'    => $registration->children_count,
                    'province'          => $registration->province,
                    'city'              => $registration->city,
                    'address'           => $registration->address,
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
            'children_count'    => ['nullable', 'integer', 'min:0', 'max:5'],
            'province'          => ['required', 'string'],
            'city'              => ['required', 'string'],
            'address'           => ['required', 'string', 'max:1000'],
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
            'address.required'           => 'آدرس محل سکونت الزامی است.',
            'address.max'                => 'آدرس نباید بیشتر از ۱۰۰۰ کاراکتر باشد.',
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
            'children_count'    => $request->marital_status === 'married' ? $request->children_count : null,
            'province'          => $request->province,
            'city'              => $request->city,
            'address'           => $request->address,
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
            'education_level'      => ['required', 'in:below_diploma,diploma,associate,bachelor,master,doctorate'],
            'has_business_license' => ['required', 'in:0,1'],
            'has_shop'             => ['required', 'in:0,1'],
            'shop_address'         => ['nullable', 'required_if:has_shop,1', 'string', 'max:1000'],
            'shop_phone'           => ['nullable', 'required_if:has_shop,1', 'string', 'max:20'],
            'has_cooperation'      => ['nullable', 'in:0,1'],
            'cooperation_companies' => ['nullable', 'required_if:has_cooperation,1', 'string', 'max:1000'],
            'referrer_name'        => ['nullable', 'string', 'max:255'],
            'referrer_phone'       => ['nullable', 'string', 'max:20'],
            'colleague1_name'      => ['nullable', 'string', 'max:255'],
            'colleague1_phone'     => ['nullable', 'string', 'max:20'],
            'colleague2_name'      => ['nullable', 'string', 'max:255'],
            'colleague2_phone'     => ['nullable', 'string', 'max:20'],
            'work_experiences'     => ['required', 'array', 'min:1', 'max:10'],
            'work_experiences.*.title'    => ['required', 'string', 'max:255'],
            'work_experiences.*.company'  => ['required', 'string', 'max:255'],
            'work_experiences.*.duration' => ['required', 'string', 'max:100'],
            'certificates'         => ['nullable', 'array', 'max:10'],
            'certificates.*.title'       => ['required', 'string', 'max:255'],
            'certificates.*.institution' => ['required', 'string', 'max:255'],
        ], [
            'mobile.required'              => 'شماره موبایل الزامی است.',
            'education_level.required'     => 'انتخاب مقطع تحصیلی الزامی است.',
            'work_experiences.required'    => 'حداقل یک سابقه شغلی الزامی است.',
            'work_experiences.min'         => 'حداقل یک سابقه شغلی الزامی است.',
            'has_business_license.required' => 'وضعیت پروانه کسب الزامی است.',
            'has_shop.required'             => 'وضعیت مغازه/دفتر الزامی است.',
            'shop_address.required_if'      => 'آدرس مغازه/دفتر الزامی است.',
            'shop_phone.required_if'        => 'تلفن مغازه/دفتر الزامی است.',
            'cooperation_companies.required_if' => 'نام شرکت‌ها الزامی است.',
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
            'education_level'      => $request->education_level,
            'has_business_license' => (bool) $request->has_business_license,
            'has_shop'             => (bool) $request->has_shop,
            'shop_address'         => $request->has_shop ? $request->shop_address : null,
            'shop_phone'           => $request->has_shop ? $request->shop_phone : null,
            'has_cooperation'      => (bool) $request->has_cooperation,
            'cooperation_companies' => $request->has_cooperation ? $request->cooperation_companies : null,
            'referrer_name'        => $request->referrer_name,
            'referrer_phone'       => $request->referrer_phone,
            'colleague1_name'      => $request->colleague1_name,
            'colleague1_phone'     => $request->colleague1_phone,
            'colleague2_name'      => $request->colleague2_name,
            'colleague2_phone'     => $request->colleague2_phone,
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
     * ذخیره مرحله چهارم: مناطق تحت پوشش
     */
    public function storeStep4(Request $request)
    {
        $request->validate([
            'mobile'                 => ['required', 'regex:/^09[0-9]{9}$/'],
            'serves_tehran'          => ['nullable', 'in:0,1'],
            'tehran_districts'       => ['nullable', 'array'],
            'tehran_districts.*'     => ['integer', 'min:1', 'max:22'],
            'tehran_province_cities' => ['nullable', 'array'],
            'tehran_province_cities.*' => ['string', 'max:255'],
            'alborz_cities'          => ['nullable', 'array'],
            'alborz_cities.*'        => ['string', 'max:255'],
            'other_provinces_cities'  => ['nullable', 'string', 'max:2000'],
        ], [
            'mobile.required' => 'شماره موبایل الزامی است.',
        ]);

        // اگر خدمات تهران انتخاب شده، حداقل یک منطقه باید انتخاب شود
        if ($request->serves_tehran == '1' && empty($request->tehran_districts)) {
            return response()->json([
                'success' => false,
                'message' => 'لطفاً حداقل یک منطقه تهران را انتخاب کنید.',
            ], 422);
        }

        // پیدا کردن رکورد ثبت‌نام
        $registration = TechnicianRegistration::where('mobile', $request->mobile)
            ->where('current_step', '>=', 4)
            ->first();

        if (!$registration) {
            return response()->json([
                'success' => false,
                'message' => 'ابتدا مراحل قبلی را تکمیل کنید.',
            ], 422);
        }

        // به‌روزرسانی اطلاعات
        $registration->update([
            'tehran_districts'       => $request->serves_tehran == '1' ? ($request->tehran_districts ?? []) : [],
            'tehran_province_cities' => $request->tehran_province_cities ?? [],
            'alborz_cities'          => $request->alborz_cities ?? [],
            'other_provinces_cities'  => $request->other_provinces_cities,
            'current_step'           => 5,
        ]);

        Log::info('Technician registration step 4 completed', [
            'registration_id' => $registration->id,
            'mobile' => $request->mobile,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'مناطق تحت پوشش با موفقیت ثبت شد.',
        ]);
    }

    /**
     * ذخیره مرحله پنجم: زمینه فعالیت
     */
    public function storeStep5(Request $request)
    {
        $request->validate([
            'mobile'                => ['required', 'regex:/^09[0-9]{9}$/'],
            'activity_type'         => ['required', 'in:install,repair,install_repair'],
            'appliance_categories'  => ['required', 'array', 'min:1'],
            'appliance_categories.*' => ['integer', 'exists:appliance_categories,id'],
            'transportation_method' => ['required', 'in:motorcycle,car,none'],
            'repair_skill'          => ['required', 'in:board_repair,parts_only,both'],
            'board_repair_experience' => ['nullable', 'in:none,beginner,intermediate,advanced,expert'],
            'additional_notes'      => ['nullable', 'string', 'max:2000'],
            'agreement'             => ['required', 'in:yes'],
        ], [
            'mobile.required'               => 'شماره موبایل الزامی است.',
            'activity_type.required'        => 'انتخاب زمینه فعالیت الزامی است.',
            'activity_type.in'              => 'زمینه فعالیت انتخاب شده معتبر نیست.',
            'appliance_categories.required' => 'لطفاً حداقل یک دستگاه را انتخاب کنید.',
            'appliance_categories.min'      => 'لطفاً حداقل یک دستگاه را انتخاب کنید.',
            'transportation_method.required' => 'انتخاب نحوه ارائه خدمات الزامی است.',
            'transportation_method.in'       => 'نحوه ارائه خدمات انتخاب شده معتبر نیست.',
            'repair_skill.required'         => 'لطفاً نوع تعمیرات خود را مشخص کنید.',
            'repair_skill.in'               => 'نوع تعمیرات انتخاب شده معتبر نیست.',
            'agreement.required'            => 'موافقت با شرایط همکاری الزامی است.',
            'agreement.in'                  => 'موافقت با شرایط همکاری الزامی است.',
        ]);

        // پیدا کردن رکورد ثبت‌نام
        $registration = TechnicianRegistration::where('mobile', $request->mobile)
            ->where('current_step', '>=', 5)
            ->first();

        if (!$registration) {
            return response()->json([
                'success' => false,
                'message' => 'ابتدا مراحل قبلی را تکمیل کنید.',
            ], 422);
        }

        // به‌روزرسانی اطلاعات
        $registration->update([
            'activity_type'           => $request->activity_type,
            'appliance_categories'    => $request->appliance_categories,
            'transportation_method'   => $request->transportation_method,
            'repair_skill'            => $request->repair_skill,
            'board_repair_experience' => $request->board_repair_experience,
            'additional_notes'        => $request->additional_notes,
            'current_step'            => 6,
            'status'                => 'pending',
        ]);

        Log::info('Technician registration step 5 completed', [
            'registration_id' => $registration->id,
            'mobile' => $request->mobile,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ثبت‌نام شما با موفقیت تکمیل شد. پس از بررسی و تایید اطلاعات، از طریق پیامک به شما اطلاع‌رسانی خواهد شد.',
        ]);
    }

    /**
     * دریافت متن قرارداد
     */
    public function getContract(Request $request)
    {
        $request->validate([
            'mobile' => ['required', 'regex:/^09[0-9]{9}$/'],
        ], [
            'mobile.required' => 'شماره موبایل الزامی است.',
            'mobile.regex'    => 'شماره موبایل معتبر نیست.',
        ]);

        $registration = TechnicianRegistration::where('mobile', $request->mobile)->first();

        if (!$registration) {
            return response()->json([
                'success' => false,
                'message' => 'ثبت‌نامی با این شماره موبایل یافت نشد. لطفاً مجدداً وارد شوید.',
            ], 422);
        }

        if ($registration->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'درخواست شما هنوز تأیید نشده است.',
            ], 422);
        }

        if ($registration->contract_signed_at) {
            return response()->json([
                'success' => false,
                'message' => 'قرارداد قبلاً امضا شده است.',
            ], 422);
        }

        try {
            $contractText = TechnicianSetting::get('contract_text', '');

            // درصد کارمزد: ابتدا مقدار اختصاصی تکنسین، سپس تنظیمات عمومی
            $commissionPercent = null;
            try {
                $commissionPercent = $registration->commission_percent;
            } catch (\Exception $e) {}
            $commissionPercent = $commissionPercent ?? TechnicianSetting::get('default_commission_percent', '');

            // مبلغ سفته: ابتدا مقدار اختصاصی تکنسین، سپس تنظیمات عمومی
            $promissoryNoteAmount = null;
            try {
                $promissoryNoteAmount = $registration->promissory_note_amount;
            } catch (\Exception $e) {}
            $promissoryNoteAmount = $promissoryNoteAmount ?? TechnicianSetting::get('default_promissory_note_amount', '');

            // عنوان جنسیت
            $genderTitle = $registration->gender === 'female' ? 'خانم' : 'آقای';

            // آدرس کامل: استان، شهر، آدرس محل سکونت
            $province = $registration->province ?? '';
            $city = $registration->city ?? '';
            $fullAddress = $registration->address ?? '';
            $addressParts = array_filter([$province, $city, $fullAddress]);
            $address = implode('، ', $addressParts);

            // تاریخ شمسی
            try {
                $jalaliDate = \Morilog\Jalali\Jalalian::now()->format('Y/m/d');
            } catch (\Exception $e) {
                $jalaliDate = now()->format('Y/m/d');
            }

            // جایگزینی متغیرها
            $contractText = str_replace(
                [
                    '{gender_title}',
                    '{name}',
                    '{father_name}',
                    '{national_code}',
                    '{address}',
                    '{mobile}',
                    '{date}',
                    '{commission_percent}',
                    '{promissory_note_amount}',
                ],
                [
                    $genderTitle,
                    $registration->first_name . ' ' . $registration->last_name,
                    $registration->father_name ?? '',
                    $registration->national_code,
                    $address,
                    $registration->mobile,
                    $jalaliDate,
                    $commissionPercent,
                    $promissoryNoteAmount ? number_format((float) $promissoryNoteAmount) : '',
                ],
                $contractText
            );

            return response()->json([
                'success' => true,
                'contract' => $contractText,
            ]);
        } catch (\Exception $e) {
            Log::error('Contract loading failed', [
                'registration_id' => $registration->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'خطا در بارگذاری قرارداد: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ارسال کد تایید قرارداد
     */
    public function sendContractOtp(Request $request)
    {
        $request->validate([
            'mobile' => ['required', 'regex:/^09[0-9]{9}$/'],
        ]);

        $registration = TechnicianRegistration::where('mobile', $request->mobile)
            ->where('status', 'approved')
            ->whereNull('contract_signed_at')
            ->first();

        if (!$registration) {
            return response()->json([
                'success' => false,
                'message' => 'درخواست معتبر نیست.',
            ], 422);
        }

        $otpService = app(OTPService::class);
        $result = $otpService->send($request->mobile);

        return response()->json($result);
    }

    /**
     * امضای قرارداد (با تایید کد)
     */
    public function signContract(Request $request)
    {
        $request->validate([
            'mobile'    => ['required', 'regex:/^09[0-9]{9}$/'],
            'signature' => ['required', 'string'],
            'code'      => ['required', 'string', 'size:6'],
        ], [
            'mobile.required'    => 'شماره موبایل الزامی است.',
            'signature.required' => 'امضای شما الزامی است.',
            'code.required'      => 'کد تایید الزامی است.',
            'code.size'          => 'کد تایید باید ۶ رقم باشد.',
        ]);

        // تایید کد OTP
        $otpService = app(OTPService::class);
        $otpResult = $otpService->verify($request->mobile, $request->code);

        if (!($otpResult['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $otpResult['message'] ?? 'کد تایید نامعتبر است.',
            ], 422);
        }

        $registration = TechnicianRegistration::where('mobile', $request->mobile)
            ->where('status', 'approved')
            ->whereNull('contract_signed_at')
            ->first();

        if (!$registration) {
            return response()->json([
                'success' => false,
                'message' => 'درخواست معتبر نیست.',
            ], 422);
        }

        $registration->update([
            'contract_signed_at' => now(),
            'contract_signature' => $request->signature,
            'current_step'       => 7,
        ]);

        Log::info('Technician contract signed', [
            'registration_id' => $registration->id,
            'mobile' => $request->mobile,
        ]);

        // ارسال پیامک تایید قرارداد
        $smsTemplate = TechnicianSetting::get('contract_sms_template', '');
        if ($smsTemplate) {
            try {
                $kavenegarService = app(KavenegarService::class);
                $kavenegarService->sendTemplate(
                    $registration->mobile,
                    $smsTemplate,
                    ['token' => $registration->first_name . ' ' . $registration->last_name]
                );
            } catch (\Exception $e) {
                Log::warning('Failed to send contract SMS', [
                    'registration_id' => $registration->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'قرارداد با موفقیت امضا شد.',
        ]);
    }

    /**
     * آپلود تکی هر مدرک — هنگام انتخاب فایل بلافاصله ارسال و ذخیره می‌شود
     */
    public function uploadSingleDocument(Request $request)
    {
        $allowedFields = [
            'national_card_front'  => 'doc_national_card_front',
            'national_card_back'   => 'doc_national_card_back',
            'birth_certificate_p1' => 'doc_birth_certificate_p1',
            'birth_certificate_p2' => 'doc_birth_certificate_p2',
            'criminal_record'      => 'doc_criminal_record',
            'photo_3x4'           => 'doc_photo_3x4',
            'lease_agreement'      => 'doc_lease_agreement',
            'utility_bill'         => 'doc_utility_bill',
        ];

        $request->validate([
            'mobile'     => ['required', 'regex:/^09[0-9]{9}$/'],
            'field_name' => ['required', 'string', 'in:' . implode(',', array_keys($allowedFields))],
            'file'       => ['required', 'image', 'max:5120'],
        ], [
            'mobile.required'     => 'شماره موبایل الزامی است.',
            'field_name.required' => 'نام فیلد الزامی است.',
            'field_name.in'       => 'نام فیلد نامعتبر است.',
            'file.required'       => 'فایل الزامی است.',
            'file.image'          => 'فایل باید تصویر باشد.',
            'file.max'            => 'حجم تصویر نباید بیشتر از ۵ مگابایت باشد.',
        ]);

        $registration = TechnicianRegistration::where('mobile', $request->mobile)
            ->where('status', 'approved')
            ->whereNotNull('contract_signed_at')
            ->first();

        if (!$registration) {
            return response()->json([
                'success' => false,
                'message' => 'درخواست معتبر نیست.',
            ], 422);
        }

        $fieldName = $request->field_name;
        $dbColumn = $allowedFields[$fieldName];
        $folder = 'technician-documents/' . $registration->id;

        // اطمینان از وجود دایرکتوری ذخیره‌سازی
        $storagePath = storage_path('app/public/' . $folder);
        if (!is_dir($storagePath)) {
            if (!mkdir($storagePath, 0755, true) && !is_dir($storagePath)) {
                Log::error('uploadSingleDocument: cannot create directory', [
                    'path' => $storagePath,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'خطا در ایجاد پوشه ذخیره‌سازی.',
                ], 500);
            }
        }

        // بررسی قابل نوشتن بودن دایرکتوری
        if (!is_writable($storagePath)) {
            Log::error('uploadSingleDocument: directory not writable', [
                'path' => $storagePath,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'پوشه ذخیره‌سازی قابل نوشتن نیست.',
            ], 500);
        }

        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت فایل: ' . ($file ? $file->getErrorMessage() : 'فایل دریافت نشد'),
            ], 422);
        }

        try {
            // حذف فایل قبلی در صورت وجود
            $oldPath = $registration->{$dbColumn};
            if ($oldPath) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($oldPath);
            }

            // ذخیره با move دستی به جای store — مطمئن‌تر
            $fileName = $fieldName . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move($storagePath, $fileName);
            $path = $folder . '/' . $fileName;

            $registration->update([$dbColumn => $path]);

            Log::info('Technician single document uploaded', [
                'registration_id' => $registration->id,
                'field' => $fieldName,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'فایل با موفقیت آپلود شد.',
            ]);
        } catch (\Exception $e) {
            Log::error('uploadSingleDocument: failed', [
                'registration_id' => $registration->id,
                'field' => $fieldName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'خطا در ذخیره فایل: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * بررسی اینکه همه مدارک آپلود شده‌اند — دکمه «مرحله بعدی» فقط این را فراخوانی می‌کند
     */
    public function uploadDocuments(Request $request)
    {
        $request->validate([
            'mobile' => ['required', 'regex:/^09[0-9]{9}$/'],
        ], [
            'mobile.required' => 'شماره موبایل الزامی است.',
        ]);

        $registration = TechnicianRegistration::where('mobile', $request->mobile)
            ->where('status', 'approved')
            ->whereNotNull('contract_signed_at')
            ->first();

        if (!$registration) {
            return response()->json([
                'success' => false,
                'message' => 'درخواست معتبر نیست.',
            ], 422);
        }

        // بررسی اینکه همه مدارک آپلود شده‌اند
        $requiredDocs = [
            'doc_national_card_front'  => 'تصویر روی کارت ملی',
            'doc_national_card_back'   => 'تصویر پشت کارت ملی',
            'doc_birth_certificate_p1' => 'صفحه اول شناسنامه',
            'doc_birth_certificate_p2' => 'صفحه دوم شناسنامه',
            'doc_criminal_record'      => 'گواهی عدم سوء‌پیشینه',
            'doc_photo_3x4'           => 'عکس ۳×۴',
            'doc_lease_agreement'      => 'اجاره‌نامه',
            'doc_utility_bill'         => 'قبض آب یا برق',
        ];

        $missing = [];
        foreach ($requiredDocs as $column => $label) {
            if (empty($registration->{$column})) {
                $missing[] = $label;
            }
        }

        if (!empty($missing)) {
            return response()->json([
                'success' => false,
                'message' => 'لطفاً ابتدا همه مدارک را آپلود کنید.',
                'missing' => $missing,
            ], 422);
        }

        $registration->update([
            'documents_uploaded' => true,
            'current_step'      => 8,
        ]);

        Log::info('Technician documents verified complete', [
            'registration_id' => $registration->id,
            'mobile' => $request->mobile,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'مدارک با موفقیت تایید شد.',
        ]);
    }

    /**
     * آپلود ویدیو سلفی (ذخیره محلی — بررسی توسط اپراتور)
     */
    public function submitBiometric(Request $request)
    {
        $request->validate([
            'mobile'              => ['required', 'regex:/^09[0-9]{9}$/'],
            'national_card_serial' => ['required', 'string', 'regex:/^[0-9A-Za-z]{10}$/'],
            'video'               => ['required', 'file', 'mimetypes:video/webm,video/mp4', 'max:10240'],
        ], [
            'mobile.required'              => 'شماره موبایل الزامی است.',
            'national_card_serial.required' => 'سریال کارت ملی الزامی است.',
            'national_card_serial.regex'    => 'فرمت سریال کارت ملی معتبر نیست.',
            'video.required'               => 'ویدیو سلفی الزامی است.',
            'video.uploaded'               => 'آپلود ویدیو ناموفق بود. لطفاً مجدداً ضبط و ارسال کنید.',
            'video.mimetypes'              => 'فرمت ویدیو معتبر نیست.',
            'video.max'                    => 'حجم ویدیو نباید بیشتر از ۱۰ مگابایت باشد.',
        ]);

        $registration = TechnicianRegistration::where('mobile', $request->mobile)
            ->where('identity_verified', true)
            ->first();

        if (!$registration) {
            return response()->json([
                'success' => false,
                'message' => 'ابتدا مراحل احراز هویت اولیه را تکمیل کنید.',
            ], 422);
        }

        // اگر قبلاً تایید شده، اجازه ارسال مجدد نده
        if ($registration->biometric_status === 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'احراز هویت ویدیویی شما قبلاً تایید شده است.',
            ], 422);
        }

        // ذخیره ویدیو به صورت محلی
        $folder = 'technician-biometric/' . $registration->id;
        $videoPath = $request->file('video')->store($folder, 'public');

        $registration->update([
            'national_card_serial'    => $request->national_card_serial,
            'biometric_video_path'    => $videoPath,
            'biometric_status'        => 'pending',
            'biometric_reject_reason' => null,
            'current_step'            => 9,
        ]);

        Log::info('Biometric video uploaded locally', [
            'registration_id' => $registration->id,
            'video_path'      => $videoPath,
        ]);

        // ارسال پیامک اطلاع‌رسانی ارسال ویدیو
        $smsTemplate = TechnicianSetting::get('sms_biometric_submitted_template', '');
        if ($smsTemplate) {
            try {
                $kavenegarService = app(KavenegarService::class);
                $kavenegarService->sendTemplate(
                    $registration->mobile,
                    $smsTemplate,
                    ['token' => $registration->first_name . ' ' . $registration->last_name]
                );
            } catch (\Exception $e) {
                Log::warning('Failed to send biometric submitted SMS', [
                    'registration_id' => $registration->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'ویدیو با موفقیت ارسال شد.',
        ]);
    }

    /**
     * بررسی وضعیت احراز هویت بایومتریک
     */
    public function checkBiometricStatus(Request $request)
    {
        $request->validate([
            'mobile' => ['required', 'regex:/^09[0-9]{9}$/'],
        ]);

        $registration = TechnicianRegistration::where('mobile', $request->mobile)
            ->whereNotNull('biometric_video_path')
            ->first();

        if (!$registration) {
            return response()->json([
                'success' => false,
                'message' => 'ویدیو احراز هویت یافت نشد.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'status'  => $registration->biometric_status,
            'reason'  => $registration->biometric_reject_reason,
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
