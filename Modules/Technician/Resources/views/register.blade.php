<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ثبت‌نام تکنسین | {{ $brand_name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="/css/fonts.css" rel="stylesheet">
    <script src="/vendor/js/jquery.min.js"></script>
    <link rel="stylesheet" href="/vendor/css/persian-datepicker.min.css">
    <script src="/vendor/js/persian-date.min.js"></script>
    <script src="/vendor/js/persian-datepicker.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            blue: '#1a5276',
                            'blue-light': '#2471a3',
                            yellow: '#f0b929',
                            'yellow-light': '#f9e79f',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        * { font-family: 'Vazirmatn', Tahoma, sans-serif; }
        body { background: #f0f2f5; min-height: 100vh; }

        .form-input {
            border: 1.5px solid #e0e0e0;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-input:focus {
            border-color: #1a5276;
            box-shadow: 0 0 0 3px rgba(26, 82, 118, 0.08);
        }
        .form-input.has-error { border-color: #e74c3c; }
        .form-input:disabled { background: #f9fafb; color: #6b7280; }
        select.form-input { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%239ca3af' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E"); background-position: left 12px center; background-repeat: no-repeat; background-size: 20px; padding-left: 36px; }
        select.form-input:disabled { background-color: #f9fafb; color: #9ca3af; cursor: not-allowed; }

        .otp-input {
            width: 44px; height: 48px;
            text-align: center; font-size: 20px; font-weight: 700;
            border: 1.5px solid #e0e0e0; border-radius: 12px;
            outline: none; transition: border-color 0.2s;
        }
        .otp-input:focus { border-color: #1a5276; box-shadow: 0 0 0 3px rgba(26,82,118,0.08); }

        .phase { display: none; }
        .phase.active { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

        .btn-loading { pointer-events: none; opacity: 0.7; }
        .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* دیت‌پیکر - فیکس تداخل Tailwind preflight */
        .datepicker-container {
            z-index: 9999 !important;
            border-radius: 12px !important;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12) !important;
            border: 1px solid #e0e0e0 !important;
            background: #fff !important;
            overflow: visible !important;
        }
        .datepicker-container * {
            border-color: #e0e0e0;
        }
        .datepicker-plot-area {
            font-family: 'Vazirmatn', Tahoma, sans-serif !important;
            background: #fff !important;
            border-radius: 12px !important;
        }
        .datepicker-plot-area .datepicker-header {
            background: #1a5276 !important;
            padding: 8px 10px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
        }
        .datepicker-plot-area .datepicker-header .btn {
            color: #fff !important;
            background: transparent !important;
            cursor: pointer !important;
            font-size: 16px !important;
            padding: 4px 8px !important;
        }
        .datepicker-container table {
            width: 100% !important;
            border-collapse: separate !important;
            border-spacing: 0 !important;
        }
        .datepicker-container td,
        .datepicker-container th {
            padding: 5px !important;
            text-align: center !important;
            border: none !important;
        }
        .datepicker-container td span {
            display: inline-block !important;
            width: 32px !important;
            height: 32px !important;
            line-height: 32px !important;
            border-radius: 50% !important;
            cursor: pointer !important;
        }
        .datepicker-container td span:hover {
            background: #f0f0f0 !important;
        }
        .datepicker-plot-area .table-days td.selected span { background: #1a5276 !important; color: #fff !important; }
        .datepicker-plot-area .table-days td.today span { color: #f0b929 !important; font-weight: 700; }
        .datepicker-container .toolbox { background: #f9f9f9 !important; padding: 4px !important; border-top: 1px solid #e0e0e0 !important; }
        .datepicker-container .toolbox .btn-today { color: #1a5276 !important; cursor: pointer !important; }
    </style>
</head>
<body dir="rtl">

    {{-- شل وب‌اپ --}}
    <div class="min-h-screen flex flex-col mx-auto w-full max-w-[480px] bg-white shadow-[0_0_30px_rgba(0,0,0,0.06)]">

        {{-- هدر --}}
        <div class="px-6 pt-8 pb-4 text-center">
            @if($brand_logo)
                <img src="{{ asset('storage/' . $brand_logo) }}" alt="{{ $brand_name }}" class="h-12 mx-auto mb-3">
            @else
                <h1 class="text-lg font-black text-gray-800">{{ $brand_name }}</h1>
            @endif
            <p class="text-sm text-gray-400 mt-1">ثبت‌نام تکنسین</p>
        </div>

        {{-- نوار مراحل --}}
        <div class="px-6 py-4 bg-gray-50/50">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-1.5" id="stepIndicator1">
                    <div class="step-circle w-7 h-7 rounded-full bg-brand-blue text-white flex items-center justify-center text-xs font-bold">۱</div>
                    <span class="step-label text-[11px] font-bold text-brand-blue">احراز هویت</span>
                </div>
                <div class="flex-1 mx-2 h-px bg-gray-200"></div>
                <div class="flex items-center gap-1.5" id="stepIndicator2">
                    <div class="step-circle w-7 h-7 rounded-full bg-gray-200 text-gray-400 flex items-center justify-center text-xs font-bold">۲</div>
                    <span class="step-label text-[11px] font-medium text-gray-400">اطلاعات شخصی</span>
                </div>
                <div class="flex-1 mx-2 h-px bg-gray-200"></div>
                <div class="flex items-center gap-1.5" id="stepIndicator3">
                    <div class="step-circle w-7 h-7 rounded-full bg-gray-200 text-gray-400 flex items-center justify-center text-xs font-bold">۳</div>
                    <span class="step-label text-[11px] font-medium text-gray-400">تخصص</span>
                </div>
                <div class="flex-1 mx-2 h-px bg-gray-200"></div>
                <div class="flex items-center gap-1.5" id="stepIndicator4">
                    <div class="step-circle w-7 h-7 rounded-full bg-gray-200 text-gray-400 flex items-center justify-center text-xs font-bold">۴</div>
                    <span class="step-label text-[11px] font-medium text-gray-400">مدارک</span>
                </div>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-1 mt-3">
                <div id="progressBar" class="h-full rounded-full bg-brand-blue transition-all duration-500" style="width: 8%"></div>
            </div>
        </div>

        {{-- محتوای فرم --}}
        <div class="px-6 py-5 flex-1">

            {{-- پیام خطا/موفقیت عمومی --}}
            <div id="alertBox" class="hidden mb-4 p-3 rounded-xl text-sm flex items-center gap-2"></div>

            {{-- ===== فاز A: ورود شماره موبایل ===== --}}
            <div id="phaseA" class="phase active">
                <div class="mb-5">
                    <h2 class="text-base font-bold text-gray-800">شماره موبایل خود را وارد کنید</h2>
                    <p class="text-xs text-gray-400 mt-1">کد تایید به این شماره ارسال می‌شود</p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label for="mobile" class="block text-sm font-semibold text-gray-600 mb-1.5">شماره موبایل</label>
                        <input type="tel" id="mobile" placeholder="09123456789" maxlength="11" inputmode="numeric" dir="ltr"
                               class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none placeholder:text-gray-300 text-left">
                        <p id="mobileError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    <button id="btnSendOtp" onclick="sendOtp()" class="w-full py-3 bg-brand-blue hover:bg-brand-blue-light text-white text-sm font-bold rounded-xl transition-colors">
                        ارسال کد تایید
                    </button>
                </div>
            </div>

            {{-- ===== فاز B: ورود کد OTP ===== --}}
            <div id="phaseB" class="phase">
                <div class="mb-5">
                    <h2 class="text-base font-bold text-gray-800">کد تایید را وارد کنید</h2>
                    <p class="text-xs text-gray-400 mt-1">کد ۶ رقمی به <span id="otpMobileDisplay" class="font-bold text-gray-600 dir-ltr"></span> ارسال شد</p>
                </div>

                <div class="space-y-4">
                    {{-- باکس‌های OTP --}}
                    <div class="flex justify-center gap-2 dir-ltr" dir="ltr">
                        <input type="text" class="otp-input" maxlength="1" inputmode="numeric" data-index="0">
                        <input type="text" class="otp-input" maxlength="1" inputmode="numeric" data-index="1">
                        <input type="text" class="otp-input" maxlength="1" inputmode="numeric" data-index="2">
                        <input type="text" class="otp-input" maxlength="1" inputmode="numeric" data-index="3">
                        <input type="text" class="otp-input" maxlength="1" inputmode="numeric" data-index="4">
                        <input type="text" class="otp-input" maxlength="1" inputmode="numeric" data-index="5">
                    </div>
                    <p id="otpError" class="text-red-500 text-xs text-center hidden"></p>

                    {{-- تایمر و ارسال مجدد --}}
                    <div class="text-center">
                        <span id="otpTimer" class="text-xs text-gray-400"></span>
                        <button id="btnResendOtp" onclick="sendOtp()" class="text-xs text-brand-blue font-bold hidden hover:underline">ارسال مجدد کد</button>
                    </div>

                    <button id="btnVerifyOtp" onclick="verifyOtp()" class="w-full py-3 bg-brand-blue hover:bg-brand-blue-light text-white text-sm font-bold rounded-xl transition-colors">
                        تایید کد
                    </button>

                    <button onclick="goToPhase('A')" class="w-full text-xs text-gray-400 hover:text-gray-500 transition-colors py-1">
                        تغییر شماره موبایل
                    </button>
                </div>
            </div>

            {{-- ===== فاز C: ورود کد ملی و تاریخ تولد ===== --}}
            <div id="phaseC" class="phase">
                <div class="mb-5">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <span class="text-xs text-green-600 font-bold">شماره موبایل تایید شد</span>
                    </div>
                    <h2 class="text-base font-bold text-gray-800">اطلاعات هویتی</h2>
                    <p class="text-xs text-gray-400 mt-1">کد ملی و تاریخ تولد جهت استعلام هویت</p>
                </div>

                <div class="space-y-4">
                    {{-- شماره موبایل (غیرفعال) --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1.5">شماره موبایل</label>
                        <input type="text" id="mobileDisplay" disabled dir="ltr"
                               class="form-input w-full px-4 py-3 rounded-xl text-sm outline-none text-left">
                    </div>

                    {{-- کد ملی --}}
                    <div>
                        <label for="national_code" class="block text-sm font-semibold text-gray-600 mb-1.5">کد ملی</label>
                        <input type="text" id="national_code" placeholder="کد ملی ۱۰ رقمی" maxlength="10" inputmode="numeric" dir="ltr"
                               class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none placeholder:text-gray-300 text-left">
                        <p id="nationalCodeError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    {{-- تاریخ تولد --}}
                    <div>
                        <label for="birth_date" class="block text-sm font-semibold text-gray-600 mb-1.5">تاریخ تولد</label>
                        <input type="text" id="birth_date" placeholder="انتخاب تاریخ" readonly
                               class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none placeholder:text-gray-300 cursor-pointer">
                        <p id="birthDateError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    <button id="btnVerifyIdentity" onclick="verifyIdentity()" class="w-full py-3 bg-brand-blue hover:bg-brand-blue-light text-white text-sm font-bold rounded-xl transition-colors">
                        تایید و ادامه
                    </button>
                </div>
            </div>

            {{-- ===== فاز D: اطلاعات شخصی (مرحله ۲) ===== --}}
            <div id="phaseD" class="phase">
                <div class="mb-5">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <span class="text-xs text-green-600 font-bold">احراز هویت تایید شد</span>
                    </div>
                    <h2 class="text-base font-bold text-gray-800">اطلاعات شخصی</h2>
                    <p class="text-xs text-gray-400 mt-1">لطفاً اطلاعات زیر را تکمیل کنید</p>
                </div>

                <div class="space-y-4">
                    {{-- نام (غیرقابل ویرایش) --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1.5">نام</label>
                        <input type="text" id="step2FirstName" disabled
                               class="form-input w-full px-4 py-3 rounded-xl text-sm outline-none">
                    </div>

                    {{-- نام خانوادگی (غیرقابل ویرایش) --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1.5">نام خانوادگی</label>
                        <input type="text" id="step2LastName" disabled
                               class="form-input w-full px-4 py-3 rounded-xl text-sm outline-none">
                    </div>

                    {{-- نام پدر (غیرقابل ویرایش) --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1.5">نام پدر</label>
                        <input type="text" id="step2FatherName" disabled
                               class="form-input w-full px-4 py-3 rounded-xl text-sm outline-none">
                    </div>

                    {{-- شماره شناسنامه --}}
                    <div>
                        <label for="shenasname_number" class="block text-sm font-semibold text-gray-600 mb-1.5">شماره شناسنامه</label>
                        <input type="text" id="shenasname_number" placeholder="شماره شناسنامه" maxlength="10" inputmode="numeric" dir="ltr"
                               class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none placeholder:text-gray-300 text-left">
                        <p id="shenasnameError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    {{-- استان --}}
                    <div>
                        <label for="province" class="block text-sm font-semibold text-gray-600 mb-1.5">استان محل سکونت</label>
                        <select id="province"
                                class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none cursor-pointer appearance-none">
                            <option value="">انتخاب استان...</option>
                        </select>
                        <p id="provinceError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    {{-- شهر --}}
                    <div>
                        <label for="city" class="block text-sm font-semibold text-gray-600 mb-1.5">شهر محل سکونت</label>
                        <select id="city" disabled
                                class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none cursor-pointer appearance-none">
                            <option value="">ابتدا استان را انتخاب کنید</option>
                        </select>
                        <p id="cityError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    <button id="btnStep2" onclick="submitStep2()" class="w-full py-3 bg-brand-blue hover:bg-brand-blue-light text-white text-sm font-bold rounded-xl transition-colors">
                        ثبت و ادامه
                    </button>
                </div>
            </div>

            {{-- ===== فاز E: تکمیل مرحله ۲ ===== --}}
            <div id="phaseE" class="phase">
                <div class="text-center py-8">
                    <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <h2 class="text-lg font-bold text-gray-800 mb-2">اطلاعات شخصی ثبت شد</h2>
                    <p class="text-sm text-gray-500 mb-6">
                        <span id="verifiedName" class="font-bold text-brand-blue"></span>
                        اطلاعات شما با موفقیت ذخیره شد
                    </p>

                    <p class="text-xs text-gray-400">مرحله بعدی به زودی فعال می‌شود...</p>
                </div>
            </div>

        </div>

        {{-- لینک بازگشت --}}
        <div class="px-6 py-3 text-center">
            <a href="{{ route('technician.landing') }}" class="text-xs text-gray-400 hover:text-gray-500 transition-colors">
                بازگشت به صفحه جذب تکنسین
            </a>
        </div>

        <div class="flex-1"></div>

        {{-- فوتر --}}
        <div class="px-6 py-5 text-center border-t border-gray-100">
            <p class="text-gray-400 text-[11px]">اطلاعات شما محفوظ است و صرفاً جهت فرآیند همکاری استفاده می‌شود.</p>
        </div>
    </div>

    <script>
        // ===== متغیرهای سراسری =====
        let currentMobile = '';
        let otpTimerInterval = null;
        let verifiedFirstName = '';
        let verifiedLastName = '';
        let verifiedFatherName = '';

        // داده استان‌ها و شهرها
        const iranProvinces = @json($provinces);

        // CSRF token
        $.ajaxSetup({
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
        });

        // ===== مدیریت فازها =====
        function goToPhase(phase) {
            $('.phase').removeClass('active');
            $('#phase' + phase).addClass('active');
            hideAlert();

            // پراگرس بار
            const progress = { A: 8, B: 16, C: 25, D: 55, E: 60 };
            $('#progressBar').css('width', (progress[phase] || 8) + '%');

            // به‌روزرسانی نوار مراحل
            updateStepIndicators(phase);

            // وقتی فاز C نمایش داده شد، دیت‌پیکر رو init کن
            if (phase === 'C') {
                setTimeout(initDatePicker, 300);
            }

            // وقتی فاز D نمایش داده شد، دراپ‌داون استان رو پر کن
            if (phase === 'D') {
                initProvinceDropdown();
            }
        }

        function updateStepIndicators(phase) {
            // مراحل: A,B,C = step1 | D,E = step2
            const phaseToStep = { A: 1, B: 1, C: 1, D: 2, E: 2 };
            const currentStep = phaseToStep[phase] || 1;

            for (let i = 1; i <= 4; i++) {
                const indicator = $('#stepIndicator' + i);
                const circle = indicator.find('.step-circle');
                const label = indicator.find('.step-label');

                if (i < currentStep) {
                    // تکمیل شده
                    circle.removeClass('bg-gray-200 text-gray-400 bg-brand-blue text-white').addClass('bg-green-500 text-white');
                    circle.html('<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>');
                    label.removeClass('text-gray-400 text-brand-blue font-medium').addClass('text-green-600 font-bold');
                } else if (i === currentStep) {
                    // فعال
                    circle.removeClass('bg-gray-200 text-gray-400 bg-green-500').addClass('bg-brand-blue text-white');
                    label.removeClass('text-gray-400 text-green-600 font-medium').addClass('text-brand-blue font-bold');
                } else {
                    // غیرفعال
                    circle.removeClass('bg-brand-blue text-white bg-green-500').addClass('bg-gray-200 text-gray-400');
                    label.removeClass('text-brand-blue text-green-600 font-bold').addClass('text-gray-400 font-medium');
                }
            }
        }

        // ===== پیام‌ها =====
        function showAlert(msg, type) {
            const box = $('#alertBox');
            box.removeClass('hidden bg-red-50 border-red-100 text-red-700 bg-green-50 border-green-100 text-green-700 bg-yellow-50 border-yellow-100 text-yellow-700');
            if (type === 'error') box.addClass('bg-red-50 border border-red-100 text-red-700');
            else if (type === 'success') box.addClass('bg-green-50 border border-green-100 text-green-700');
            else box.addClass('bg-yellow-50 border border-yellow-100 text-yellow-700');
            box.html(msg).removeClass('hidden');
        }
        function hideAlert() { $('#alertBox').addClass('hidden'); }

        function showFieldError(id, msg) { $('#' + id).text(msg).removeClass('hidden'); }
        function hideFieldError(id) { $('#' + id).text('').addClass('hidden'); }
        function clearAllErrors() { hideFieldError('mobileError'); hideFieldError('otpError'); hideFieldError('nationalCodeError'); hideFieldError('birthDateError'); hideFieldError('shenasnameError'); hideFieldError('provinceError'); hideFieldError('cityError'); hideAlert(); }

        function setLoading(btnId, loading) {
            const btn = $('#' + btnId);
            if (loading) {
                btn.addClass('btn-loading');
                btn.data('original-text', btn.html());
                btn.html('<span class="spinner"></span>');
            } else {
                btn.removeClass('btn-loading');
                btn.html(btn.data('original-text'));
            }
        }

        // ===== فاز A: ارسال OTP =====
        function sendOtp() {
            clearAllErrors();
            const mobile = $('#mobile').val().trim();

            if (!/^09[0-9]{9}$/.test(mobile)) {
                showFieldError('mobileError', 'شماره موبایل باید با 09 شروع شده و ۱۱ رقم باشد.');
                return;
            }

            currentMobile = mobile;
            setLoading('btnSendOtp', true);
            setLoading('btnResendOtp', true);

            $.post('{{ route("technician.register.send-otp") }}', { mobile: mobile })
                .done(function(res) {
                    if (res.success) {
                        $('#otpMobileDisplay').text(mobile);
                        goToPhase('B');
                        startOtpTimer(res.expires_in || 120);
                        $('.otp-input').val('').first().focus();
                    } else {
                        showFieldError('mobileError', res.message);
                    }
                })
                .fail(function(xhr) {
                    const res = xhr.responseJSON;
                    showFieldError('mobileError', res?.message || 'خطا در ارسال کد تایید');
                })
                .always(function() {
                    setLoading('btnSendOtp', false);
                    setLoading('btnResendOtp', false);
                });
        }

        // ===== تایمر OTP =====
        function startOtpTimer(seconds) {
            clearInterval(otpTimerInterval);
            let remaining = seconds;
            $('#otpTimer').removeClass('hidden');
            $('#btnResendOtp').addClass('hidden');

            function updateTimer() {
                const m = Math.floor(remaining / 60);
                const s = remaining % 60;
                $('#otpTimer').text(`ارسال مجدد تا ${m}:${s.toString().padStart(2, '0')}`);
                if (remaining <= 0) {
                    clearInterval(otpTimerInterval);
                    $('#otpTimer').addClass('hidden');
                    $('#btnResendOtp').removeClass('hidden');
                }
                remaining--;
            }
            updateTimer();
            otpTimerInterval = setInterval(updateTimer, 1000);
        }

        // ===== فاز B: تایید OTP =====
        function verifyOtp() {
            clearAllErrors();
            const code = getOtpCode();

            if (code.length !== 6) {
                showFieldError('otpError', 'لطفاً کد ۶ رقمی را کامل وارد کنید.');
                return;
            }

            setLoading('btnVerifyOtp', true);

            $.post('{{ route("technician.register.verify-otp") }}', { mobile: currentMobile, code: code })
                .done(function(res) {
                    if (res.success) {
                        $('#mobileDisplay').val(currentMobile);
                        goToPhase('C');
                        initDatePicker();
                    } else {
                        showFieldError('otpError', res.message);
                    }
                })
                .fail(function(xhr) {
                    const res = xhr.responseJSON;
                    showFieldError('otpError', res?.message || 'خطا در تایید کد');
                })
                .always(function() {
                    setLoading('btnVerifyOtp', false);
                });
        }

        function getOtpCode() {
            let code = '';
            $('.otp-input').each(function() { code += $(this).val(); });
            return code;
        }

        // ===== فاز C: تایید هویت =====
        function verifyIdentity() {
            clearAllErrors();
            const nationalCode = $('#national_code').val().trim();
            let birthDate = $('#birth_date').val().trim();

            let hasError = false;

            if (!/^[0-9]{10}$/.test(nationalCode)) {
                showFieldError('nationalCodeError', 'کد ملی باید ۱۰ رقم باشد.');
                hasError = true;
            }

            if (!birthDate) {
                showFieldError('birthDateError', 'تاریخ تولد الزامی است.');
                hasError = true;
            }

            if (hasError) return;

            // تبدیل اعداد فارسی/عربی به انگلیسی
            birthDate = birthDate
                .replace(/[۰-۹]/g, function(d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); })
                .replace(/[٠-٩]/g, function(d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); });

            setLoading('btnVerifyIdentity', true);

            $.post('{{ route("technician.register.step1") }}', {
                mobile: currentMobile,
                national_code: nationalCode,
                birth_date: birthDate
            })
            .done(function(res) {
                if (res.success) {
                    // ذخیره اطلاعات هویتی برای مرحله ۲
                    verifiedFirstName = res.first_name || '';
                    verifiedLastName = res.last_name || '';
                    verifiedFatherName = res.father_name || '';

                    // پر کردن فیلدهای غیرقابل ویرایش مرحله ۲
                    $('#step2FirstName').val(verifiedFirstName);
                    $('#step2LastName').val(verifiedLastName);
                    $('#step2FatherName').val(verifiedFatherName);
                    $('#verifiedName').text(verifiedFirstName + ' ' + verifiedLastName);

                    goToPhase('D');
                } else {
                    if (res.field) showFieldError(res.field === 'national_code' ? 'nationalCodeError' : 'birthDateError', res.message);
                    else showAlert(res.message, 'error');
                }
            })
            .fail(function(xhr) {
                const res = xhr.responseJSON;
                if (res?.field) {
                    showFieldError(res.field === 'national_code' ? 'nationalCodeError' : 'birthDateError', res.message);
                } else if (res?.message) {
                    showAlert(res.message, 'error');
                } else {
                    showAlert('خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.', 'error');
                }
            })
            .always(function() {
                setLoading('btnVerifyIdentity', false);
            });
        }

        // ===== فاز D: مرحله ۲ - اطلاعات شخصی =====
        let provinceDropdownReady = false;

        function initProvinceDropdown() {
            if (provinceDropdownReady) return;

            const select = $('#province');
            select.empty().append('<option value="">انتخاب استان...</option>');

            Object.keys(iranProvinces).sort().forEach(function(name) {
                select.append($('<option>').val(name).text(name));
            });

            provinceDropdownReady = true;
        }

        function submitStep2() {
            clearAllErrors();

            const shenasname = $('#shenasname_number').val().trim();
            const province = $('#province').val();
            const city = $('#city').val();

            let hasError = false;

            if (!shenasname || !/^[0-9]{1,10}$/.test(shenasname)) {
                showFieldError('shenasnameError', 'شماره شناسنامه باید عددی باشد.');
                hasError = true;
            }

            if (!province) {
                showFieldError('provinceError', 'انتخاب استان الزامی است.');
                hasError = true;
            }

            if (!city) {
                showFieldError('cityError', 'انتخاب شهر الزامی است.');
                hasError = true;
            }

            if (hasError) return;

            setLoading('btnStep2', true);

            $.post('{{ route("technician.register.step2") }}', {
                mobile: currentMobile,
                shenasname_number: shenasname,
                province: province,
                city: city
            })
            .done(function(res) {
                if (res.success) {
                    goToPhase('E');
                } else {
                    if (res.field) {
                        const errorMap = { shenasname_number: 'shenasnameError', province: 'provinceError', city: 'cityError' };
                        showFieldError(errorMap[res.field] || 'shenasnameError', res.message);
                    } else {
                        showAlert(res.message, 'error');
                    }
                }
            })
            .fail(function(xhr) {
                const res = xhr.responseJSON;
                if (res?.field) {
                    const errorMap = { shenasname_number: 'shenasnameError', province: 'provinceError', city: 'cityError' };
                    showFieldError(errorMap[res.field] || 'shenasnameError', res.message);
                } else if (res?.message) {
                    showAlert(res.message, 'error');
                } else {
                    showAlert('خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.', 'error');
                }
            })
            .always(function() {
                setLoading('btnStep2', false);
            });
        }

        // ===== دیت‌پیکر =====
        let datePickerReady = false;

        function initDatePicker() {
            if (datePickerReady) return;

            console.log('[DatePicker] jQuery:', typeof $);
            console.log('[DatePicker] $.fn.persianDatepicker:', typeof $.fn.persianDatepicker);
            console.log('[DatePicker] $.fn.pDatepicker:', typeof $.fn.pDatepicker);
            console.log('[DatePicker] #birth_date visible:', $('#birth_date').is(':visible'));

            if (typeof $.fn.persianDatepicker === 'undefined') {
                console.error('[DatePicker] persian-datepicker plugin NOT loaded!');
                return;
            }

            try {
                $("#birth_date").persianDatepicker({
                    format: 'YYYY/MM/DD',
                    autoClose: true,
                    persianDigits: false,
                    initialValue: false,
                    observer: true,
                    calendar: { persian: { locale: 'fa' } },
                    toolbox: {
                        calendarSwitch: { enabled: false },
                        todayButton: { enabled: true, text: { fa: 'امروز' } },
                        submitButton: { enabled: false }
                    },
                    navigator: {
                        enabled: true,
                        scroll: { enabled: true },
                        text: { btnNextText: '<', btnPrevText: '>' }
                    },
                    timePicker: { enabled: false },
                    responsive: true,
                });
                datePickerReady = true;
                console.log('[DatePicker] initialized OK');
            } catch(e) {
                console.error('[DatePicker] init error:', e);
            }
        }

        // ===== رویدادها =====
        $(document).ready(function() {
            // دیباگ: بررسی container دیت‌پیکر بعد کلیک
            $(document).on('click', '#birth_date', function() {
                setTimeout(function() {
                    var containers = document.querySelectorAll('.datepicker-container');
                    console.log('[DatePicker] click - containers found:', containers.length);
                    for (var i = 0; i < containers.length; i++) {
                        var c = containers[i];
                        var style = window.getComputedStyle(c);
                        console.log('[DatePicker] container:', {
                            display: style.display,
                            visibility: style.visibility,
                            width: c.offsetWidth,
                            height: c.offsetHeight,
                            top: style.top,
                            left: style.left,
                            position: style.position
                        });
                    }
                }, 200);
            });

            // فقط عدد در اینپوت‌ها
            $('#mobile, #national_code, #shenasname_number').on('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
            });

            // تغییر استان → بارگذاری شهرها
            $('#province').on('change', function() {
                const province = $(this).val();
                const citySelect = $('#city');

                if (!province) {
                    citySelect.empty().append('<option value="">ابتدا استان را انتخاب کنید</option>').prop('disabled', true);
                    return;
                }

                const cities = iranProvinces[province] || [];
                citySelect.empty().append('<option value="">انتخاب شهر...</option>');
                cities.forEach(function(c) {
                    citySelect.append($('<option>').val(c).text(c));
                });
                citySelect.prop('disabled', false);
            });

            // مدیریت اینپوت‌های OTP
            $('.otp-input').on('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value && $(this).data('index') < 5) {
                    $(this).next('.otp-input').focus();
                }
                // اگر همه پر شدن، اتوماتیک verify
                if (getOtpCode().length === 6) {
                    verifyOtp();
                }
            });

            $('.otp-input').on('keydown', function(e) {
                // بک‌اسپیس → برو عقب
                if (e.key === 'Backspace' && !this.value && $(this).data('index') > 0) {
                    $(this).prev('.otp-input').focus().val('');
                }
            });

            // پیست کردن کد OTP
            $('.otp-input').first().on('paste', function(e) {
                e.preventDefault();
                const pasted = (e.originalEvent.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '').slice(0, 6);
                pasted.split('').forEach(function(digit, i) {
                    $('.otp-input').eq(i).val(digit);
                });
                if (pasted.length === 6) verifyOtp();
            });

            // اینتر در فیلد موبایل
            $('#mobile').on('keydown', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); sendOtp(); }
            });
        });
    </script>

</body>
</html>
