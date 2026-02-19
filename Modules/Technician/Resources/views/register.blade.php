<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ثبت‌نام تکنسین | {{ $brand_name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="/css/fonts.css" rel="stylesheet">
    {{-- Persian Date Picker --}}
    <link rel="stylesheet" href="https://unpkg.com/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
    <script src="https://unpkg.com/persian-date@1.1.0/dist/persian-date.min.js"></script>
    <script src="https://unpkg.com/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        * { font-family: 'Vazirmatn', Tahoma, sans-serif; }

        /* پس‌زمینه گرادیانت */
        .bg-main {
            background: linear-gradient(135deg, #1e3a5f 0%, #0f4c81 40%, #1a6b4a 100%);
            min-height: 100vh;
        }

        /* انیمیشن ورود */
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-slide-up {
            animation: slideUp 0.5s ease-out forwards;
        }

        /* استایل اینپوت‌ها */
        .input-field {
            transition: all 0.2s ease;
            border: 2px solid #e5e7eb;
        }
        .input-field:focus {
            border-color: #0f4c81;
            box-shadow: 0 0 0 3px rgba(15, 76, 129, 0.1);
        }
        .input-field.has-error {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        /* استپ اندیکاتور */
        .step-indicator {
            transition: all 0.3s ease;
        }

        /* Persian Datepicker Overrides */
        .datepicker-plot-area { font-family: 'Vazirmatn', Tahoma, sans-serif !important; }
        .datepicker-container { z-index: 9999 !important; border-radius: 16px !important; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.2) !important; }
        .datepicker-plot-area .datepicker-header { background: linear-gradient(135deg, #0f4c81, #1a6b4a) !important; }
        .datepicker-plot-area .datepicker-header .btn { color: #fff !important; }
        .datepicker-plot-area .table-days td.selected span { background: #0f4c81 !important; }
        .datepicker-plot-area .table-days td.today span { color: #0f4c81 !important; }
    </style>
</head>
<body class="bg-main" dir="rtl">

    {{-- ===== MOBILE BOX CONTAINER ===== --}}
    <div class="min-h-screen flex items-center justify-center px-4 py-8">
        <div class="w-full max-w-[420px] animate-slide-up">

            {{-- لوگو / برند --}}
            <div class="text-center mb-6">
                @if($brand_logo)
                    <img src="{{ asset('storage/' . $brand_logo) }}" alt="{{ $brand_name }}" class="h-14 mx-auto mb-3">
                @else
                    <h2 class="text-2xl font-black text-white">{{ $brand_name }}</h2>
                @endif
                <p class="text-blue-200 text-sm mt-1">فرم ثبت‌نام تکنسین</p>
            </div>

            {{-- کارت اصلی --}}
            <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">

                {{-- هدر استپ‌ها --}}
                <div class="bg-gray-50 px-6 pt-5 pb-4 border-b border-gray-100">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-gray-400">مرحله ۱ از ۳</span>
                        <span class="text-xs font-bold text-blue-600">اطلاعات پایه</span>
                    </div>
                    {{-- Progress Bar --}}
                    <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500" style="width: 33%; background: linear-gradient(90deg, #0f4c81, #1a6b4a);"></div>
                    </div>
                    {{-- Step Indicators --}}
                    <div class="flex items-center justify-between mt-4">
                        <div class="flex flex-col items-center gap-1">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-black text-white shadow-md" style="background: linear-gradient(135deg, #0f4c81, #1a6b4a);">
                                ۱
                            </div>
                            <span class="text-[10px] font-bold text-blue-800">اطلاعات پایه</span>
                        </div>
                        <div class="flex-1 h-0.5 bg-gray-200 mx-2 rounded"></div>
                        <div class="flex flex-col items-center gap-1">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold bg-gray-200 text-gray-400">
                                ۲
                            </div>
                            <span class="text-[10px] font-medium text-gray-400">تخصص</span>
                        </div>
                        <div class="flex-1 h-0.5 bg-gray-200 mx-2 rounded"></div>
                        <div class="flex flex-col items-center gap-1">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold bg-gray-200 text-gray-400">
                                ۳
                            </div>
                            <span class="text-[10px] font-medium text-gray-400">مدارک</span>
                        </div>
                    </div>
                </div>

                {{-- فرم مرحله ۱ --}}
                <form method="POST" action="{{ route('technician.register.step1') }}" class="px-6 py-6 space-y-5" id="step1Form">
                    @csrf

                    {{-- پیام موفقیت --}}
                    @if(session('success'))
                        <div class="bg-green-50 border border-green-200 rounded-2xl p-4 flex items-center gap-3">
                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <p class="text-green-800 text-sm font-medium">{{ session('success') }}</p>
                        </div>
                    @endif

                    {{-- شماره موبایل --}}
                    <div>
                        <label for="mobile" class="block text-sm font-bold text-gray-700 mb-2">
                            شماره موبایل
                        </label>
                        <div class="relative">
                            <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            </div>
                            <input
                                type="tel"
                                id="mobile"
                                name="mobile"
                                value="{{ old('mobile') }}"
                                placeholder="09123456789"
                                maxlength="11"
                                inputmode="numeric"
                                dir="ltr"
                                class="input-field w-full pr-11 pl-4 py-3.5 rounded-2xl text-base bg-white outline-none placeholder:text-gray-300 text-left @error('mobile') has-error @enderror"
                            >
                        </div>
                        @error('mobile')
                            <p class="text-red-500 text-xs font-medium mt-1.5 mr-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- کد ملی --}}
                    <div>
                        <label for="national_code" class="block text-sm font-bold text-gray-700 mb-2">
                            کد ملی
                        </label>
                        <div class="relative">
                            <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
                            </div>
                            <input
                                type="text"
                                id="national_code"
                                name="national_code"
                                value="{{ old('national_code') }}"
                                placeholder="۱۰ رقم کد ملی"
                                maxlength="10"
                                inputmode="numeric"
                                dir="ltr"
                                class="input-field w-full pr-11 pl-4 py-3.5 rounded-2xl text-base bg-white outline-none placeholder:text-gray-300 text-left @error('national_code') has-error @enderror"
                            >
                        </div>
                        @error('national_code')
                            <p class="text-red-500 text-xs font-medium mt-1.5 mr-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- تاریخ تولد --}}
                    <div>
                        <label for="birth_date" class="block text-sm font-bold text-gray-700 mb-2">
                            تاریخ تولد
                        </label>
                        <div class="relative">
                            <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <input
                                type="text"
                                id="birth_date"
                                name="birth_date"
                                value="{{ old('birth_date') }}"
                                placeholder="انتخاب تاریخ تولد"
                                readonly
                                class="input-field w-full pr-11 pl-4 py-3.5 rounded-2xl text-base bg-white outline-none placeholder:text-gray-300 cursor-pointer @error('birth_date') has-error @enderror"
                            >
                        </div>
                        @error('birth_date')
                            <p class="text-red-500 text-xs font-medium mt-1.5 mr-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- دکمه ادامه --}}
                    <div class="pt-2">
                        <button type="submit"
                                class="w-full py-4 text-white font-bold text-base rounded-2xl transition-all duration-200 shadow-lg hover:shadow-xl hover:-translate-y-0.5 active:translate-y-0"
                                style="background: linear-gradient(135deg, #0f4c81, #1a6b4a);">
                            ادامه
                            <svg class="w-5 h-5 inline-block mr-1 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </button>
                    </div>

                    {{-- لینک بازگشت --}}
                    <div class="text-center pt-1">
                        <a href="{{ route('technician.landing') }}" class="text-sm text-gray-400 hover:text-gray-600 transition-colors">
                            بازگشت به صفحه اصلی
                        </a>
                    </div>
                </form>

            </div>

            {{-- متن پایین کارت --}}
            <p class="text-center text-blue-200/60 text-xs mt-5">
                اطلاعات شما نزد ما محفوظ است و صرفاً جهت فرآیند همکاری استفاده می‌شود.
            </p>

        </div>
    </div>

    <script>
        $(document).ready(function () {
            // دیت‌پیکر شمسی
            $("#birth_date").pDatepicker({
                format: 'YYYY/MM/DD',
                autoClose: true,
                initialValue: false,
                observer: true,
                calendar: {
                    persian: {
                        locale: 'fa'
                    }
                },
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
                maxDate: new persianDate().subtract('year', 18).endOf('year').unix() * 1000,
                minDate: new persianDate().subtract('year', 80).startOf('year').unix() * 1000,
                responsive: true,
            });

            // فقط عدد در فیلد موبایل و کدملی
            $('#mobile, #national_code').on('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '');
            });

            // تبدیل اعداد فارسی/عربی به انگلیسی در هنگام سابمیت
            $('#step1Form').on('submit', function () {
                var birthDate = $('#birth_date').val();
                if (birthDate) {
                    birthDate = birthDate
                        .replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); })
                        .replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); });
                    $('#birth_date').val(birthDate);
                }
            });
        });
    </script>

</body>
</html>
