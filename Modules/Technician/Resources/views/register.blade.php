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

        /* تب‌های مراحل - اسکرول افقی */
        .overflow-x-auto::-webkit-scrollbar { height: 3px; }
        .overflow-x-auto::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
        [id^="stepIndicator"]:hover .step-circle { transform: scale(1.1); }
        [id^="stepIndicator"] .step-circle { transition: all 0.2s; }

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

        {{-- نوار مراحل (اسکرول افقی) --}}
        <div class="px-4 py-3 bg-gray-50/50">
            <div class="overflow-x-auto pb-1 -mx-1" style="scrollbar-width: thin;">
                <div class="flex items-center gap-0 min-w-max px-1">
                    @php
                        $stepLabels = ['هویت', 'شخصی', 'تکمیلی', 'مناطق', 'فعالیت', 'قرارداد', 'مدارک', 'ویدیو'];
                        $stepNumbers = ['۱','۲','۳','۴','۵','۶','۷','۸'];
                    @endphp
                    @foreach($stepLabels as $i => $label)
                        @php $num = $i + 1; @endphp
                        <div class="flex items-center gap-0.5 cursor-pointer select-none shrink-0" id="stepIndicator{{ $num }}" onclick="onStepClick({{ $num }})">
                            <div class="step-circle w-5 h-5 rounded-full {{ $num === 1 ? 'bg-brand-blue text-white' : 'bg-gray-200 text-gray-400' }} flex items-center justify-center text-[9px] font-bold transition-all">{{ $stepNumbers[$i] }}</div>
                            <span class="step-label text-[9px] {{ $num === 1 ? 'font-bold text-brand-blue' : 'font-medium text-gray-400' }} transition-all whitespace-nowrap">{{ $label }}</span>
                        </div>
                        @if($num < 8)
                        <div class="flex-shrink-0 w-3 h-px bg-gray-200 mx-0.5 step-line" id="stepLine{{ $num }}"></div>
                        @endif
                    @endforeach
                </div>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-1 mt-2">
                <div id="progressBar" class="h-full rounded-full bg-brand-blue transition-all duration-500" style="width: 5%"></div>
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

                    {{-- جنسیت --}}
                    <div>
                        <label for="gender" class="block text-sm font-semibold text-gray-600 mb-1.5">جنسیت</label>
                        <select id="gender"
                                class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none cursor-pointer appearance-none">
                            <option value="">انتخاب کنید...</option>
                            <option value="male">مرد</option>
                            <option value="female">زن</option>
                        </select>
                        <p id="genderError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    {{-- وضعیت تاهل --}}
                    <div>
                        <label for="marital_status" class="block text-sm font-semibold text-gray-600 mb-1.5">وضعیت تاهل</label>
                        <select id="marital_status" onchange="toggleChildrenCount()"
                                class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none cursor-pointer appearance-none">
                            <option value="">انتخاب کنید...</option>
                            <option value="single">مجرد</option>
                            <option value="married">متاهل</option>
                        </select>
                        <p id="maritalError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    {{-- تعداد فرزندان --}}
                    <div id="childrenCountWrapper" class="hidden">
                        <label for="children_count" class="block text-sm font-semibold text-gray-600 mb-1.5">تعداد فرزندان</label>
                        <select id="children_count"
                                class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none cursor-pointer appearance-none">
                            <option value="0">بدون فرزند</option>
                            <option value="1">۱</option>
                            <option value="2">۲</option>
                            <option value="3">۳</option>
                            <option value="4">۴</option>
                            <option value="5">۵ و بیشتر</option>
                        </select>
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

            {{-- ===== فاز E: اطلاعات تکمیلی (مرحله ۳) ===== --}}
            <div id="phaseE" class="phase">
                <div class="mb-5">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <span class="text-xs text-green-600 font-bold">اطلاعات شخصی ثبت شد</span>
                    </div>
                    <h2 class="text-base font-bold text-gray-800">اطلاعات تکمیلی</h2>
                    <p class="text-xs text-gray-400 mt-1">لطفاً اطلاعات زیر را تکمیل کنید</p>
                </div>

                <div class="space-y-5">

                    {{-- مقطع تحصیلی --}}
                    <div>
                        <label for="education_level" class="block text-sm font-semibold text-gray-600 mb-1.5">مقطع تحصیلی <span class="text-red-500">*</span></label>
                        <select id="education_level"
                                class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none cursor-pointer appearance-none">
                            <option value="">انتخاب کنید...</option>
                            <option value="below_diploma">زیر دیپلم</option>
                            <option value="diploma">دیپلم</option>
                            <option value="associate">کاردانی</option>
                            <option value="bachelor">کارشناسی</option>
                            <option value="master">کارشناسی ارشد</option>
                            <option value="doctorate">دکتری</option>
                        </select>
                        <p id="educationLevelError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    {{-- رشته تحصیلی --}}
                    <div>
                        <label for="field_of_study" class="block text-sm font-semibold text-gray-600 mb-1.5">رشته تحصیلی</label>
                        <input type="text" id="field_of_study" placeholder="مثال: برق، مکانیک، تاسیسات..."
                               class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none placeholder:text-gray-300">
                    </div>

                    {{-- پروانه کسب --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1.5">آیا پروانه کسب دارید؟</label>
                        <div class="flex gap-3">
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="has_business_license" value="1" class="hidden peer">
                                <div class="form-input text-center py-3 rounded-xl text-sm peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all">
                                    بله
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="has_business_license" value="0" class="hidden peer" checked>
                                <div class="form-input text-center py-3 rounded-xl text-sm peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all">
                                    خیر
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- مغازه/دفتر --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1.5">آیا مغازه یا دفتر کار دارید؟</label>
                        <div class="flex gap-3">
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="has_shop" value="1" class="hidden peer" onchange="toggleShopFields(true)">
                                <div class="form-input text-center py-3 rounded-xl text-sm peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all">
                                    بله
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="has_shop" value="0" class="hidden peer" checked onchange="toggleShopFields(false)">
                                <div class="form-input text-center py-3 rounded-xl text-sm peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all">
                                    خیر
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- فیلدهای مغازه --}}
                    <div id="shopFields" class="space-y-4 hidden">
                        <div>
                            <label for="shop_address" class="block text-sm font-semibold text-gray-600 mb-1.5">آدرس مغازه/دفتر</label>
                            <textarea id="shop_address" rows="2" placeholder="آدرس کامل مغازه یا دفتر کار..."
                                      class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none placeholder:text-gray-300 resize-none"></textarea>
                            <p id="shopAddressError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                        </div>
                        <div>
                            <label for="shop_phone" class="block text-sm font-semibold text-gray-600 mb-1.5">تلفن مغازه/دفتر</label>
                            <input type="tel" id="shop_phone" placeholder="شماره تلفن ثابت" maxlength="15" dir="ltr"
                                   class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none placeholder:text-gray-300 text-left">
                            <p id="shopPhoneError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                        </div>
                    </div>

                    {{-- همکاری با شرکت‌ها --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1.5">آیا در حال حاضر با شرکت‌ها یا مجموعه‌های همکار در حوزه فعالیت ما همکاری دارید؟</label>
                        <div class="flex gap-3 mb-2">
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="has_cooperation" value="1" class="hidden peer" onchange="toggleCooperationFields(true)">
                                <div class="form-input text-center py-3 rounded-xl text-sm peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all">
                                    بله
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="has_cooperation" value="0" class="hidden peer" checked onchange="toggleCooperationFields(false)">
                                <div class="form-input text-center py-3 rounded-xl text-sm peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all">
                                    خیر
                                </div>
                            </label>
                        </div>
                        <div id="cooperationFields" class="hidden">
                            <textarea id="cooperation_companies" rows="2" placeholder="نام شرکت‌ها یا مجموعه‌ها..."
                                      class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none placeholder:text-gray-300 resize-none"></textarea>
                            <p id="cooperationError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                        </div>
                    </div>

                    {{-- معرف --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1.5">در صورت داشتن معرف: نام و شماره تماس</label>
                        <div class="grid grid-cols-2 gap-3">
                            <input type="text" id="referrer_name" placeholder="نام معرف"
                                   class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none placeholder:text-gray-300">
                            <input type="tel" id="referrer_phone" placeholder="شماره تماس معرف" dir="ltr"
                                   class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none placeholder:text-gray-300 text-left">
                        </div>
                    </div>

                    {{-- هم‌صنفی‌ها --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1.5">لطفاً نام و شماره تماس دو نفر از هم‌صنفی‌های آشنا با شما را ذکر نمایید</label>
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-3">
                                <input type="text" id="colleague1_name" placeholder="نام نفر اول"
                                       class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none placeholder:text-gray-300">
                                <input type="tel" id="colleague1_phone" placeholder="شماره تماس نفر اول" dir="ltr"
                                       class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none placeholder:text-gray-300 text-left">
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <input type="text" id="colleague2_name" placeholder="نام نفر دوم"
                                       class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none placeholder:text-gray-300">
                                <input type="tel" id="colleague2_phone" placeholder="شماره تماس نفر دوم" dir="ltr"
                                       class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none placeholder:text-gray-300 text-left">
                            </div>
                        </div>
                    </div>

                    {{-- سوابق شغلی --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-semibold text-gray-600">سوابق شغلی <span class="text-red-500">*</span></label>
                            <button type="button" onclick="addWorkExperience()"
                                    class="text-xs text-brand-blue font-bold hover:underline flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                افزودن سابقه
                            </button>
                        </div>
                        <div id="workExperiencesList" class="space-y-3">
                            {{-- آیتم‌ها به صورت داینامیک اضافه می‌شوند --}}
                        </div>
                        <p class="text-xs text-gray-400 mt-1">حداقل یک سابقه شغلی الزامی است.</p>
                        <p id="workExperienceError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    {{-- مدارک و دوره‌ها --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-semibold text-gray-600">مدارک و دوره‌های آموزشی</label>
                            <button type="button" onclick="addCertificate()"
                                    class="text-xs text-brand-blue font-bold hover:underline flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                افزودن مدرک
                            </button>
                        </div>
                        <div id="certificatesList" class="space-y-3">
                            {{-- آیتم‌ها به صورت داینامیک اضافه می‌شوند --}}
                        </div>
                        <p class="text-xs text-gray-400 mt-1">دوره‌ها و مدارک مرتبط با حرفه خود را اضافه کنید.</p>
                    </div>

                    <p id="step3GeneralError" class="text-red-500 text-xs hidden"></p>

                    <button id="btnStep3" onclick="submitStep3()" class="w-full py-3 bg-brand-blue hover:bg-brand-blue-light text-white text-sm font-bold rounded-xl transition-colors">
                        ثبت و ادامه
                    </button>
                </div>
            </div>

            {{-- ===== فاز F: مناطق تحت پوشش (مرحله ۴) ===== --}}
            <div id="phaseF" class="phase">
                <div class="mb-5">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <span class="text-xs text-green-600 font-bold">اطلاعات تکمیلی ثبت شد</span>
                    </div>
                    <h2 class="text-base font-bold text-gray-800">مناطق تحت پوشش</h2>
                    <p class="text-xs text-gray-400 mt-1">مشخص کنید در کدام مناطق می‌توانید خدمات ارائه دهید</p>
                </div>

                <div class="space-y-5">

                    {{-- سوال خدمات تهران --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1.5">آیا قصد ارائه خدمات در شهرهای استان تهران را دارید؟</label>
                        <div class="flex gap-3">
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="serves_tehran" value="1" class="hidden peer" onchange="toggleTehranDistricts(true)">
                                <div class="form-input text-center py-3 rounded-xl text-sm peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all">
                                    بله
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="serves_tehran" value="0" class="hidden peer" onchange="toggleTehranDistricts(false)">
                                <div class="form-input text-center py-3 rounded-xl text-sm peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all">
                                    خیر
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- مناطق تهران --}}
                    <div id="tehranDistrictsSection" style="display: none;">
                        <label class="block text-sm font-semibold text-gray-600 mb-2">مناطق تهران که می‌توانید سرویس ارائه دهید:</label>
                        <div class="grid grid-cols-4 gap-2" id="tehranDistrictsGrid">
                            {{-- گزینه همه مناطق --}}
                            <label class="col-span-4 cursor-pointer">
                                <input type="checkbox" id="allTehranDistricts" class="hidden peer" onchange="toggleAllDistricts(this)">
                                <div class="form-input text-center py-2.5 rounded-xl text-sm peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all">
                                    همه مناطق
                                </div>
                            </label>
                            @for($i = 1; $i <= 22; $i++)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="tehran_district" value="{{ $i }}" class="hidden peer tehran-district-cb" onchange="checkAllDistrictsState()">
                                <div class="form-input text-center py-2 rounded-xl text-xs peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all">
                                    منطقه {{ $i }}
                                </div>
                            </label>
                            @endfor
                        </div>
                        <p id="tehranDistrictsError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    {{-- شهرهای استان تهران (غیر از تهران) --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1.5">آیا در شهرهای دیگر استان تهران هم می‌توانید خدمات ارائه دهید؟</label>
                        <div class="flex gap-3">
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="has_tehran_cities" value="1" class="hidden peer" onchange="toggleTehranCities(true)">
                                <div class="form-input text-center py-3 rounded-xl text-sm peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all">
                                    بله
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="has_tehran_cities" value="0" class="hidden peer" checked onchange="toggleTehranCities(false)">
                                <div class="form-input text-center py-3 rounded-xl text-sm peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all">
                                    خیر
                                </div>
                            </label>
                        </div>
                        <div id="tehranCitiesGrid" class="hidden mt-3 grid grid-cols-3 gap-2">
                            {{-- شهرهای استان تهران بجز تهران --}}
                        </div>
                        <p id="tehranCitiesError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    {{-- شهرهای استان البرز --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1.5">آیا قصد ارائه خدمات در شهرهای استان البرز را دارید؟</label>
                        <div class="flex gap-3">
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="has_alborz_cities" value="1" class="hidden peer" onchange="toggleAlborzCities(true)">
                                <div class="form-input text-center py-3 rounded-xl text-sm peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all">
                                    بله
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="has_alborz_cities" value="0" class="hidden peer" checked onchange="toggleAlborzCities(false)">
                                <div class="form-input text-center py-3 rounded-xl text-sm peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all">
                                    خیر
                                </div>
                            </label>
                        </div>
                        <div id="alborzCitiesGrid" class="hidden mt-3 grid grid-cols-3 gap-2">
                            {{-- شهرهای استان البرز --}}
                        </div>
                        <p id="alborzCitiesError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    {{-- سایر استان‌ها --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1.5">آیا قصد ارائه خدمات در شهرهای دیگر را دارید؟</label>
                        <div class="flex gap-3">
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="has_other_provinces" value="1" class="hidden peer" onchange="toggleOtherProvinces(true)">
                                <div class="form-input text-center py-3 rounded-xl text-sm peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all">
                                    بله
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="has_other_provinces" value="0" class="hidden peer" checked onchange="toggleOtherProvinces(false)">
                                <div class="form-input text-center py-3 rounded-xl text-sm peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all">
                                    خیر
                                </div>
                            </label>
                        </div>
                        <div id="otherProvincesField" class="hidden mt-3">
                            <textarea id="other_provinces_cities" rows="3" placeholder="نام استان و شهرهایی که می‌توانید خدمات ارائه دهید را بنویسید..."
                                      class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none placeholder:text-gray-300 resize-none"></textarea>
                        </div>
                        <p id="otherProvincesError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    <p id="step4GeneralError" class="text-red-500 text-xs hidden"></p>

                    <button id="btnStep4" onclick="submitStep4()" class="w-full py-3 bg-brand-blue hover:bg-brand-blue-light text-white text-sm font-bold rounded-xl transition-colors">
                        ثبت و ادامه
                    </button>
                </div>
            </div>

            {{-- ===== فاز G: زمینه فعالیت (مرحله ۵) ===== --}}
            <div id="phaseG" class="phase">
                <div class="mb-5">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <span class="text-xs text-green-600 font-bold">مناطق تحت پوشش ثبت شد</span>
                    </div>
                    <h2 class="text-base font-bold text-gray-800">زمینه فعالیت</h2>
                    <p class="text-xs text-gray-400 mt-1">نوع فعالیت و دستگاه‌هایی که سرویس می‌دهید را مشخص کنید</p>
                </div>

                <div class="space-y-5">

                    {{-- زمینه فعالیت --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-2">زمینه فعالیت شما:</label>
                        <div class="flex gap-2">
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="activity_type" value="install" class="hidden peer">
                                <div class="form-input text-center py-3 rounded-xl text-sm peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all">
                                    نصب
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="activity_type" value="repair" class="hidden peer">
                                <div class="form-input text-center py-3 rounded-xl text-sm peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all">
                                    تعمیر
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="activity_type" value="install_repair" class="hidden peer">
                                <div class="form-input text-center py-3 rounded-xl text-sm peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all">
                                    نصب و تعمیر
                                </div>
                            </label>
                        </div>
                        <p id="activityTypeError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    {{-- دستگاه‌ها --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-2">دستگاه‌هایی که سرویس می‌دهید:</label>
                        <div class="grid grid-cols-2 gap-2" id="applianceCategoriesGrid">
                            @foreach($appliance_categories as $cat)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="appliance_category" value="{{ $cat->id }}" class="hidden peer appliance-cat-cb">
                                <div class="form-input text-center py-2 rounded-xl text-xs peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all">
                                    {{ $cat->name }}
                                </div>
                            </label>
                            @endforeach
                        </div>
                        <p id="applianceCategoriesError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    {{-- نحوه ارائه خدمات --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-2">نحوه ارائه خدمات به مشتریان:</label>
                        <div class="flex gap-2">
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="transportation_method" value="motorcycle" class="hidden peer">
                                <div class="form-input text-center py-3 rounded-xl text-sm peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all">
                                    موتور
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="transportation_method" value="car" class="hidden peer">
                                <div class="form-input text-center py-3 rounded-xl text-sm peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all">
                                    خودرو
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="transportation_method" value="none" class="hidden peer">
                                <div class="form-input text-center py-3 rounded-xl text-xs peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all leading-tight">
                                    وسیله نقلیه ندارم
                                </div>
                            </label>
                        </div>
                        <p id="transportationError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    {{-- نوع تعمیر --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-2">آیا در حوزه تخصصی خود تعمیر بردهای الکترونیکی و قطعات مکانیکی دستگاه‌ها را انجام می‌دهید یا صرفاً تعویض قطعات انجام می‌شود؟</label>
                        <div class="flex gap-2">
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="repair_skill" value="board_repair" class="hidden peer">
                                <div class="form-input text-center py-3 rounded-xl text-xs peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all leading-tight">
                                    تعمیر برد و قطعات
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="repair_skill" value="parts_only" class="hidden peer">
                                <div class="form-input text-center py-3 rounded-xl text-xs peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all leading-tight">
                                    صرفاً تعویض قطعات
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="repair_skill" value="both" class="hidden peer">
                                <div class="form-input text-center py-3 rounded-xl text-xs peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all leading-tight">
                                    هر دو
                                </div>
                            </label>
                        </div>
                        <p id="repairSkillError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    {{-- سابقه تعمیرات بردهای الکترونیکی --}}
                    <div>
                        <label for="board_repair_experience" class="block text-sm font-semibold text-gray-600 mb-1.5">میزان آشنایی و سابقه کاری در زمینه تعمیرات بردهای الکترونیکی</label>
                        <select id="board_repair_experience"
                                class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none cursor-pointer appearance-none">
                            <option value="">انتخاب کنید...</option>
                            <option value="none">تجربه‌ای ندارم</option>
                            <option value="beginner">مبتدی (کمتر از ۱ سال)</option>
                            <option value="intermediate">متوسط (۱ تا ۳ سال)</option>
                            <option value="advanced">حرفه‌ای (۳ تا ۵ سال)</option>
                            <option value="expert">متخصص (بیش از ۵ سال)</option>
                        </select>
                    </div>

                    {{-- توضیحات تکمیلی --}}
                    <div>
                        <label for="additional_notes" class="block text-sm font-semibold text-gray-600 mb-1.5">در صورت داشتن هرگونه توضیح یا نکته تکمیلی، لطفاً در این بخش درج شود</label>
                        <textarea id="additional_notes" rows="3" placeholder="توضیحات تکمیلی..."
                                  class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none placeholder:text-gray-300 resize-none"></textarea>
                    </div>

                    <p id="step5GeneralError" class="text-red-500 text-xs hidden"></p>

                    <button id="btnGoToAgreement" onclick="goToAgreement()" class="w-full py-3 bg-brand-blue hover:bg-brand-blue-light text-white text-sm font-bold rounded-xl transition-colors">
                        ادامه
                    </button>
                </div>
            </div>

            {{-- ===== فاز H: موافقت‌نامه ===== --}}
            <div id="phaseH" class="phase">
                <div class="mb-5">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <span class="text-xs text-green-600 font-bold">اطلاعات فعالیت ثبت شد</span>
                    </div>
                    <h2 class="text-base font-bold text-gray-800">شرایط همکاری</h2>
                </div>

                <div class="space-y-5">
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <p class="text-sm text-gray-700 leading-relaxed text-justify">
                            تعمیرکار محترم، پس از ارزیابی اولیه توسط مجموعه «تعمیر آنلاین»، در صورت تأیید، لازم است تصویر مدارک شناسایی، مدارک شغلی و گواهی عدم سوءپیشینه را در سامانه بارگذاری نمایید. همچنین قرارداد به‌صورت الکترونیکی منعقد خواهد شد و به‌منظور تضمین حسن انجام کار، سفته الکترونیکی به مبلغ دویست میلیون تومان دریافت می‌گردد.
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">آیا با این شرایط موافق هستید؟</label>
                        <div class="flex gap-3">
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="agreement" value="yes" class="hidden peer">
                                <div class="form-input text-center py-3 rounded-xl text-sm peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:text-green-700 font-semibold transition-all">
                                    بلی، موافقم
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="agreement" value="no" class="hidden peer">
                                <div class="form-input text-center py-3 rounded-xl text-sm peer-checked:border-red-500 peer-checked:bg-red-50 peer-checked:text-red-700 font-semibold transition-all">
                                    خیر
                                </div>
                            </label>
                        </div>
                        <p id="agreementError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    <button id="btnStep5" onclick="submitStep5()" class="w-full py-3 bg-brand-blue hover:bg-brand-blue-light text-white text-sm font-bold rounded-xl transition-colors">
                        ثبت نهایی
                    </button>
                </div>
            </div>

            {{-- ===== فاز I: تکمیل ثبت‌نام (در انتظار بررسی) ===== --}}
            <div id="phaseI" class="phase">
                <div class="text-center py-8">
                    <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <h2 class="text-lg font-bold text-gray-800 mb-2">ثبت‌نام شما با موفقیت تکمیل شد!</h2>
                    <p class="text-sm text-gray-500 mb-3">
                        <span id="verifiedName" class="font-bold text-brand-blue"></span>
                        از اعتماد شما سپاسگزاریم.
                    </p>
                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 mx-4 mt-4">
                        <p class="text-sm text-brand-blue font-semibold mb-1">در انتظار بررسی</p>
                        <p class="text-xs text-gray-500 leading-relaxed">
                            اطلاعات شما در حال بررسی است. پس از تایید، از طریق پیامک به شماره موبایل ثبت‌شده اطلاع‌رسانی خواهد شد.
                        </p>
                    </div>
                </div>
            </div>

            {{-- ===== فاز رد شده ===== --}}
            <div id="phaseRejected" class="phase">
                <div class="text-center py-8">
                    <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </div>
                    <h2 class="text-lg font-bold text-red-600 mb-2">درخواست شما رد شد</h2>
                    <p class="text-sm text-gray-500 mb-3">
                        <span id="rejectedName" class="font-bold text-brand-blue"></span>
                        متاسفانه درخواست شما مورد تایید قرار نگرفت.
                    </p>
                    <div id="rejectionReasonBox" class="bg-red-50 border border-red-200 rounded-xl p-4 mx-4 mt-4 text-right hidden">
                        <p class="text-sm text-red-700 font-semibold mb-1">دلیل رد درخواست:</p>
                        <p id="rejectionReasonText" class="text-sm text-gray-700 leading-relaxed"></p>
                    </div>
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 mx-4 mt-3">
                        <p class="text-xs text-gray-500 leading-relaxed">
                            در صورت داشتن سوال می‌توانید با پشتیبانی تماس بگیرید.
                        </p>
                    </div>
                </div>
            </div>

            {{-- ===== فاز J: تبریک تایید + ادامه مراحل ===== --}}
            <div id="phaseJ" class="phase">
                <div class="text-center py-6">
                    <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h2 class="text-lg font-bold text-green-700 mb-2">احراز هویت تایید شد!</h2>
                    <p class="text-sm text-gray-500 mb-4">
                        <span class="font-bold text-brand-blue" id="approvedName"></span>
                        عزیز، خوش‌آمدید به خانواده تعمیر آنلاین.
                    </p>
                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 mx-4 mb-5 text-right">
                        <p class="text-sm text-gray-700 leading-relaxed">
                            برای تکمیل فرآیند همکاری، لازم است مراحل زیر را تکمیل نمایید:
                        </p>
                        <ul class="mt-3 space-y-2 text-xs text-gray-600">
                            <li class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-brand-blue text-white flex items-center justify-center text-[10px] font-bold shrink-0">۱</span>
                                مطالعه و امضای قرارداد همکاری
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-gray-300 text-white flex items-center justify-center text-[10px] font-bold shrink-0">۲</span>
                                آپلود مدارک
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-gray-300 text-white flex items-center justify-center text-[10px] font-bold shrink-0">۳</span>
                                احراز هویت ویدیویی
                            </li>
                        </ul>
                    </div>
                    <button onclick="loadContract()" class="w-full max-w-xs mx-auto py-3 bg-brand-blue hover:bg-brand-blue-light text-white text-sm font-bold rounded-xl transition-colors">
                        شروع مراحل
                    </button>
                </div>
            </div>

            {{-- ===== فاز K: قرارداد + امضا ===== --}}
            <div id="phaseK" class="phase">
                <div class="mb-4">
                    <h2 class="text-base font-bold text-gray-800">قرارداد همکاری</h2>
                    <p class="text-xs text-gray-400 mt-1">لطفاً متن قرارداد را با دقت مطالعه نمایید</p>
                </div>

                <div class="space-y-4">
                    {{-- متن قرارداد --}}
                    <div id="contractContent" class="bg-white border border-gray-200 rounded-xl p-4 max-h-[400px] overflow-y-auto text-sm text-gray-700 leading-relaxed prose prose-sm">
                        <p class="text-center text-gray-400">در حال بارگذاری قرارداد...</p>
                    </div>

                    {{-- تایید مطالعه --}}
                    <label class="flex items-start gap-3 cursor-pointer bg-gray-50 rounded-xl p-3">
                        <input type="checkbox" id="contractRead" class="mt-1 w-4 h-4 text-brand-blue border-gray-300 rounded focus:ring-brand-blue">
                        <span class="text-xs text-gray-600">متن قرارداد را به طور کامل مطالعه کردم و با تمامی مفاد آن موافقم.</span>
                    </label>

                    {{-- دکمه امضا --}}
                    <button id="btnOpenSignature" onclick="openSignaturePad()" disabled
                            class="w-full py-3 bg-brand-blue hover:bg-brand-blue-light text-white text-sm font-bold rounded-xl transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        امضای قرارداد
                    </button>

                    {{-- باکس امضا --}}
                    <div id="signatureSection" class="hidden">
                        <div class="border-2 border-dashed border-gray-300 rounded-xl p-2 bg-white">
                            <p class="text-xs text-gray-500 text-center mb-2">لطفاً در کادر زیر امضا کنید</p>
                            <canvas id="signatureCanvas" class="w-full border border-gray-200 rounded-lg bg-white" style="height: 200px; touch-action: none;"></canvas>
                            <div class="flex gap-2 mt-2">
                                <button type="button" onclick="clearSignature()" class="flex-1 py-2 bg-gray-100 text-gray-600 text-xs font-medium rounded-lg hover:bg-gray-200 transition-colors">
                                    پاک کردن
                                </button>
                                <button type="button" id="btnSendContractOtp" onclick="sendContractOtp()" class="flex-1 py-2 bg-green-600 text-white text-xs font-bold rounded-lg hover:bg-green-700 transition-colors">
                                    تایید و ارسال کد
                                </button>
                            </div>
                        </div>

                        {{-- بخش وارد کردن کد تایید --}}
                        <div id="contractOtpSection" class="hidden mt-3">
                            <div class="bg-blue-50 border border-blue-200 rounded-xl p-3">
                                <p class="text-xs text-gray-600 text-center mb-2">کد تایید به شماره موبایل شما ارسال شد</p>
                                <input type="text" id="contractOtpCode" maxlength="6" inputmode="numeric" pattern="[0-9]*"
                                       class="w-full text-center text-lg font-bold tracking-[0.5em] border border-gray-300 rounded-lg py-2 focus:border-brand-blue focus:ring-1 focus:ring-brand-blue"
                                       placeholder="------">
                                <button type="button" id="btnSubmitSignature" onclick="submitSignature()" class="w-full mt-2 py-2 bg-green-600 text-white text-sm font-bold rounded-lg hover:bg-green-700 transition-colors">
                                    تایید نهایی و ثبت قرارداد
                                </button>
                                <div class="flex items-center justify-between mt-2">
                                    <button type="button" id="btnResendContractOtp" onclick="sendContractOtp()" disabled class="text-xs text-brand-blue disabled:text-gray-400">
                                        ارسال مجدد کد
                                    </button>
                                    <span id="contractOtpTimer" class="text-xs text-gray-400"></span>
                                </div>
                            </div>
                        </div>

                        <p id="signatureError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>
                </div>
            </div>

            {{-- ===== فاز L: قرارداد امضا شد → ادامه به آپلود مدارک ===== --}}
            <div id="phaseL" class="phase">
                <div class="text-center py-6">
                    <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h2 class="text-lg font-bold text-green-700 mb-2">قرارداد با موفقیت امضا شد!</h2>
                    <p class="text-sm text-gray-500 mb-4">
                        برای تکمیل فرآیند، آپلود مدارک و احراز هویت ویدیویی باقی‌مانده است.
                    </p>
                    <button onclick="highestCompletedStep = Math.max(highestCompletedStep, 6); goToPhase('N')" class="w-full max-w-xs mx-auto py-3 bg-brand-blue hover:bg-brand-blue-light text-white text-sm font-bold rounded-xl transition-colors">
                        ادامه: آپلود مدارک
                    </button>
                </div>
            </div>

            {{-- ===== فاز N: آپلود مدارک ===== --}}
            <div id="phaseN" class="phase">
                <div class="mb-5">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <span class="text-xs text-green-600 font-bold">قرارداد امضا شد</span>
                    </div>
                    <h2 class="text-base font-bold text-gray-800">آپلود مدارک</h2>
                    <p class="text-xs text-gray-400 mt-1">هر تصویر بلافاصله پس از انتخاب آپلود می‌شود</p>
                </div>

                <div class="space-y-4">
                    {{-- کارت ملی (رو) --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1.5">تصویر روی کارت ملی</label>
                        <div class="doc-upload-box" data-field="national_card_front">
                            <input type="file" accept="image/*" class="hidden doc-file-input" id="file_national_card_front" onchange="previewDocImage(this, 'national_card_front')">
                            <div id="preview_national_card_front" class="hidden relative">
                                <img class="w-full rounded-xl" alt="پیش‌نمایش">
                                <button type="button" onclick="removeDocImage('national_card_front')" class="absolute top-2 left-2 w-7 h-7 bg-red-500 text-white rounded-full flex items-center justify-center text-xs hover:bg-red-600">✕</button>
                            </div>
                            <label for="file_national_card_front" id="placeholder_national_card_front" class="flex flex-col items-center justify-center border-2 border-dashed border-gray-200 rounded-xl p-6 cursor-pointer hover:border-blue-300 hover:bg-blue-50/30 transition-colors">
                                <svg class="w-8 h-8 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span class="text-xs text-gray-400">برای انتخاب تصویر کلیک کنید</span>
                            </label>
                        </div>
                        <p id="docError_national_card_front" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    {{-- کارت ملی (پشت) --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1.5">تصویر پشت کارت ملی</label>
                        <div class="doc-upload-box" data-field="national_card_back">
                            <input type="file" accept="image/*" class="hidden doc-file-input" id="file_national_card_back" onchange="previewDocImage(this, 'national_card_back')">
                            <div id="preview_national_card_back" class="hidden relative">
                                <img class="w-full rounded-xl" alt="پیش‌نمایش">
                                <button type="button" onclick="removeDocImage('national_card_back')" class="absolute top-2 left-2 w-7 h-7 bg-red-500 text-white rounded-full flex items-center justify-center text-xs hover:bg-red-600">✕</button>
                            </div>
                            <label for="file_national_card_back" id="placeholder_national_card_back" class="flex flex-col items-center justify-center border-2 border-dashed border-gray-200 rounded-xl p-6 cursor-pointer hover:border-blue-300 hover:bg-blue-50/30 transition-colors">
                                <svg class="w-8 h-8 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span class="text-xs text-gray-400">برای انتخاب تصویر کلیک کنید</span>
                            </label>
                        </div>
                        <p id="docError_national_card_back" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    {{-- شناسنامه صفحه اول --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1.5">صفحه اول شناسنامه</label>
                        <div class="doc-upload-box" data-field="birth_certificate_p1">
                            <input type="file" accept="image/*" class="hidden doc-file-input" id="file_birth_certificate_p1" onchange="previewDocImage(this, 'birth_certificate_p1')">
                            <div id="preview_birth_certificate_p1" class="hidden relative">
                                <img class="w-full rounded-xl" alt="پیش‌نمایش">
                                <button type="button" onclick="removeDocImage('birth_certificate_p1')" class="absolute top-2 left-2 w-7 h-7 bg-red-500 text-white rounded-full flex items-center justify-center text-xs hover:bg-red-600">✕</button>
                            </div>
                            <label for="file_birth_certificate_p1" id="placeholder_birth_certificate_p1" class="flex flex-col items-center justify-center border-2 border-dashed border-gray-200 rounded-xl p-6 cursor-pointer hover:border-blue-300 hover:bg-blue-50/30 transition-colors">
                                <svg class="w-8 h-8 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span class="text-xs text-gray-400">برای انتخاب تصویر کلیک کنید</span>
                            </label>
                        </div>
                        <p id="docError_birth_certificate_p1" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    {{-- شناسنامه صفحه دوم --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1.5">صفحه دوم شناسنامه</label>
                        <div class="doc-upload-box" data-field="birth_certificate_p2">
                            <input type="file" accept="image/*" class="hidden doc-file-input" id="file_birth_certificate_p2" onchange="previewDocImage(this, 'birth_certificate_p2')">
                            <div id="preview_birth_certificate_p2" class="hidden relative">
                                <img class="w-full rounded-xl" alt="پیش‌نمایش">
                                <button type="button" onclick="removeDocImage('birth_certificate_p2')" class="absolute top-2 left-2 w-7 h-7 bg-red-500 text-white rounded-full flex items-center justify-center text-xs hover:bg-red-600">✕</button>
                            </div>
                            <label for="file_birth_certificate_p2" id="placeholder_birth_certificate_p2" class="flex flex-col items-center justify-center border-2 border-dashed border-gray-200 rounded-xl p-6 cursor-pointer hover:border-blue-300 hover:bg-blue-50/30 transition-colors">
                                <svg class="w-8 h-8 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span class="text-xs text-gray-400">برای انتخاب تصویر کلیک کنید</span>
                            </label>
                        </div>
                        <p id="docError_birth_certificate_p2" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    {{-- گواهی سوپیشینه --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1.5">گواهی عدم سوء‌پیشینه</label>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-2">
                            <p class="text-xs text-gray-700 leading-relaxed text-justify">
                                برای آشنایی با نحوه دریافت گواهی عدم سوءپیشینه،
                                <a href="https://www.aparat.com/v/zedkgg4" target="_blank" class="text-blue-600 font-semibold hover:underline">ویدئو</a>
                                را مشاهده کنید.
                            </p>
                            <p class="text-xs text-gray-700 leading-relaxed text-justify mt-1">
                                پس از دریافت گواهی، لطفاً فایل PDF مربوط به آن را در قسمت زیر بارگذاری نمایید.
                            </p>
                        </div>
                        <div class="doc-upload-box" data-field="criminal_record">
                            <input type="file" accept="image/*,.pdf" class="hidden doc-file-input" id="file_criminal_record" onchange="previewDocImage(this, 'criminal_record')">
                            <div id="preview_criminal_record" class="hidden relative">
                                <img class="w-full rounded-xl" alt="پیش‌نمایش">
                                <button type="button" onclick="removeDocImage('criminal_record')" class="absolute top-2 left-2 w-7 h-7 bg-red-500 text-white rounded-full flex items-center justify-center text-xs hover:bg-red-600">✕</button>
                            </div>
                            <label for="file_criminal_record" id="placeholder_criminal_record" class="flex flex-col items-center justify-center border-2 border-dashed border-gray-200 rounded-xl p-6 cursor-pointer hover:border-blue-300 hover:bg-blue-50/30 transition-colors">
                                <svg class="w-8 h-8 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span class="text-xs text-gray-400">برای انتخاب تصویر کلیک کنید</span>
                            </label>
                        </div>
                        <p id="docError_criminal_record" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    {{-- عکس ۳×۴ --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1.5">عکس ۳×۴</label>
                        <div class="doc-upload-box" data-field="photo_3x4">
                            <input type="file" accept="image/*" class="hidden doc-file-input" id="file_photo_3x4" onchange="previewDocImage(this, 'photo_3x4')">
                            <div id="preview_photo_3x4" class="hidden relative">
                                <img class="w-full rounded-xl" alt="پیش‌نمایش">
                                <button type="button" onclick="removeDocImage('photo_3x4')" class="absolute top-2 left-2 w-7 h-7 bg-red-500 text-white rounded-full flex items-center justify-center text-xs hover:bg-red-600">✕</button>
                            </div>
                            <label for="file_photo_3x4" id="placeholder_photo_3x4" class="flex flex-col items-center justify-center border-2 border-dashed border-gray-200 rounded-xl p-6 cursor-pointer hover:border-blue-300 hover:bg-blue-50/30 transition-colors">
                                <svg class="w-8 h-8 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span class="text-xs text-gray-400">برای انتخاب تصویر کلیک کنید</span>
                            </label>
                        </div>
                        <p id="docError_photo_3x4" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    {{-- سند ملکی یا اجاره‌نامه --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1.5">تصویر سند ملکی یا اجاره‌نامه محل سکونت</label>
                        <div class="doc-upload-box" data-field="lease_agreement">
                            <input type="file" accept="image/*" class="hidden doc-file-input" id="file_lease_agreement" onchange="previewDocImage(this, 'lease_agreement')">
                            <div id="preview_lease_agreement" class="hidden relative">
                                <img class="w-full rounded-xl" alt="پیش‌نمایش">
                                <button type="button" onclick="removeDocImage('lease_agreement')" class="absolute top-2 left-2 w-7 h-7 bg-red-500 text-white rounded-full flex items-center justify-center text-xs hover:bg-red-600">✕</button>
                            </div>
                            <label for="file_lease_agreement" id="placeholder_lease_agreement" class="flex flex-col items-center justify-center border-2 border-dashed border-gray-200 rounded-xl p-6 cursor-pointer hover:border-blue-300 hover:bg-blue-50/30 transition-colors">
                                <svg class="w-8 h-8 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span class="text-xs text-gray-400">برای انتخاب تصویر کلیک کنید</span>
                            </label>
                        </div>
                        <p id="docError_lease_agreement" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    {{-- قبض آب یا برق --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1.5">قبض آب یا برق</label>
                        <div class="doc-upload-box" data-field="utility_bill">
                            <input type="file" accept="image/*" class="hidden doc-file-input" id="file_utility_bill" onchange="previewDocImage(this, 'utility_bill')">
                            <div id="preview_utility_bill" class="hidden relative">
                                <img class="w-full rounded-xl" alt="پیش‌نمایش">
                                <button type="button" onclick="removeDocImage('utility_bill')" class="absolute top-2 left-2 w-7 h-7 bg-red-500 text-white rounded-full flex items-center justify-center text-xs hover:bg-red-600">✕</button>
                            </div>
                            <label for="file_utility_bill" id="placeholder_utility_bill" class="flex flex-col items-center justify-center border-2 border-dashed border-gray-200 rounded-xl p-6 cursor-pointer hover:border-blue-300 hover:bg-blue-50/30 transition-colors">
                                <svg class="w-8 h-8 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span class="text-xs text-gray-400">برای انتخاب تصویر کلیک کنید</span>
                            </label>
                        </div>
                        <p id="docError_utility_bill" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    <p class="text-xs text-gray-400 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        فرمت‌های مجاز: JPG، PNG — حداکثر حجم هر فایل: ۵ مگابایت
                    </p>

                    <p id="docGeneralError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>

                    <button type="button" id="btnSubmitDocs" onclick="submitDocuments()" class="w-full py-3 bg-brand-blue hover:bg-brand-blue-light text-white text-sm font-bold rounded-xl transition-colors mt-2">
                        تایید و ادامه (0 از 8 آپلود شده)
                    </button>
                </div>
            </div>

            {{-- ===== فاز C2: احراز هویت ویدیویی ===== --}}
            <div id="phaseC2" class="phase">
                <div class="mb-5">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <span class="text-xs text-green-600 font-bold">مدارک آپلود شد</span>
                    </div>
                    <h2 class="text-base font-bold text-gray-800">احراز هویت ویدیویی</h2>
                    <p class="text-xs text-gray-400 mt-1">لطفاً متن زیر را با صدای بلند بخوانید و ویدیو سلفی ضبط کنید</p>
                </div>

                <div class="space-y-4">
                    {{-- متن خوانشی --}}
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                        <p class="text-xs text-blue-600 font-bold mb-2 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                            هنگام ضبط ویدیو، این متن را با صدای بلند بخوانید:
                        </p>
                        <p class="text-sm font-bold text-gray-800 leading-relaxed text-center py-2">
                            «اینجانب به عنوان تکنسین تعمیرآنلاین، صحت اطلاعات ارائه‌شده را تایید می‌کنم.»
                        </p>
                    </div>

                    {{-- سریال کارت ملی --}}
                    <div>
                        <label for="national_card_serial" class="block text-sm font-semibold text-gray-600 mb-1.5">سریال پشت کارت ملی</label>
                        <input type="text" id="national_card_serial" placeholder="مثال: 1A2345678B" maxlength="10" dir="ltr"
                               class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none placeholder:text-gray-300 text-left">
                        <p class="text-xs text-gray-400 mt-1">سریال ۱۰ رقمی پشت کارت ملی هوشمند</p>
                        <p id="cardSerialError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>
                    </div>

                    {{-- پیش‌نمایش دوربین --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1.5">ضبط ویدیو سلفی</label>
                        <div class="relative bg-black rounded-xl overflow-hidden" style="aspect-ratio: 4/3;">
                            <video id="biometricPreview" autoplay playsinline muted class="w-full h-full object-cover" style="transform: scaleX(-1);"></video>
                            <div id="biometricOverlay" class="absolute inset-0 flex items-center justify-center">
                                <div class="border-2 border-dashed border-white/40 rounded-full" style="width: 65%; height: 80%;"></div>
                            </div>
                            {{-- متن روی ویدیو هنگام ضبط --}}
                            <div id="biometricRecordingText" class="absolute bottom-0 left-0 right-0 bg-black/60 px-3 py-2 text-center hidden">
                                <p class="text-white text-[11px] font-bold leading-relaxed">اینجانب به عنوان تکنسین تعمیرآنلاین، صحت اطلاعات ارائه‌شده را تایید می‌کنم.</p>
                            </div>
                            <div id="biometricCountdown" class="absolute top-3 left-3 bg-red-600 text-white text-xs font-bold px-2 py-1 rounded-lg hidden">
                                <span id="biometricTimer">00:00</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 mt-2 text-xs text-gray-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>صورت خود را در کادر قرار دهید و متن بالا را با صدای بلند بخوانید.</span>
                        </div>
                    </div>

                    {{-- دکمه‌ها --}}
                    <div id="biometricActions">
                        <button type="button" id="btnStartRecord" onclick="startBiometricRecording()" class="w-full py-3 bg-red-600 hover:bg-red-700 text-white text-sm font-bold rounded-xl transition-colors flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/></svg>
                            شروع ضبط ویدیو
                        </button>
                        <button type="button" id="btnStopRecord" onclick="stopBiometricRecording()" class="hidden w-full py-3 bg-gray-700 hover:bg-gray-800 text-white text-sm font-bold rounded-xl transition-colors flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>
                            توقف ضبط
                        </button>
                        <button type="button" id="btnSubmitBiometric" onclick="submitBiometric()" class="hidden w-full py-3 bg-green-600 hover:bg-green-700 text-white text-sm font-bold rounded-xl transition-colors mt-2">
                            ارسال ویدیو
                        </button>
                        <button type="button" id="btnRetryBiometric" onclick="retryBiometricRecording()" class="hidden w-full py-3 bg-amber-500 hover:bg-amber-600 text-white text-sm font-bold rounded-xl transition-colors mt-2">
                            ضبط مجدد
                        </button>
                    </div>

                    <p id="biometricError" class="text-red-500 text-xs mt-1 mr-1 hidden"></p>

                    {{-- رد شده توسط اپراتور --}}
                    <div id="biometricRejectedBox" class="hidden">
                        <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-center">
                            <svg class="w-8 h-8 text-red-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                            <p class="text-sm font-bold text-red-700 mb-1">ویدیو احراز هویت رد شد</p>
                            <p id="biometricRejectReason" class="text-xs text-gray-600 mb-3"></p>
                            <button type="button" onclick="retryBiometricFull()" class="px-6 py-2 bg-red-600 text-white text-sm font-bold rounded-lg hover:bg-red-700 transition-colors">
                                ضبط مجدد ویدیو
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== فاز M: تکمیل نهایی ===== --}}
            <div id="phaseM" class="phase">
                <div class="text-center py-8">
                    <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h2 class="text-lg font-bold text-gray-800 mb-2">همه مراحل تکمیل شد!</h2>
                    <p class="text-sm text-gray-500 mb-3">
                        از همکاری شما سپاسگزاریم.
                    </p>
                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 mx-4 mt-4">
                        <svg class="w-8 h-8 text-blue-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-sm text-blue-700 font-bold mb-1">اطلاعات و ویدیو شما در حال بررسی است</p>
                        <p class="text-xs text-gray-600 leading-relaxed">
                            اطلاعات و ویدیوی احراز هویت شما تا <span class="font-bold text-blue-700">۴۸ ساعت آینده</span> بررسی و نتیجه از طریق پیامک به شما اعلام خواهد شد.
                        </p>
                    </div>
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
        let highestCompletedStep = 0; // بالاترین مرحله تکمیل شده
        let isApproved = false; // آیا توسط ادمین تایید شده
        let currentPhase = 'A'; // فاز فعلی
        const stepNumbers = ['۱','۲','۳','۴','۵','۶','۷','۸'];

        // نگاشت فاز به مرحله (۸ مرحله)
        // 1=هویت(A,B,C) | 2=شخصی(D) | 3=تکمیلی(E) | 4=مناطق(F) | 5=فعالیت(G,H) | 6=قرارداد(J,K,L) | 7=مدارک(N) | 8=ویدیو(C2)
        const phaseToStep = { A:1, B:1, C:1, D:2, E:3, F:4, G:5, H:5, I:5, Rejected:5, J:6, K:6, L:6, N:7, C2:8, M:8 };

        // نگاشت مرحله به اولین فاز آن (برای کلیک روی تب)
        const stepToPhase = { 1:'A', 2:'D', 3:'E', 4:'F', 5:'G', 6:'J', 7:'N', 8:'C2' };

        function goToPhase(phase) {
            $('.phase').removeClass('active');
            $('#phase' + phase).addClass('active');
            hideAlert();
            currentPhase = phase;

            // پراگرس بار
            const progress = { A:5, B:8, C:12, D:20, E:32, F:44, G:56, H:62, I:62, J:68, K:75, L:82, N:88, C2:94, M:100, Rejected:62 };
            $('#progressBar').css('width', (progress[phase] || 5) + '%');

            // به‌روزرسانی بالاترین مرحله تکمیل شده
            const step = phaseToStep[phase] || 1;
            if (step > highestCompletedStep + 1) {
                highestCompletedStep = step - 1;
            }

            // به‌روزرسانی نوار مراحل
            updateStepIndicators(phase);

            // اسکرول تب فعال به دید
            scrollStepIntoView(step);

            // وقتی فاز C نمایش داده شد، دیت‌پیکر رو init کن
            if (phase === 'C') {
                setTimeout(initDatePicker, 300);
            }

            // وقتی فاز D نمایش داده شد، دراپ‌داون استان رو پر کن
            if (phase === 'D') {
                initProvinceDropdown();
            }

            // وقتی فاز F نمایش داده شد، شهرهای استان تهران و البرز رو پر کن
            if (phase === 'F') {
                initCoverageAreaCities();
            }
        }

        function scrollStepIntoView(step) {
            const el = document.getElementById('stepIndicator' + step);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            }
        }

        function updateStepIndicators(phase) {
            const currentStep = phaseToStep[phase] || 1;

            for (let i = 1; i <= 8; i++) {
                const indicator = $('#stepIndicator' + i);
                const circle = indicator.find('.step-circle');
                const label = indicator.find('.step-label');
                const line = $('#stepLine' + i);

                if (i < currentStep) {
                    // تکمیل شده
                    circle.removeClass('bg-gray-200 text-gray-400 bg-brand-blue text-white').addClass('bg-green-500 text-white');
                    circle.html('<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>');
                    label.removeClass('text-gray-400 text-brand-blue font-medium').addClass('text-green-600 font-bold');
                    if (line.length) line.removeClass('bg-gray-200').addClass('bg-green-400');
                } else if (i === currentStep) {
                    // فعال
                    circle.removeClass('bg-gray-200 text-gray-400 bg-green-500').addClass('bg-brand-blue text-white');
                    circle.html(stepNumbers[i-1]);
                    label.removeClass('text-gray-400 text-green-600 font-medium').addClass('text-brand-blue font-bold');
                    if (line.length) line.removeClass('bg-green-400').addClass('bg-gray-200');
                } else {
                    // غیرفعال
                    circle.removeClass('bg-brand-blue text-white bg-green-500').addClass('bg-gray-200 text-gray-400');
                    circle.html(stepNumbers[i-1]);
                    label.removeClass('text-brand-blue text-green-600 font-bold').addClass('text-gray-400 font-medium');
                    if (line.length) line.removeClass('bg-green-400').addClass('bg-gray-200');
                }
            }
        }

        // کلیک روی تب‌های بالا
        function onStepClick(stepNum) {
            const currentStep = phaseToStep[currentPhase] || 1;

            // اگر تایید شده، نمی‌تواند به مراحل قبل از تایید (1-5) برگردد
            if (isApproved && stepNum <= 5) return;

            // فقط مراحل تکمیل شده یا مرحله فعلی قابل کلیک هستند
            if (stepNum > highestCompletedStep + 1) return;

            // اگر روی مرحله فعلی هستیم، کاری نکن
            if (stepNum === currentStep) return;

            // رفتن به فاز مربوطه
            const targetPhase = stepToPhase[stepNum];
            if (targetPhase) {
                goToPhase(targetPhase);
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
        function clearAllErrors() { hideFieldError('mobileError'); hideFieldError('otpError'); hideFieldError('nationalCodeError'); hideFieldError('birthDateError'); hideFieldError('shenasnameError'); hideFieldError('genderError'); hideFieldError('maritalError'); hideFieldError('provinceError'); hideFieldError('cityError'); hideFieldError('shopAddressError'); hideFieldError('shopPhoneError'); hideFieldError('step3GeneralError'); hideFieldError('tehranDistrictsError'); hideFieldError('step4GeneralError'); hideFieldError('activityTypeError'); hideFieldError('applianceCategoriesError'); hideFieldError('transportationError'); hideFieldError('step5GeneralError'); hideAlert(); }

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
                        // بررسی ثبت‌نام قبلی: هدایت به مرحله مناسب
                        if (res.resume && res.resume.current_step >= 2) {
                            verifiedFirstName = res.resume.first_name || '';
                            verifiedLastName = res.resume.last_name || '';
                            verifiedFatherName = res.resume.father_name || '';

                            $('#step2FirstName').val(verifiedFirstName);
                            $('#step2LastName').val(verifiedLastName);
                            $('#step2FatherName').val(verifiedFatherName);
                            $('#verifiedName').text(verifiedFirstName + ' ' + verifiedLastName);

                            // هدایت به مرحله مناسب
                            // ترتیب جدید: قرارداد → مدارک → بایومتریک → تکمیل
                            if (res.resume.status === 'approved' && res.resume.contract_signed && res.resume.documents_uploaded && res.resume.biometric_status === 'verified') {
                                isApproved = true;
                                highestCompletedStep = 8;
                                goToPhase('M');
                            } else if (res.resume.status === 'approved' && res.resume.contract_signed && res.resume.documents_uploaded) {
                                // مدارک آپلود شده، بایومتریک انجام نشده
                                isApproved = true;
                                highestCompletedStep = 7;
                                if (res.resume.biometric_status === 'pending') {
                                    // ویدیو ارسال شده، منتظر بررسی اپراتور
                                    highestCompletedStep = 8;
                                    goToPhase('M');
                                } else if (res.resume.biometric_status === 'rejected') {
                                    // ویدیو رد شده، اجازه ضبط مجدد
                                    goToPhase('C2');
                                    initBiometricCamera();
                                    $('#biometricRejectedBox').removeClass('hidden');
                                    $('#biometricRejectReason').text(res.resume.biometric_reject_reason || 'ویدیو رد شده است. لطفاً مجدداً ضبط کنید.');
                                    $('#biometricActions').addClass('hidden');
                                } else {
                                    goToPhase('C2');
                                    initBiometricCamera();
                                }
                            } else if (res.resume.status === 'approved' && res.resume.contract_signed) {
                                // قرارداد امضا شده ولی مدارک آپلود نشده
                                isApproved = true;
                                highestCompletedStep = 6;
                                goToPhase('N');
                            } else if (res.resume.status === 'approved') {
                                // تایید شده، قرارداد امضا نشده
                                isApproved = true;
                                highestCompletedStep = 5;
                                $('#approvedName').text(verifiedFirstName + ' ' + verifiedLastName);
                                goToPhase('J');
                            } else if (res.resume.status === 'rejected') {
                                $('#rejectedName').text(verifiedFirstName + ' ' + verifiedLastName);
                                if (res.resume.rejection_reason) {
                                    $('#rejectionReasonText').text(res.resume.rejection_reason);
                                    $('#rejectionReasonBox').removeClass('hidden');
                                }
                                goToPhase('Rejected');
                            } else if (res.resume.current_step >= 6) {
                                highestCompletedStep = 5;
                                goToPhase('I');
                            } else if (res.resume.current_step >= 5) {
                                highestCompletedStep = 4;
                                goToPhase('G');
                            } else if (res.resume.current_step >= 4) {
                                highestCompletedStep = 3;
                                goToPhase('F');
                            } else if (res.resume.current_step >= 3) {
                                highestCompletedStep = 2;
                                goToPhase('E');
                            } else {
                                // مرحله ۲ هنوز تکمیل نشده، فیلدها رو پر کن
                                if (res.resume.shenasname_number) {
                                    $('#shenasname_number').val(res.resume.shenasname_number);
                                }
                                if (res.resume.gender) {
                                    $('#gender').val(res.resume.gender);
                                }
                                if (res.resume.marital_status) {
                                    $('#marital_status').val(res.resume.marital_status);
                                    toggleChildrenCount();
                                    if (res.resume.marital_status === 'married' && res.resume.children_count !== null) {
                                        $('#children_count').val(res.resume.children_count);
                                    }
                                }
                                if (res.resume.province) {
                                    initProvinceDropdown();
                                    $('#province').val(res.resume.province).trigger('change');
                                    setTimeout(function() {
                                        if (res.resume.city) {
                                            $('#city').val(res.resume.city);
                                        }
                                    }, 100);
                                }
                                highestCompletedStep = 1;
                                goToPhase('D');
                            }
                        } else {
                            // کاربر جدید: برو مرحله احراز هویت
                            $('#mobileDisplay').val(currentMobile);
                            goToPhase('C');
                            initDatePicker();
                        }
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

                    highestCompletedStep = Math.max(highestCompletedStep, 1);
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

        // ===== فاز C2: احراز هویت ویدیویی =====
        let biometricStream = null;
        let biometricRecorder = null;
        let biometricChunks = [];
        let biometricBlob = null;
        let biometricRecordTimer = null;
        // ===== فاز N: آپلود مدارک =====
        const docFields = ['national_card_front', 'national_card_back', 'birth_certificate_p1', 'birth_certificate_p2', 'criminal_record', 'photo_3x4', 'lease_agreement', 'utility_bill'];

        // وضعیت آپلود هر فایل + صف آپلود
        const docUploadStatus = {};
        let uploadQueue = Promise.resolve();

        function previewDocImage(input, fieldName) {
            const file = input.files[0];
            if (!file) return;

            // بررسی نوع فایل
            if (!file.type.startsWith('image/')) {
                showFieldError('docError_' + fieldName, 'فقط فایل تصویری مجاز است.');
                input.value = '';
                return;
            }
            // بررسی حجم (5MB)
            if (file.size > 5 * 1024 * 1024) {
                showFieldError('docError_' + fieldName, 'حجم تصویر نباید بیشتر از ۵ مگابایت باشد.');
                input.value = '';
                return;
            }

            hideFieldError('docError_' + fieldName);

            // نمایش پیش‌نمایش
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = $('#preview_' + fieldName);
                preview.find('img').attr('src', e.target.result);
                preview.removeClass('hidden');
                $('#placeholder_' + fieldName).addClass('hidden');
            };
            reader.readAsDataURL(file);

            // آپلود فوری — صف‌بندی شده تا همزمان نشوند
            uploadQueue = uploadQueue.then(function() {
                return uploadSingleDoc(fieldName, file);
            });
        }

        function uploadSingleDoc(fieldName, file) {
            return new Promise(function(resolve) {
                const preview = $('#preview_' + fieldName);

                // نمایش وضعیت در حال آپلود
                preview.find('.doc-upload-status').remove();
                preview.append('<div class="doc-upload-status absolute bottom-0 left-0 right-0 bg-blue-500 text-white text-center text-xs py-1 rounded-b-xl">در حال آپلود...</div>');

                const formData = new FormData();
                formData.append('mobile', currentMobile);
                formData.append('field_name', fieldName);
                formData.append('file', file);

                $.ajax({
                    url: '{{ route("technician.register.upload-single-document") }}',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(res) {
                        if (res.success) {
                            docUploadStatus[fieldName] = true;
                            preview.find('.doc-upload-status').removeClass('bg-blue-500').addClass('bg-green-500').text('آپلود شد');
                            hideFieldError('docError_' + fieldName);
                        } else {
                            docUploadStatus[fieldName] = false;
                            preview.find('.doc-upload-status').removeClass('bg-blue-500').addClass('bg-red-500').text('خطا — دوباره انتخاب کنید');
                            showFieldError('docError_' + fieldName, res.message || 'خطا در آپلود');
                        }
                        updateDocProgress();
                        resolve();
                    },
                    error: function(xhr) {
                        docUploadStatus[fieldName] = false;
                        const res = xhr.responseJSON;
                        const msg = res?.errors?.file?.[0] || res?.message || 'خطا در آپلود فایل';
                        preview.find('.doc-upload-status').removeClass('bg-blue-500').addClass('bg-red-500').text('خطا — دوباره انتخاب کنید');
                        showFieldError('docError_' + fieldName, msg);
                        updateDocProgress();
                        resolve();
                    }
                });
            });
        }

        function updateDocProgress() {
            const uploaded = Object.values(docUploadStatus).filter(v => v === true).length;
            const total = docFields.length;
            const btn = $('#btnSubmitDocs');
            btn.text('تایید و ادامه (' + uploaded + ' از ' + total + ' آپلود شده)');
        }

        function removeDocImage(fieldName) {
            $('#file_' + fieldName).val('');
            $('#preview_' + fieldName).addClass('hidden').find('img').attr('src', '');
            $('#preview_' + fieldName).find('.doc-upload-status').remove();
            $('#placeholder_' + fieldName).removeClass('hidden');
            delete docUploadStatus[fieldName];
            updateDocProgress();
        }

        function submitDocuments() {
            hideFieldError('docGeneralError');
            docFields.forEach(f => hideFieldError('docError_' + f));

            // بررسی اینکه همه فایل‌ها آپلود شده‌اند
            const labels = {
                'national_card_front': 'تصویر روی کارت ملی',
                'national_card_back': 'تصویر پشت کارت ملی',
                'birth_certificate_p1': 'صفحه اول شناسنامه',
                'birth_certificate_p2': 'صفحه دوم شناسنامه',
                'criminal_record': 'گواهی عدم سوء‌پیشینه',
                'photo_3x4': 'عکس ۳×۴',
                'lease_agreement': 'تصویر سند ملکی یا اجاره‌نامه محل سکونت',
                'utility_bill': 'قبض آب یا برق'
            };

            let hasError = false;
            docFields.forEach(function(f) {
                if (!docUploadStatus[f]) {
                    showFieldError('docError_' + f, labels[f] + ' آپلود نشده است.');
                    hasError = true;
                }
            });

            if (hasError) {
                showFieldError('docGeneralError', 'لطفاً ابتدا همه مدارک را آپلود کنید.');
                return;
            }

            setLoading('btnSubmitDocs', true);

            // فقط بررسی سمت سرور که همه مدارک ثبت شده‌اند
            $.ajax({
                url: '{{ route("technician.register.upload-documents") }}',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: JSON.stringify({ mobile: currentMobile }),
                contentType: 'application/json',
                success: function(res) {
                    if (res.success) {
                        highestCompletedStep = 7;
                        goToPhase('C2');
                        initBiometricCamera();
                    } else {
                        showFieldError('docGeneralError', res.message || 'خطا در تایید مدارک');
                    }
                },
                error: function(xhr) {
                    const res = xhr.responseJSON;
                    showFieldError('docGeneralError', res?.message || 'خطا در تایید مدارک');
                },
                complete: function() {
                    setLoading('btnSubmitDocs', false);
                }
            });
        }

        // ===== فاز C2: بایومتریک =====
        let biometricRecordSeconds = 0;
        let biometricPollInterval = null;

        function initBiometricCamera() {
            if (biometricStream) return; // already initialized

            navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                audio: true
            })
            .then(function(stream) {
                biometricStream = stream;
                document.getElementById('biometricPreview').srcObject = stream;
            })
            .catch(function(err) {
                console.error('Camera error:', err);
                showFieldError('biometricError', 'دسترسی به دوربین امکان‌پذیر نیست. لطفاً مجوز دسترسی را فعال کنید.');
            });
        }

        function startBiometricRecording() {
            hideFieldError('biometricError');

            const serial = $('#national_card_serial').val().trim();
            if (!/^[0-9A-Za-z]{10}$/.test(serial)) {
                showFieldError('cardSerialError', 'سریال کارت ملی باید ۱۰ کاراکتر (عدد و حرف) باشد.');
                return;
            }
            hideFieldError('cardSerialError');

            if (!biometricStream) {
                showFieldError('biometricError', 'دوربین آماده نیست. لطفاً مجوز دسترسی را بررسی کنید.');
                return;
            }

            biometricChunks = [];
            biometricBlob = null;
            biometricRecordSeconds = 0;

            try {
                biometricRecorder = new MediaRecorder(biometricStream, {
                    mimeType: 'video/webm;codecs=vp8,opus',
                    videoBitsPerSecond: 500000 // ~500kbps to keep under 2MB for 10s
                });
            } catch (e) {
                try {
                    biometricRecorder = new MediaRecorder(biometricStream, {
                        videoBitsPerSecond: 500000
                    });
                } catch (e2) {
                    biometricRecorder = new MediaRecorder(biometricStream);
                }
            }

            biometricRecorder.ondataavailable = function(e) {
                if (e.data.size > 0) biometricChunks.push(e.data);
            };

            biometricRecorder.onstop = function() {
                biometricBlob = new Blob(biometricChunks, { type: biometricRecorder.mimeType || 'video/webm' });
                // نمایش دکمه‌ها
                $('#btnStopRecord').addClass('hidden');
                $('#btnSubmitBiometric').removeClass('hidden');
                $('#btnRetryBiometric').removeClass('hidden');
                $('#biometricCountdown').addClass('hidden');
                $('#biometricRecordingText').addClass('hidden');
                clearInterval(biometricRecordTimer);
            };

            biometricRecorder.start(100);

            // نمایش شمارنده و متن خوانشی روی ویدیو
            $('#btnStartRecord').addClass('hidden');
            $('#btnStopRecord').removeClass('hidden');
            $('#btnSubmitBiometric').addClass('hidden');
            $('#btnRetryBiometric').addClass('hidden');
            $('#biometricCountdown').removeClass('hidden');
            $('#biometricRecordingText').removeClass('hidden');

            biometricRecordTimer = setInterval(function() {
                biometricRecordSeconds++;
                let m = Math.floor(biometricRecordSeconds / 60);
                let s = biometricRecordSeconds % 60;
                $('#biometricTimer').text((m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s);

                // حداکثر ۱۰ ثانیه ضبط
                if (biometricRecordSeconds >= 10) {
                    stopBiometricRecording();
                }
            }, 1000);
        }

        function stopBiometricRecording() {
            if (biometricRecorder && biometricRecorder.state === 'recording') {
                biometricRecorder.stop();
            }
        }

        function retryBiometricRecording() {
            biometricBlob = null;
            biometricChunks = [];
            $('#btnStartRecord').removeClass('hidden');
            $('#btnStopRecord').addClass('hidden');
            $('#btnSubmitBiometric').addClass('hidden');
            $('#btnRetryBiometric').addClass('hidden');
            hideFieldError('biometricError');
        }

        function retryBiometricFull() {
            $('#biometricRejectedBox').addClass('hidden');
            $('#biometricActions').removeClass('hidden');
            retryBiometricRecording();
        }

        function submitBiometric() {
            hideFieldError('biometricError');

            if (!biometricBlob) {
                showFieldError('biometricError', 'لطفاً ابتدا ویدیو ضبط کنید.');
                return;
            }

            const serial = $('#national_card_serial').val().trim();

            const formData = new FormData();
            formData.append('mobile', currentMobile);
            formData.append('national_card_serial', serial);
            formData.append('video', biometricBlob, 'selfie.webm');

            setLoading('btnSubmitBiometric', true);

            $.ajax({
                url: '{{ route("technician.register.biometric") }}',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
            })
            .done(function(res) {
                if (res.success) {
                    stopBiometricCamera();
                    highestCompletedStep = 8;
                    goToPhase('M');
                } else {
                    showFieldError('biometricError', res.message);
                }
            })
            .fail(function(xhr) {
                const res = xhr.responseJSON;
                showFieldError('biometricError', res?.message || 'خطا در ارسال ویدیو.');
            })
            .always(function() {
                setLoading('btnSubmitBiometric', false);
            });
        }

        function showBiometricPending() {
            $('#biometricActions').addClass('hidden');
            $('#biometricPendingBox').removeClass('hidden');
            $('#biometricRejectedBox').addClass('hidden');
        }

        var biometricPollAttempts = 0;
        var biometricMaxPollAttempts = 60; // حداکثر ۵ دقیقه (60 × 5 ثانیه)
        var biometricPollErrors = 0;

        function startBiometricPolling() {
            if (biometricPollInterval) clearInterval(biometricPollInterval);
            biometricPollAttempts = 0;
            biometricPollErrors = 0;

            biometricPollInterval = setInterval(function() {
                biometricPollAttempts++;
                $('#biometricPollCount').text('بررسی ' + biometricPollAttempts + ' از ' + biometricMaxPollAttempts);

                if (biometricPollAttempts >= biometricMaxPollAttempts) {
                    clearInterval(biometricPollInterval);
                    $('#biometricPendingBox').addClass('hidden');
                    $('#biometricErrorDetail').text('پس از ' + biometricMaxPollAttempts + ' بار تلاش، نتیجه‌ای دریافت نشد. لطفاً مجدداً تلاش کنید.');
                    $('#biometricErrorBox').removeClass('hidden');
                    return;
                }

                $.post('{{ route("technician.register.biometric-status") }}', {
                    mobile: currentMobile
                })
                .done(function(res) {
                    biometricPollErrors = 0; // ریست شمارنده خطا

                    if (res.debug) {
                        $('#biometricPollDebug').text(res.debug).removeClass('hidden');
                    }

                    if (res.success) {
                        if (res.status === 'verified') {
                            clearInterval(biometricPollInterval);
                            stopBiometricCamera();
                            // رفتن به مرحله تکمیل نهایی (ویدیو آخرین مرحله است)
                            highestCompletedStep = 8;
                            goToPhase('M');
                        } else if (res.status === 'rejected') {
                            clearInterval(biometricPollInterval);
                            $('#biometricPendingBox').addClass('hidden');
                            $('#biometricErrorBox').addClass('hidden');
                            $('#biometricRejectedBox').removeClass('hidden');
                            $('#biometricRejectReason').text(res.reason || 'احراز هویت ناموفق بود.');
                            $('#biometricActions').removeClass('hidden');
                        }
                        // اگر pending باشه، ادامه polling
                    }
                })
                .fail(function(xhr) {
                    biometricPollErrors++;
                    console.error('Biometric poll error #' + biometricPollErrors, xhr.status, xhr.responseText);

                    if (biometricPollErrors >= 5) {
                        clearInterval(biometricPollInterval);
                        $('#biometricPendingBox').addClass('hidden');
                        $('#biometricErrorDetail').text('خطا در ارتباط با سرور (HTTP ' + xhr.status + '). لطفاً مجدداً تلاش کنید.');
                        $('#biometricErrorBox').removeClass('hidden');
                    }
                });
            }, 5000); // هر ۵ ثانیه
        }

        function stopBiometricCamera() {
            if (biometricStream) {
                biometricStream.getTracks().forEach(function(track) { track.stop(); });
                biometricStream = null;
            }
            if (biometricPollInterval) {
                clearInterval(biometricPollInterval);
            }
        }

        // ===== فاز D: مرحله ۲ - اطلاعات شخصی =====
        function toggleChildrenCount() {
            const wrapper = document.getElementById('childrenCountWrapper');
            if ($('#marital_status').val() === 'married') {
                wrapper.classList.remove('hidden');
            } else {
                wrapper.classList.add('hidden');
                $('#children_count').val('0');
            }
        }

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
            const gender = $('#gender').val();
            const maritalStatus = $('#marital_status').val();
            const childrenCount = maritalStatus === 'married' ? $('#children_count').val() : null;
            const province = $('#province').val();
            const city = $('#city').val();

            let hasError = false;

            if (!shenasname || !/^[0-9]{1,10}$/.test(shenasname)) {
                showFieldError('shenasnameError', 'شماره شناسنامه باید عددی باشد.');
                hasError = true;
            }

            if (!gender) {
                showFieldError('genderError', 'انتخاب جنسیت الزامی است.');
                hasError = true;
            }

            if (!maritalStatus) {
                showFieldError('maritalError', 'انتخاب وضعیت تاهل الزامی است.');
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
                gender: gender,
                marital_status: maritalStatus,
                children_count: childrenCount,
                province: province,
                city: city
            })
            .done(function(res) {
                if (res.success) {
                    highestCompletedStep = Math.max(highestCompletedStep, 2);
                    goToPhase('E');
                } else {
                    if (res.field) {
                        const errorMap = { shenasname_number: 'shenasnameError', gender: 'genderError', marital_status: 'maritalError', province: 'provinceError', city: 'cityError' };
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

        // ===== فاز E: مرحله ۳ - اطلاعات تکمیلی =====

        function toggleShopFields(show) {
            if (show) {
                $('#shopFields').removeClass('hidden');
            } else {
                $('#shopFields').addClass('hidden');
                $('#shop_address').val('');
                $('#shop_phone').val('');
            }
        }

        function toggleCooperationFields(show) {
            if (show) {
                $('#cooperationFields').removeClass('hidden');
            } else {
                $('#cooperationFields').addClass('hidden');
                $('#cooperation_companies').val('');
            }
        }

        let workExpCounter = 0;
        function addWorkExperience() {
            const idx = workExpCounter++;
            const html = `
                <div class="bg-gray-50 rounded-xl p-3 space-y-2 relative" id="workExp_${idx}">
                    <button type="button" onclick="$('#workExp_${idx}').remove()" class="absolute top-2 left-2 text-red-400 hover:text-red-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                    <input type="text" placeholder="عنوان شغل" class="work-exp-title form-input w-full px-3 py-2 rounded-lg text-sm bg-white outline-none placeholder:text-gray-300">
                    <input type="text" placeholder="محل کار / نام شرکت" class="work-exp-company form-input w-full px-3 py-2 rounded-lg text-sm bg-white outline-none placeholder:text-gray-300">
                    <input type="text" placeholder="مدت فعالیت (مثال: ۲ سال)" class="work-exp-duration form-input w-full px-3 py-2 rounded-lg text-sm bg-white outline-none placeholder:text-gray-300">
                </div>
            `;
            $('#workExperiencesList').append(html);
        }

        let certCounter = 0;
        function addCertificate() {
            const idx = certCounter++;
            const html = `
                <div class="bg-gray-50 rounded-xl p-3 space-y-2 relative" id="cert_${idx}">
                    <button type="button" onclick="$('#cert_${idx}').remove()" class="absolute top-2 left-2 text-red-400 hover:text-red-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                    <input type="text" placeholder="عنوان مدرک / دوره" class="cert-title form-input w-full px-3 py-2 rounded-lg text-sm bg-white outline-none placeholder:text-gray-300">
                    <input type="text" placeholder="نام موسسه / مرکز آموزشی" class="cert-institution form-input w-full px-3 py-2 rounded-lg text-sm bg-white outline-none placeholder:text-gray-300">
                </div>
            `;
            $('#certificatesList').append(html);
        }

        function collectWorkExperiences() {
            const items = [];
            $('#workExperiencesList > div').each(function() {
                const title = $(this).find('.work-exp-title').val().trim();
                const company = $(this).find('.work-exp-company').val().trim();
                const duration = $(this).find('.work-exp-duration').val().trim();
                if (title || company || duration) {
                    items.push({ title, company, duration });
                }
            });
            return items;
        }

        function collectCertificates() {
            const items = [];
            $('#certificatesList > div').each(function() {
                const title = $(this).find('.cert-title').val().trim();
                const institution = $(this).find('.cert-institution').val().trim();
                if (title || institution) {
                    items.push({ title, institution });
                }
            });
            return items;
        }

        function submitStep3() {
            clearAllErrors();
            hideFieldError('step3GeneralError');
            hideFieldError('educationLevelError');
            hideFieldError('workExperienceError');
            hideFieldError('shopAddressError');
            hideFieldError('shopPhoneError');

            const hasShop = $('input[name="has_shop"]:checked').val();
            const hasLicense = $('input[name="has_business_license"]:checked').val();
            const hasCooperation = $('input[name="has_cooperation"]:checked').val();

            let hasError = false;

            // بررسی مقطع تحصیلی (الزامی)
            if (!$('#education_level').val()) {
                showFieldError('educationLevelError', 'انتخاب مقطع تحصیلی الزامی است.');
                hasError = true;
            }

            // بررسی سوابق شغلی (حداقل یکی الزامی)
            const workExpsCheck = collectWorkExperiences();
            if (workExpsCheck.length === 0) {
                showFieldError('workExperienceError', 'حداقل یک سابقه شغلی الزامی است.');
                hasError = true;
            }

            // اگر مغازه دارد، آدرس و تلفن الزامی
            if (hasShop === '1') {
                if (!$('#shop_address').val().trim()) {
                    showFieldError('shopAddressError', 'آدرس مغازه/دفتر الزامی است.');
                    hasError = true;
                }
                if (!$('#shop_phone').val().trim()) {
                    showFieldError('shopPhoneError', 'تلفن مغازه/دفتر الزامی است.');
                    hasError = true;
                }
            }

            // اگر همکاری دارد، نام شرکت‌ها الزامی
            if (hasCooperation === '1') {
                if (!$('#cooperation_companies').val().trim()) {
                    showFieldError('cooperationError', 'لطفاً نام شرکت‌ها را وارد کنید.');
                    hasError = true;
                }
            }

            // بررسی سوابق شغلی (اگر اضافه شده، همه فیلدها پر باشد)
            const workExps = collectWorkExperiences();
            for (let i = 0; i < workExps.length; i++) {
                if (!workExps[i].title || !workExps[i].company || !workExps[i].duration) {
                    showFieldError('step3GeneralError', 'لطفاً تمام فیلدهای سوابق شغلی را تکمیل کنید.');
                    hasError = true;
                    break;
                }
            }

            // بررسی مدارک (اگر اضافه شده، همه فیلدها پر باشد)
            const certs = collectCertificates();
            for (let i = 0; i < certs.length; i++) {
                if (!certs[i].title || !certs[i].institution) {
                    showFieldError('step3GeneralError', 'لطفاً تمام فیلدهای مدارک و دوره‌ها را تکمیل کنید.');
                    hasError = true;
                    break;
                }
            }

            if (hasError) return;

            setLoading('btnStep3', true);

            $.post('{{ route("technician.register.step3") }}', {
                mobile: currentMobile,
                education_level: $('#education_level').val(),
                field_of_study: $('#field_of_study').val().trim(),
                has_business_license: hasLicense,
                has_shop: hasShop,
                shop_address: $('#shop_address').val().trim(),
                shop_phone: $('#shop_phone').val().trim(),
                has_cooperation: hasCooperation,
                cooperation_companies: hasCooperation === '1' ? $('#cooperation_companies').val().trim() : null,
                referrer_name: $('#referrer_name').val().trim(),
                referrer_phone: $('#referrer_phone').val().trim(),
                colleague1_name: $('#colleague1_name').val().trim(),
                colleague1_phone: $('#colleague1_phone').val().trim(),
                colleague2_name: $('#colleague2_name').val().trim(),
                colleague2_phone: $('#colleague2_phone').val().trim(),
                work_experiences: workExps.length ? workExps : null,
                certificates: certs.length ? certs : null
            })
            .done(function(res) {
                if (res.success) {
                    highestCompletedStep = Math.max(highestCompletedStep, 3);
                    goToPhase('F');
                } else {
                    showAlert(res.message, 'error');
                }
            })
            .fail(function(xhr) {
                const res = xhr.responseJSON;
                if (res?.message) {
                    showAlert(res.message, 'error');
                } else {
                    showAlert('خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.', 'error');
                }
            })
            .always(function() {
                setLoading('btnStep3', false);
            });
        }

        // ===== فاز F: مرحله ۴ - مناطق تحت پوشش =====

        let coverageCitiesReady = false;

        function initCoverageAreaCities() {
            if (coverageCitiesReady) return;

            // شهرهای استان تهران (بدون تهران)
            const tehranCities = (iranProvinces['تهران'] || []).filter(c => c !== 'تهران');
            const tehranGrid = $('#tehranCitiesGrid');
            tehranGrid.empty();
            tehranCities.forEach(function(city) {
                tehranGrid.append(`
                    <label class="cursor-pointer">
                        <input type="checkbox" name="tehran_province_city" value="${city}" class="hidden peer">
                        <div class="form-input text-center py-2 rounded-xl text-xs peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all">
                            ${city}
                        </div>
                    </label>
                `);
            });

            // شهرهای استان البرز
            const alborzCities = iranProvinces['البرز'] || [];
            const alborzGrid = $('#alborzCitiesGrid');
            alborzGrid.empty();
            alborzCities.forEach(function(city) {
                alborzGrid.append(`
                    <label class="cursor-pointer">
                        <input type="checkbox" name="alborz_city" value="${city}" class="hidden peer">
                        <div class="form-input text-center py-2 rounded-xl text-xs peer-checked:border-brand-blue peer-checked:bg-blue-50 peer-checked:text-brand-blue font-semibold transition-all">
                            ${city}
                        </div>
                    </label>
                `);
            });

            coverageCitiesReady = true;
        }

        function toggleTehranDistricts(show) {
            if (show) {
                $('#tehranDistrictsSection').slideDown(200);
            } else {
                $('#tehranDistrictsSection').slideUp(200);
                // پاک کردن تیک‌های مناطق
                $('.tehran-district-cb').prop('checked', false);
                $('#allTehranDistricts').prop('checked', false);
                hideFieldError('tehranDistrictsError');
            }
        }

        function toggleAllDistricts(el) {
            const checked = el.checked;
            $('.tehran-district-cb').each(function() {
                this.checked = checked;
            });
        }

        function checkAllDistrictsState() {
            const all = $('.tehran-district-cb').length;
            const checked = $('.tehran-district-cb:checked').length;
            $('#allTehranDistricts').prop('checked', all === checked);
        }

        function toggleTehranCities(show) {
            if (show) {
                $('#tehranCitiesGrid').removeClass('hidden');
            } else {
                $('#tehranCitiesGrid').addClass('hidden');
                $('input[name="tehran_province_city"]').prop('checked', false);
            }
        }

        function toggleAlborzCities(show) {
            if (show) {
                $('#alborzCitiesGrid').removeClass('hidden');
            } else {
                $('#alborzCitiesGrid').addClass('hidden');
                $('input[name="alborz_city"]').prop('checked', false);
            }
        }

        function toggleOtherProvinces(show) {
            if (show) {
                $('#otherProvincesField').removeClass('hidden');
            } else {
                $('#otherProvincesField').addClass('hidden');
                $('#other_provinces_cities').val('');
            }
        }

        function submitStep4() {
            clearAllErrors();
            hideFieldError('tehranDistrictsError');
            hideFieldError('tehranCitiesError');
            hideFieldError('alborzCitiesError');
            hideFieldError('otherProvincesError');
            hideFieldError('step4GeneralError');

            const servesTehran = $('input[name="serves_tehran"]:checked').val();
            const hasTehranCities = $('input[name="has_tehran_cities"]:checked').val();
            const hasAlborzCities = $('input[name="has_alborz_cities"]:checked').val();
            const hasOtherProvinces = $('input[name="has_other_provinces"]:checked').val();

            let hasError = false;

            // جمع‌آوری مناطق تهران
            const tehranDistricts = [];
            if (servesTehran === '1') {
                $('.tehran-district-cb:checked').each(function() {
                    tehranDistricts.push(parseInt($(this).val()));
                });

                if (tehranDistricts.length === 0) {
                    showFieldError('tehranDistrictsError', 'لطفاً حداقل یک منطقه تهران را انتخاب کنید.');
                    hasError = true;
                }
            }

            // جمع‌آوری شهرهای استان تهران
            const tehranProvinceCities = [];
            $('input[name="tehran_province_city"]:checked').each(function() {
                tehranProvinceCities.push($(this).val());
            });

            // اگر بله زده ولی شهری انتخاب نکرده
            if (hasTehranCities === '1' && tehranProvinceCities.length === 0) {
                showFieldError('tehranCitiesError', 'لطفاً حداقل یک شهر استان تهران را انتخاب کنید.');
                hasError = true;
            }

            // جمع‌آوری شهرهای البرز
            const alborzCities = [];
            $('input[name="alborz_city"]:checked').each(function() {
                alborzCities.push($(this).val());
            });

            // اگر بله زده ولی شهری انتخاب نکرده
            if (hasAlborzCities === '1' && alborzCities.length === 0) {
                showFieldError('alborzCitiesError', 'لطفاً حداقل یک شهر استان البرز را انتخاب کنید.');
                hasError = true;
            }

            // سایر استان‌ها
            const otherProvincesCities = $('#other_provinces_cities').val().trim();

            // اگر بله زده ولی چیزی ننوشته
            if (hasOtherProvinces === '1' && !otherProvincesCities) {
                showFieldError('otherProvincesError', 'لطفاً نام استان و شهرها را وارد کنید.');
                hasError = true;
            }

            // حداقل یکی از ۴ بخش باید «بله» باشد
            const hasAnyArea = servesTehran === '1' || hasTehranCities === '1' || hasAlborzCities === '1' || hasOtherProvinces === '1';
            if (!hasAnyArea) {
                showFieldError('step4GeneralError', 'لطفاً حداقل یک منطقه تحت پوشش را انتخاب کنید.');
                hasError = true;
            }

            if (hasError) return;

            setLoading('btnStep4', true);

            $.post('{{ route("technician.register.step4") }}', {
                mobile: currentMobile,
                serves_tehran: servesTehran || '0',
                tehran_districts: tehranDistricts.length ? tehranDistricts : null,
                tehran_province_cities: tehranProvinceCities.length ? tehranProvinceCities : null,
                alborz_cities: alborzCities.length ? alborzCities : null,
                other_provinces_cities: otherProvincesCities || null
            })
            .done(function(res) {
                if (res.success) {
                    highestCompletedStep = Math.max(highestCompletedStep, 4);
                    goToPhase('G');
                } else {
                    showFieldError('step4GeneralError', res.message);
                }
            })
            .fail(function(xhr) {
                const res = xhr.responseJSON;
                if (res?.message) {
                    showFieldError('step4GeneralError', res.message);
                } else {
                    showAlert('خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.', 'error');
                }
            })
            .always(function() {
                setLoading('btnStep4', false);
            });
        }

        // ===== فاز G: مرحله ۵ - زمینه فعالیت =====

        // داده‌های موقت مرحله ۵
        let step5Data = {};

        function goToAgreement() {
            clearAllErrors();

            const activityType = $('input[name="activity_type"]:checked').val();
            const transportationMethod = $('input[name="transportation_method"]:checked').val();

            let hasError = false;

            if (!activityType) {
                showFieldError('activityTypeError', 'لطفاً زمینه فعالیت خود را انتخاب کنید.');
                hasError = true;
            }

            const applianceCategories = [];
            $('.appliance-cat-cb:checked').each(function() {
                applianceCategories.push(parseInt($(this).val()));
            });

            if (applianceCategories.length === 0) {
                showFieldError('applianceCategoriesError', 'لطفاً حداقل یک دستگاه را انتخاب کنید.');
                hasError = true;
            }

            if (!transportationMethod) {
                showFieldError('transportationError', 'لطفاً نحوه ارائه خدمات را انتخاب کنید.');
                hasError = true;
            }

            const repairSkill = $('input[name="repair_skill"]:checked').val();
            if (!repairSkill) {
                showFieldError('repairSkillError', 'لطفاً نوع تعمیرات خود را مشخص کنید.');
                hasError = true;
            }

            if (hasError) return;

            // ذخیره داده‌ها برای ارسال بعدی
            step5Data = {
                mobile: currentMobile,
                activity_type: activityType,
                appliance_categories: applianceCategories,
                transportation_method: transportationMethod,
                repair_skill: repairSkill,
                board_repair_experience: $('#board_repair_experience').val(),
                additional_notes: $('#additional_notes').val().trim()
            };

            goToPhase('H');
        }

        function submitStep5() {
            clearAllErrors();

            const agreement = $('input[name="agreement"]:checked').val();
            if (!agreement) {
                showFieldError('agreementError', 'لطفاً یکی از گزینه‌ها را انتخاب کنید.');
                return;
            }
            if (agreement === 'no') {
                showFieldError('agreementError', 'برای تکمیل ثبت‌نام، موافقت با شرایط همکاری الزامی است.');
                return;
            }

            step5Data.agreement = 'yes';

            setLoading('btnStep5', true);

            $.post('{{ route("technician.register.step5") }}', step5Data)
            .done(function(res) {
                if (res.success) {
                    highestCompletedStep = 5;
                    goToPhase('I');
                } else {
                    showFieldError('step5GeneralError', res.message);
                }
            })
            .fail(function(xhr) {
                const res = xhr.responseJSON;
                if (res?.message) {
                    showFieldError('step5GeneralError', res.message);
                } else {
                    showAlert('خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.', 'error');
                }
            })
            .always(function() {
                setLoading('btnStep5', false);
            });
        }

        // ===== فاز J-K-L: قرارداد و امضا =====

        function loadContract() {
            $.post('{{ route("technician.register.get-contract") }}', {
                mobile: currentMobile
            })
            .done(function(res) {
                if (res.success) {
                    $('#contractContent').html(res.contract || '<p class="text-center text-gray-400">متن قرارداد هنوز تنظیم نشده است.</p>');
                    highestCompletedStep = Math.max(highestCompletedStep, 5);
                    goToPhase('K');
                } else {
                    showAlert(res.message, 'error');
                }
            })
            .fail(function(xhr) {
                var msg = 'خطا در بارگذاری قرارداد.';
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.message) msg = data.message;
                } catch(e) {}
                showAlert(msg, 'error');
            });
        }

        // فعال کردن دکمه امضا بعد از تیک زدن
        $(document).on('change', '#contractRead', function() {
            $('#btnOpenSignature').prop('disabled', !this.checked);
        });

        let signaturePadCtx = null;
        let isDrawing = false;
        let signatureEmpty = true;

        function openSignaturePad() {
            $('#signatureSection').removeClass('hidden');
            initSignatureCanvas();
            // اسکرول به باکس امضا
            document.getElementById('signatureSection').scrollIntoView({ behavior: 'smooth' });
        }

        function initSignatureCanvas() {
            const canvas = document.getElementById('signatureCanvas');
            canvas.width = canvas.offsetWidth;
            canvas.height = 200;
            signaturePadCtx = canvas.getContext('2d');
            signaturePadCtx.strokeStyle = '#1a1a1a';
            signaturePadCtx.lineWidth = 2;
            signaturePadCtx.lineCap = 'round';
            signaturePadCtx.lineJoin = 'round';
            signatureEmpty = true;

            // Mouse events
            canvas.addEventListener('mousedown', startDraw);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('mouseup', stopDraw);
            canvas.addEventListener('mouseleave', stopDraw);

            // Touch events
            canvas.addEventListener('touchstart', function(e) { e.preventDefault(); startDraw(e.touches[0]); });
            canvas.addEventListener('touchmove', function(e) { e.preventDefault(); draw(e.touches[0]); });
            canvas.addEventListener('touchend', function(e) { e.preventDefault(); stopDraw(); });
        }

        function startDraw(e) {
            isDrawing = true;
            const canvas = document.getElementById('signatureCanvas');
            const rect = canvas.getBoundingClientRect();
            signaturePadCtx.beginPath();
            signaturePadCtx.moveTo(e.clientX - rect.left, e.clientY - rect.top);
        }

        function draw(e) {
            if (!isDrawing) return;
            const canvas = document.getElementById('signatureCanvas');
            const rect = canvas.getBoundingClientRect();
            signaturePadCtx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
            signaturePadCtx.stroke();
            signatureEmpty = false;
        }

        function stopDraw() {
            isDrawing = false;
        }

        function clearSignature() {
            const canvas = document.getElementById('signatureCanvas');
            signaturePadCtx.clearRect(0, 0, canvas.width, canvas.height);
            signatureEmpty = true;
        }

        let contractOtpInterval = null;

        function sendContractOtp() {
            hideFieldError('signatureError');

            if (signatureEmpty) {
                showFieldError('signatureError', 'لطفاً امضای خود را در کادر بالا ثبت کنید.');
                return;
            }

            setLoading('btnSendContractOtp', true);

            $.post('{{ route("technician.register.send-contract-otp") }}', {
                mobile: currentMobile
            })
            .done(function(res) {
                if (res.success) {
                    $('#contractOtpSection').removeClass('hidden');
                    $('#contractOtpCode').val('').focus();
                    startContractOtpTimer(res.expires_in || 120);
                } else {
                    showFieldError('signatureError', res.message);
                }
            })
            .fail(function(xhr) {
                const res = xhr.responseJSON;
                showFieldError('signatureError', res?.message || 'خطا در ارسال کد تایید.');
            })
            .always(function() {
                setLoading('btnSendContractOtp', false);
            });
        }

        function startContractOtpTimer(seconds) {
            let remaining = seconds;
            $('#btnResendContractOtp').prop('disabled', true);
            if (contractOtpInterval) clearInterval(contractOtpInterval);

            contractOtpInterval = setInterval(function() {
                remaining--;
                let m = Math.floor(remaining / 60);
                let s = remaining % 60;
                $('#contractOtpTimer').text((m > 0 ? m + ':' : '') + (s < 10 ? '0' : '') + s);

                if (remaining <= 0) {
                    clearInterval(contractOtpInterval);
                    $('#contractOtpTimer').text('');
                    $('#btnResendContractOtp').prop('disabled', false);
                }
            }, 1000);
        }

        function submitSignature() {
            hideFieldError('signatureError');

            const code = $('#contractOtpCode').val().trim();
            if (!code || code.length !== 6) {
                showFieldError('signatureError', 'لطفاً کد تایید ۶ رقمی را وارد کنید.');
                return;
            }

            if (signatureEmpty) {
                showFieldError('signatureError', 'لطفاً امضای خود را در کادر بالا ثبت کنید.');
                return;
            }

            const canvas = document.getElementById('signatureCanvas');
            const signatureData = canvas.toDataURL('image/png');

            setLoading('btnSubmitSignature', true);

            $.post('{{ route("technician.register.sign-contract") }}', {
                mobile: currentMobile,
                signature: signatureData,
                code: code
            })
            .done(function(res) {
                if (res.success) {
                    if (contractOtpInterval) clearInterval(contractOtpInterval);
                    highestCompletedStep = 6;
                    goToPhase('L');
                } else {
                    showFieldError('signatureError', res.message);
                }
            })
            .fail(function(xhr) {
                const res = xhr.responseJSON;
                showFieldError('signatureError', res?.message || 'خطا در ارسال امضا.');
            })
            .always(function() {
                setLoading('btnSubmitSignature', false);
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
