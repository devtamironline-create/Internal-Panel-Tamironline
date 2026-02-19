<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ثبت‌نام تکنسین | {{ $brand_name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="/css/fonts.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
    <script src="https://unpkg.com/persian-date@1.1.0/dist/persian-date.min.js"></script>
    <script src="https://unpkg.com/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
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

        body {
            background: #f0f2f5;
            min-height: 100vh;
        }

        /* اینپوت */
        .form-input {
            border: 1.5px solid #e0e0e0;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-input:focus {
            border-color: #1a5276;
            box-shadow: 0 0 0 3px rgba(26, 82, 118, 0.08);
        }
        .form-input.has-error {
            border-color: #e74c3c;
        }

        /* دیت‌پیکر */
        .datepicker-plot-area { font-family: 'Vazirmatn', Tahoma, sans-serif !important; }
        .datepicker-container { z-index: 9999 !important; border-radius: 12px !important; overflow: hidden; box-shadow: 0 8px 30px rgba(0,0,0,0.12) !important; border: 1px solid #e0e0e0 !important; }
        .datepicker-plot-area .datepicker-header { background: #1a5276 !important; }
        .datepicker-plot-area .datepicker-header .btn { color: #fff !important; }
        .datepicker-plot-area .table-days td.selected span { background: #1a5276 !important; }
        .datepicker-plot-area .table-days td.today span { color: #f0b929 !important; font-weight: 700; }
    </style>
</head>
<body dir="rtl">

    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-6">
        <div class="w-full max-w-[420px]">

            {{-- ===== کارت اصلی ===== --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

                {{-- هدر کارت: لوگو + عنوان --}}
                <div class="px-6 pt-6 pb-4 text-center border-b border-gray-50">
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
                        {{-- مرحله ۱ - فعال --}}
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full bg-brand-blue text-white flex items-center justify-center text-xs font-bold">۱</div>
                            <span class="text-xs font-bold text-brand-blue hidden sm:inline">اطلاعات پایه</span>
                        </div>
                        <div class="flex-1 mx-3 h-px bg-gray-200"></div>
                        {{-- مرحله ۲ --}}
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full bg-gray-200 text-gray-400 flex items-center justify-center text-xs font-bold">۲</div>
                            <span class="text-xs font-medium text-gray-400 hidden sm:inline">تخصص</span>
                        </div>
                        <div class="flex-1 mx-3 h-px bg-gray-200"></div>
                        {{-- مرحله ۳ --}}
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full bg-gray-200 text-gray-400 flex items-center justify-center text-xs font-bold">۳</div>
                            <span class="text-xs font-medium text-gray-400 hidden sm:inline">مدارک</span>
                        </div>
                    </div>
                    {{-- پراگرس بار --}}
                    <div class="w-full bg-gray-200 rounded-full h-1 mt-3">
                        <div class="h-full rounded-full bg-brand-blue transition-all" style="width: 33%"></div>
                    </div>
                </div>

                {{-- فرم --}}
                <form method="POST" action="{{ route('technician.register.step1') }}" class="px-6 py-5 space-y-4" id="step1Form">
                    @csrf

                    {{-- پیام موفقیت --}}
                    @if(session('success'))
                        <div class="flex items-center gap-2.5 p-3 rounded-xl bg-green-50 border border-green-100">
                            <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <p class="text-green-700 text-sm">{{ session('success') }}</p>
                        </div>
                    @endif

                    {{-- شماره موبایل --}}
                    <div>
                        <label for="mobile" class="block text-sm font-semibold text-gray-600 mb-1.5">شماره موبایل</label>
                        <input
                            type="tel"
                            id="mobile"
                            name="mobile"
                            value="{{ old('mobile') }}"
                            placeholder="09123456789"
                            maxlength="11"
                            inputmode="numeric"
                            dir="ltr"
                            class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none placeholder:text-gray-300 text-left @error('mobile') has-error @enderror"
                        >
                        @error('mobile')
                            <p class="text-red-500 text-xs mt-1 mr-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- کد ملی --}}
                    <div>
                        <label for="national_code" class="block text-sm font-semibold text-gray-600 mb-1.5">کد ملی</label>
                        <input
                            type="text"
                            id="national_code"
                            name="national_code"
                            value="{{ old('national_code') }}"
                            placeholder="کد ملی ۱۰ رقمی"
                            maxlength="10"
                            inputmode="numeric"
                            dir="ltr"
                            class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none placeholder:text-gray-300 text-left @error('national_code') has-error @enderror"
                        >
                        @error('national_code')
                            <p class="text-red-500 text-xs mt-1 mr-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- تاریخ تولد --}}
                    <div>
                        <label for="birth_date" class="block text-sm font-semibold text-gray-600 mb-1.5">تاریخ تولد</label>
                        <input
                            type="text"
                            id="birth_date"
                            name="birth_date"
                            value="{{ old('birth_date') }}"
                            placeholder="انتخاب تاریخ"
                            readonly
                            class="form-input w-full px-4 py-3 rounded-xl text-sm bg-white outline-none placeholder:text-gray-300 cursor-pointer @error('birth_date') has-error @enderror"
                        >
                        @error('birth_date')
                            <p class="text-red-500 text-xs mt-1 mr-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- دکمه --}}
                    <button type="submit"
                            class="w-full py-3 mt-2 bg-brand-blue hover:bg-brand-blue-light text-white text-sm font-bold rounded-xl transition-colors">
                        مرحله بعد
                        <svg class="w-4 h-4 inline-block mr-1 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </button>
                </form>

                {{-- فوتر کارت --}}
                <div class="px-6 py-3 border-t border-gray-50 text-center">
                    <a href="{{ route('technician.landing') }}" class="text-xs text-gray-400 hover:text-gray-500 transition-colors">
                        بازگشت به صفحه جذب تکنسین
                    </a>
                </div>

            </div>

            {{-- نوشته پایین --}}
            <p class="text-center text-gray-400 text-[11px] mt-4">
                اطلاعات شما محفوظ است و صرفاً جهت فرآیند همکاری استفاده می‌شود.
            </p>

        </div>
    </div>

    <script>
        $(document).ready(function () {
            $("#birth_date").pDatepicker({
                format: 'YYYY/MM/DD',
                autoClose: true,
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
                maxDate: new persianDate().subtract('year', 18).endOf('year').unix() * 1000,
                minDate: new persianDate().subtract('year', 80).startOf('year').unix() * 1000,
                responsive: true,
            });

            $('#mobile, #national_code').on('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '');
            });

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
