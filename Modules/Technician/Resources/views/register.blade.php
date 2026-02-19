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
        .form-input:disabled { background: #f9fafb; color: #9ca3af; }

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
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full bg-brand-blue text-white flex items-center justify-center text-xs font-bold">۱</div>
                    <span class="text-xs font-bold text-brand-blue">احراز هویت</span>
                </div>
                <div class="flex-1 mx-3 h-px bg-gray-200"></div>
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full bg-gray-200 text-gray-400 flex items-center justify-center text-xs font-bold">۲</div>
                    <span class="text-xs font-medium text-gray-400">تخصص</span>
                </div>
                <div class="flex-1 mx-3 h-px bg-gray-200"></div>
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full bg-gray-200 text-gray-400 flex items-center justify-center text-xs font-bold">۳</div>
                    <span class="text-xs font-medium text-gray-400">مدارک</span>
                </div>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-1 mt-3">
                <div id="progressBar" class="h-full rounded-full bg-brand-blue transition-all duration-500" style="width: 10%"></div>
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

            {{-- ===== فاز D: موفقیت ===== --}}
            <div id="phaseD" class="phase">
                <div class="text-center py-8">
                    <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <h2 class="text-lg font-bold text-gray-800 mb-2">احراز هویت موفق</h2>
                    <p class="text-sm text-gray-500 mb-6">
                        <span id="verifiedName" class="font-bold text-brand-blue"></span>
                        خوش آمدید
                    </p>

                    <div class="bg-gray-50 rounded-xl p-4 text-right space-y-2 mb-6">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400">نام و نام‌خانوادگی:</span>
                            <span id="resultFullName" class="font-bold text-gray-700"></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400">شماره موبایل:</span>
                            <span id="resultMobile" class="font-bold text-gray-700" dir="ltr"></span>
                        </div>
                    </div>

                    {{-- TODO: لینک به مرحله ۲ --}}
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
            const progress = { A: 10, B: 20, C: 50, D: 100 };
            $('#progressBar').css('width', (progress[phase] || 10) + '%');
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
        function clearAllErrors() { hideFieldError('mobileError'); hideFieldError('otpError'); hideFieldError('nationalCodeError'); hideFieldError('birthDateError'); hideAlert(); }

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
                    const fullName = res.first_name + ' ' + res.last_name;
                    $('#verifiedName').text(fullName);
                    $('#resultFullName').text(fullName);
                    $('#resultMobile').text(currentMobile);
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

        // ===== دیت‌پیکر =====
        function initDatePicker() {
            if ($('#birth_date').data('pDatepicker')) return;
            if (typeof $.fn.pDatepicker === 'undefined') {
                console.error('persian-datepicker not loaded');
                return;
            }
            $("#birth_date").pDatepicker({
                format: 'YYYY/MM/DD',
                autoClose: true,
                initialValue: false,
                persianDigits: false,
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
        }

        // ===== رویدادها =====
        $(document).ready(function() {
            // دیت‌پیکر رو همون اول init کن
            initDatePicker();

            // fallback: اگه کلیک شد و هنوز init نشده بود
            $('#birth_date').on('click focus', function() {
                initDatePicker();
            });

            // فقط عدد در اینپوت‌ها
            $('#mobile, #national_code').on('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
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
