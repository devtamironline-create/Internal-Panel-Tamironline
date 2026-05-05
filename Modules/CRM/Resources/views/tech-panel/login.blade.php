<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ورود تکنسین</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4 font-sans">
    <div class="w-full max-w-sm bg-white rounded-2xl shadow-lg p-6 space-y-4" x-data="techLogin()" x-cloak>
        <h1 class="text-xl font-bold text-center">ورود تکنسین</h1>
        <p class="text-xs text-gray-500 text-center">فاز ۱ — تست اولیه auth. ظاهر کامل در فاز ۲ اعمال می‌شود.</p>

        <!-- Tabs -->
        <div class="flex border rounded-lg overflow-hidden text-sm">
            <button type="button" @click="mode='otp'" :class="mode==='otp' ? 'bg-blue-600 text-white' : 'bg-gray-100'" class="flex-1 py-2">پیامک</button>
            <button type="button" @click="mode='password'" :class="mode==='password' ? 'bg-blue-600 text-white' : 'bg-gray-100'" class="flex-1 py-2">رمز عبور</button>
        </div>

        <div x-show="error" class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-3" x-text="error"></div>
        <div x-show="info" class="bg-blue-50 border border-blue-200 text-blue-700 text-sm rounded-lg p-3" x-text="info"></div>

        <!-- OTP mode -->
        <div x-show="mode==='otp'" class="space-y-3">
            <div>
                <label class="block text-xs text-gray-600 mb-1">شماره موبایل</label>
                <input type="tel" x-model="mobile" maxlength="11" placeholder="09xxxxxxxxx" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div x-show="otpSent">
                <label class="block text-xs text-gray-600 mb-1">کد تایید (۶ رقم)</label>
                <input type="text" x-model="code" maxlength="6" inputmode="numeric" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm tracking-widest text-center">
            </div>
            <button x-show="!otpSent" @click="sendOtp" :disabled="busy"
                    class="w-full py-2 bg-blue-600 text-white rounded-lg text-sm disabled:opacity-50">
                <span x-show="!busy">ارسال کد</span>
                <span x-show="busy">در حال ارسال…</span>
            </button>
            <button x-show="otpSent" @click="verifyOtp" :disabled="busy"
                    class="w-full py-2 bg-green-600 text-white rounded-lg text-sm disabled:opacity-50">
                <span x-show="!busy">تایید و ورود</span>
                <span x-show="busy">…</span>
            </button>
            <button x-show="otpSent" @click="otpSent=false; code=''" type="button"
                    class="w-full py-2 text-xs text-gray-500">تغییر شماره</button>
        </div>

        <!-- Password mode -->
        <div x-show="mode==='password'" class="space-y-3">
            <div>
                <label class="block text-xs text-gray-600 mb-1">شماره موبایل</label>
                <input type="tel" x-model="mobile" maxlength="11" placeholder="09xxxxxxxxx" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">رمز عبور</label>
                <input type="password" x-model="password"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <button @click="loginPassword" :disabled="busy"
                    class="w-full py-2 bg-blue-600 text-white rounded-lg text-sm disabled:opacity-50">
                <span x-show="!busy">ورود</span>
                <span x-show="busy">…</span>
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
        function techLogin() {
            return {
                mode: 'otp',
                mobile: '',
                code: '',
                password: '',
                otpSent: false,
                busy: false,
                error: '',
                info: '',
                async post(url, data) {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify(data),
                    });
                    return { status: res.status, body: await res.json().catch(() => ({})) };
                },
                async sendOtp() {
                    this.error = ''; this.info = ''; this.busy = true;
                    try {
                        const { status, body } = await this.post('{{ route('tech.auth.send-otp') }}', { mobile: this.mobile });
                        if (status === 200 && body.success) {
                            this.otpSent = true;
                            this.info = body.debug_code ? ('کد ارسال شد. (debug: ' + body.debug_code + ')') : 'کد ارسال شد';
                        } else {
                            this.error = body.message || 'خطای ناشناخته';
                        }
                    } catch (e) { this.error = 'خطای شبکه'; }
                    this.busy = false;
                },
                async verifyOtp() {
                    this.error = ''; this.busy = true;
                    try {
                        const { status, body } = await this.post('{{ route('tech.auth.verify-otp') }}', { mobile: this.mobile, code: this.code });
                        if (status === 200 && body.success) {
                            window.location.href = body.redirect;
                        } else {
                            this.error = body.message || 'خطای ناشناخته';
                        }
                    } catch (e) { this.error = 'خطای شبکه'; }
                    this.busy = false;
                },
                async loginPassword() {
                    this.error = ''; this.busy = true;
                    try {
                        const { status, body } = await this.post('{{ route('tech.auth.login-password') }}', { mobile: this.mobile, password: this.password });
                        if (status === 200 && body.success) {
                            window.location.href = body.redirect;
                        } else {
                            this.error = body.message || 'خطای ناشناخته';
                        }
                    } catch (e) { this.error = 'خطای شبکه'; }
                    this.busy = false;
                },
            }
        }
    </script>
</body>
</html>
