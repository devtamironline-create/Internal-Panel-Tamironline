@extends('crm::tech-panel.layout')

@section('title', 'ورود تکنسین')

@section('body')
<div class="min-h-screen flex flex-col" x-data="techLogin()" x-cloak>
    {{-- ─────── Hero banner (top half) ─────── --}}
    <div class="relative overflow-hidden flex-1 flex items-center justify-center"
         style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%); min-height: 45vh;">
        @if($brandHero)
            {{-- از روت Laravel استفاده می‌کنیم چون symlink استوریج روی
                 هاست cPanel/LiteSpeed قابل اعتماد نیست. --}}
            <img src="{{ route('crm.tech-panel-settings.serve', 'tech_panel_hero') }}" alt="hero"
                 class="absolute inset-0 w-full h-full object-cover">
        @else
            {{-- Decorative SVG fallback تا وقتی تصویر آپلود نشده --}}
            <div class="absolute inset-0 opacity-20" style="
                background-image: radial-gradient(circle at 20% 30%, rgba(251, 191, 36, .35), transparent 40%),
                                  radial-gradient(circle at 80% 70%, rgba(96, 165, 250, .35), transparent 40%);"></div>
            <div class="relative z-10 text-center px-6">
                <div class="inline-flex items-center justify-center w-24 h-24 rounded-3xl bg-white/10 backdrop-blur-md mb-4 border border-white/20">
                    <svg class="w-12 h-12 text-gold-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18L19.82 8 12 11.82 4.18 8 12 4.18zM4 9.39l7 3.5v7.22l-7-3.5V9.39zm9 10.72v-7.22l7-3.5v7.22l-7 3.5z"/>
                    </svg>
                </div>
                <div class="text-gold-400 text-2xl font-bold">چتر طلایی ضمانت</div>
                <div class="text-white text-sm mt-1 opacity-90">پنل تکنسین — کارت کامل سفارش‌ها</div>
            </div>
        @endif
    </div>

    {{-- ─────── Bottom sheet form ─────── --}}
    <div class="bg-white rounded-t-3xl -mt-6 px-6 pt-4 pb-8 relative z-10 shadow-[0_-4px_20px_rgba(0,0,0,0.08)]">
        {{-- Drag indicator --}}
        <div class="w-12 h-1.5 rounded-full bg-gray-200 mx-auto mb-5"></div>

        {{-- Title --}}
        <div class="flex items-center gap-2 mb-1">
            <div class="w-9 h-9 rounded-xl bg-brand-50 flex items-center justify-center">
                <svg class="w-5 h-5 text-brand-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
            </div>
            <h1 class="text-lg font-bold text-gray-900">خوش آمدید</h1>
        </div>
        <p class="text-xs text-gray-500 mb-5">برای ورود به پنل تکنسین، شماره موبایل خود را وارد کنید.</p>

        {{-- Error/info banners --}}
        <div x-show="error" class="bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl p-3 mb-3" x-text="error"></div>
        <div x-show="info" class="bg-brand-50 border border-brand-200 text-brand-700 text-xs rounded-xl p-3 mb-3" x-text="info"></div>

        {{-- ─── OTP-only login ─── --}}
        <div>
            <div class="space-y-1 mb-4">
                <label class="text-sm font-medium text-gray-700">شماره موبایل</label>
                <input type="tel" x-model="mobile" maxlength="11" placeholder="09xxxxxxxxx" dir="ltr"
                       :disabled="otpSent"
                       class="w-full px-4 py-3.5 border border-gray-200 rounded-xl text-base placeholder:text-gray-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition disabled:bg-gray-50">
            </div>

            <template x-if="otpSent">
                <div class="space-y-1 mb-4">
                    <label class="text-sm font-medium text-gray-700">کد تایید ({{ str_replace(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], (string) (int) config('sms.otp.length', 4)) }} رقم)</label>
                    <input type="text" x-model="code" maxlength="{{ (int) config('sms.otp.length', 4) }}" autocomplete="one-time-code" inputmode="numeric" dir="ltr"
                           class="w-full px-4 py-3.5 border border-gray-200 rounded-xl text-lg tracking-[.5em] text-center placeholder:text-gray-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition"
                           placeholder="{{ str_repeat('-', (int) config('sms.otp.length', 4)) }}">
                </div>
            </template>

            <button x-show="!otpSent" @click="sendOtp" :disabled="busy || !mobile"
                    class="w-full py-3.5 rounded-2xl bg-brand-700 hover:bg-brand-800 text-white font-medium text-sm flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed transition">
                <span x-show="!busy">ادامه</span>
                <span x-show="busy">در حال ارسال…</span>
                <svg x-show="!busy" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </button>

            <button x-show="otpSent" @click="verifyOtp" :disabled="busy || code.length !== {{ (int) config('sms.otp.length', 4) }}"
                    class="w-full py-3.5 rounded-2xl bg-brand-700 hover:bg-brand-800 text-white font-medium text-sm disabled:opacity-50 disabled:cursor-not-allowed transition">
                <span x-show="!busy">تایید و ورود</span>
                <span x-show="busy">…</span>
            </button>

            <button x-show="otpSent" @click="otpSent=false; code=''" type="button"
                    class="w-full mt-2 py-2 text-xs text-gray-500 hover:text-brand-700">تغییر شماره</button>
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-between text-xs mt-6 pt-4 border-t border-gray-100">
            <span class="text-gray-400">شرایط استفاده</span>
            @if($supportPhone)
                <a href="tel:{{ $supportPhone }}" class="text-brand-700 font-medium">تماس با پشتیبانی</a>
            @else
                <span class="text-gray-400">تماس با پشتیبانی</span>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
    function techLogin() {
        return {
            mobile: '',
            code: '',
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
                    const { body, status } = await this.post('{{ route('tech.auth.send-otp') }}', { mobile: this.mobile });
                    if (status === 200 && body.success) {
                        this.otpSent = true;
                        this.info = body.debug_code ? ('کد ارسال شد. (debug: ' + body.debug_code + ')') : 'کد به موبایل شما ارسال شد';
                    } else {
                        this.error = body.message || 'خطای ناشناخته';
                    }
                } catch (e) { this.error = 'خطای شبکه'; }
                this.busy = false;
            },
            async verifyOtp() {
                this.error = ''; this.busy = true;
                try {
                    const { body, status } = await this.post('{{ route('tech.auth.verify-otp') }}', { mobile: this.mobile, code: this.code });
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
@endpush
@endsection
