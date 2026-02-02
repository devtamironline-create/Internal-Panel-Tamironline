<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $isAdmin ? 'ورود به پنل مدیریت' : 'ورود به حساب کاربری' }} | تعمیرآنلاین</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="/vendor/js/tailwind.min.js"></script>
    <script defer src="/vendor/js/alpine.min.js"></script>
    <style>
        * { font-family: 'Vazirmatn', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">
    <div class="w-full max-w-sm mx-4" x-data="loginForm()" x-cloak>
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-600 rounded-2xl mb-4">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-white">{{ $isAdmin ? 'پنل مدیریت' : 'ورود به حساب' }}</h1>
        </div>

        <!-- Card -->
        <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
            <!-- Step 1: Login -->
            <div x-show="step === 'mobile'" x-transition>
                <!-- Login Method Tabs -->
                <div class="flex mb-6 bg-slate-700/50 rounded-xl p-1">
                    <button type="button" @click="loginMethod = 'otp'" :class="loginMethod === 'otp' ? 'bg-blue-600 text-white' : 'text-slate-400'" class="flex-1 py-2.5 px-4 rounded-lg text-sm font-medium transition-all">
                        کد پیامکی
                    </button>
                    <button type="button" @click="loginMethod = 'password'" :class="loginMethod === 'password' ? 'bg-blue-600 text-white' : 'text-slate-400'" class="flex-1 py-2.5 px-4 rounded-lg text-sm font-medium transition-all">
                        رمز عبور
                    </button>
                </div>

                <!-- OTP Login Form -->
                <form x-show="loginMethod === 'otp'" @submit.prevent="sendOTP" class="space-y-4">
                    <div>
                        <label class="block mb-2 text-sm font-medium text-slate-300">شماره موبایل</label>
                        <input
                            type="tel"
                            x-model="mobile"
                            class="w-full px-4 py-3 bg-slate-700/50 border border-slate-600 rounded-xl text-white placeholder-slate-500 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            placeholder="09123456789"
                            maxlength="11"
                            dir="ltr"
                            :disabled="loading"
                        >
                    </div>

                    <button
                        type="submit"
                        class="w-full py-3 text-sm font-medium text-white bg-blue-600 rounded-xl hover:bg-blue-700 focus:ring-4 focus:ring-blue-500/30 disabled:opacity-50 transition-all"
                        :disabled="loading || !isValidMobile"
                    >
                        <span x-show="!loading">دریافت کد تایید</span>
                        <span x-show="loading" class="flex items-center justify-center gap-2">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            در حال ارسال...
                        </span>
                    </button>
                </form>

                <!-- Password Login Form -->
                <form x-show="loginMethod === 'password'" @submit.prevent="loginWithPassword" class="space-y-4">
                    <div>
                        <label class="block mb-2 text-sm font-medium text-slate-300">نام کاربری یا موبایل</label>
                        <input
                            type="text"
                            x-model="username"
                            class="w-full px-4 py-3 bg-slate-700/50 border border-slate-600 rounded-xl text-white placeholder-slate-500 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            placeholder="نام کاربری یا شماره موبایل"
                            :disabled="loading"
                        >
                    </div>

                    <div>
                        <label class="block mb-2 text-sm font-medium text-slate-300">رمز عبور</label>
                        <input
                            type="password"
                            x-model="password"
                            class="w-full px-4 py-3 bg-slate-700/50 border border-slate-600 rounded-xl text-white placeholder-slate-500 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            placeholder="رمز عبور"
                            :disabled="loading"
                        >
                    </div>

                    <button
                        type="submit"
                        class="w-full py-3 text-sm font-medium text-white bg-blue-600 rounded-xl hover:bg-blue-700 focus:ring-4 focus:ring-blue-500/30 disabled:opacity-50 transition-all"
                        :disabled="loading || !username || !password"
                    >
                        <span x-show="!loading">ورود</span>
                        <span x-show="loading" class="flex items-center justify-center gap-2">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            در حال بررسی...
                        </span>
                    </button>
                </form>
            </div>

            <!-- Step 2: OTP -->
            <div x-show="step === 'otp'" x-transition>
                <button
                    type="button"
                    @click="step = 'mobile'; code = ''"
                    class="flex items-center gap-1 mb-4 text-sm text-slate-400 hover:text-white transition"
                >
                    <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    بازگشت
                </button>

                <div class="mb-6">
                    <h2 class="text-lg font-bold text-white mb-1">کد تایید</h2>
                    <p class="text-sm text-slate-400">ارسال شده به <span class="text-white" dir="ltr" x-text="mobile"></span></p>
                </div>

                <!-- Debug Code -->
                <div x-show="debugCode" class="p-3 mb-4 bg-amber-500/20 border border-amber-500/30 rounded-xl">
                    <p class="text-sm text-amber-200">
                        کد تست: <span class="font-bold text-lg" x-text="debugCode"></span>
                    </p>
                </div>

                <form @submit.prevent="verifyOTP" class="space-y-4">
                    <div>
                        <input
                            type="text"
                            x-model="code"
                            x-ref="codeInput"
                            class="w-full px-4 py-4 text-2xl font-bold tracking-[0.5em] text-center bg-slate-700/50 border border-slate-600 rounded-xl text-white placeholder-slate-500 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            placeholder="------"
                            maxlength="6"
                            dir="ltr"
                            :disabled="loading"
                        >
                    </div>

                    <div class="text-center text-sm">
                        <p x-show="timer > 0" class="text-slate-400">
                            ارسال مجدد تا <span class="text-white font-medium" x-text="formatTimer"></span>
                        </p>
                        <button
                            type="button"
                            x-show="timer === 0"
                            @click="sendOTP"
                            class="text-blue-400 hover:text-blue-300 font-medium"
                            :disabled="loading"
                        >
                            ارسال مجدد کد
                        </button>
                    </div>

                    <button
                        type="submit"
                        class="w-full py-3 text-sm font-medium text-white bg-blue-600 rounded-xl hover:bg-blue-700 focus:ring-4 focus:ring-blue-500/30 disabled:opacity-50 transition-all"
                        :disabled="loading || code.length !== 6"
                    >
                        <span x-show="!loading">تایید و ورود</span>
                        <span x-show="loading">در حال بررسی...</span>
                    </button>
                </form>
            </div>

            <!-- Messages -->
            <div x-show="message" x-transition class="mt-4">
                <div
                    class="p-3 rounded-xl text-sm"
                    :class="messageType === 'error' ? 'bg-red-500/20 text-red-200 border border-red-500/30' : 'bg-green-500/20 text-green-200 border border-green-500/30'"
                >
                    <p x-text="message"></p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <p class="text-center text-slate-500 text-xs mt-6">تعمیرآنلاین &copy; {{ date('Y') }}</p>
    </div>

    <script>
    function loginForm() {
        return {
            step: 'mobile',
            loginMethod: 'otp',
            mobile: '',
            code: '',
            username: '',
            password: '',
            loading: false,
            timer: 0,
            timerInterval: null,
            message: '',
            messageType: 'error',
            errors: {},
            debugCode: null,
            isAdmin: {{ $isAdmin ? 'true' : 'false' }},

            get isValidMobile() { return /^09[0-9]{9}$/.test(this.mobile); },
            get formatTimer() {
                const m = Math.floor(this.timer / 60);
                const s = this.timer % 60;
                return `${m}:${s.toString().padStart(2, '0')}`;
            },

            startTimer(sec) {
                this.timer = sec;
                if (this.timerInterval) clearInterval(this.timerInterval);
                this.timerInterval = setInterval(() => {
                    if (this.timer > 0) this.timer--;
                    else clearInterval(this.timerInterval);
                }, 1000);
            },

            async sendOTP() {
                if (!this.isValidMobile || this.loading) return;
                this.loading = true;
                this.message = '';
                this.debugCode = null;

                try {
                    const res = await fetch('{{ route("auth.send-otp") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body: JSON.stringify({ mobile: this.mobile })
                    });
                    const data = await res.json();

                    if (data.success) {
                        this.step = 'otp';
                        this.startTimer(data.expires_in || 120);
                        this.debugCode = data.debug_code;
                        this.$nextTick(() => this.$refs.codeInput?.focus());
                    } else {
                        if (data.wait_time) { this.step = 'otp'; this.startTimer(data.wait_time); }
                        this.message = data.message;
                        this.messageType = 'error';
                    }
                } catch (e) { this.message = 'خطا در برقراری ارتباط'; this.messageType = 'error'; }
                this.loading = false;
            },

            async verifyOTP() {
                if (this.code.length !== 6 || this.loading) return;
                this.loading = true;
                this.message = '';

                try {
                    const res = await fetch('{{ route("auth.verify-otp") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body: JSON.stringify({ mobile: this.mobile, code: this.code, is_admin: this.isAdmin })
                    });
                    const data = await res.json();

                    if (data.success) {
                        this.message = data.message;
                        this.messageType = 'success';
                        setTimeout(() => window.location.href = data.redirect, 500);
                    } else {
                        this.message = data.message;
                        this.messageType = 'error';
                        this.code = '';
                    }
                } catch (e) { this.message = 'خطا در برقراری ارتباط'; this.messageType = 'error'; }
                this.loading = false;
            },

            async loginWithPassword() {
                if (!this.username || !this.password || this.loading) return;
                this.loading = true;
                this.message = '';

                try {
                    const res = await fetch('{{ route("auth.login-password") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body: JSON.stringify({ username: this.username, password: this.password, is_admin: this.isAdmin })
                    });
                    const data = await res.json();

                    if (data.success) {
                        this.message = data.message;
                        this.messageType = 'success';
                        setTimeout(() => window.location.href = data.redirect, 500);
                    } else {
                        this.message = data.message;
                        this.messageType = 'error';
                    }
                } catch (e) { this.message = 'خطا در برقراری ارتباط'; this.messageType = 'error'; }
                this.loading = false;
            }
        }
    }
    </script>
</body>
</html>
