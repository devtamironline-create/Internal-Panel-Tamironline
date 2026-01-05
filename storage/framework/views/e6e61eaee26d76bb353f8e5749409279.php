<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo e($isAdmin ? 'ورود به پنل مدیریت' : 'ورود به حساب کاربری'); ?> | تعمیرآنلاین</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="/css/fonts.css" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        * { font-family: 'Rokh', sans-serif; font-weight: 500; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900">
    <div class="flex min-h-screen">
        <!-- Left Side - Form -->
        <div class="flex flex-col justify-center w-full px-4 py-12 lg:w-1/2 sm:px-6 lg:px-20 xl:px-24">
            <div class="w-full max-w-md mx-auto">
                
                <div x-data="loginForm()" x-cloak>
                    <!-- Step 1: Mobile -->
                    <div x-show="step === 'mobile'" x-transition>
                        <div class="mb-8">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isAdmin): ?>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">ورود به پنل مدیریت</h1>
                            <p class="mt-2 text-gray-600 dark:text-gray-400">شماره موبایل ادمین را وارد کنید</p>
                            <?php else: ?>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">ورود / ثبت‌نام</h1>
                            <p class="mt-2 text-gray-600 dark:text-gray-400">شماره موبایل خود را وارد کنید</p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        
                        <form @submit.prevent="sendOTP" class="space-y-6">
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    شماره موبایل <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="tel" 
                                    x-model="mobile"
                                    class="w-full px-5 py-3 text-gray-900 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                                    placeholder="09123456789"
                                    maxlength="11"
                                    dir="ltr"
                                    :disabled="loading"
                                >
                                <p x-show="errors.mobile" x-text="errors.mobile" class="mt-2 text-sm text-red-600"></p>
                            </div>
                            
                            <button 
                                type="submit" 
                                class="w-full px-5 py-3 text-base font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                :disabled="loading || !isValidMobile"
                            >
                                <span x-show="!loading">دریافت کد تایید</span>
                                <span x-show="loading" class="flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    در حال ارسال...
                                </span>
                            </button>
                        </form>
                    </div>
                    
                    <!-- Step 2: OTP -->
                    <div x-show="step === 'otp'" x-transition>
                        <button 
                            type="button" 
                            @click="step = 'mobile'; code = ''" 
                            class="flex items-center gap-2 mb-6 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                        >
                            <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            بازگشت
                        </button>
                        
                        <div class="mb-8">
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">تایید شماره موبایل</h1>
                            <p class="mt-2 text-gray-600 dark:text-gray-400">
                                کد تایید به شماره <span class="font-medium text-gray-900 dark:text-white" dir="ltr" x-text="mobile"></span> ارسال شد
                            </p>
                        </div>
                        
                        <!-- Debug Code -->
                        <div x-show="debugCode" class="p-4 mb-6 bg-amber-50 border border-amber-200 rounded-lg">
                            <p class="text-sm text-amber-800">
                                <span class="font-medium">کد تست:</span>
                                <span class="font-bold text-lg mr-2" x-text="debugCode"></span>
                            </p>
                        </div>
                        
                        <form @submit.prevent="verifyOTP" class="space-y-6">
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    کد تایید <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    x-model="code"
                                    x-ref="codeInput"
                                    class="w-full px-5 py-4 text-2xl font-bold tracking-[0.5em] text-center text-gray-900 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                                    placeholder="------"
                                    maxlength="6"
                                    dir="ltr"
                                    :disabled="loading"
                                >
                            </div>
                            
                            <div class="text-center">
                                <p x-show="timer > 0" class="text-sm text-gray-600 dark:text-gray-400">
                                    ارسال مجدد تا <span class="font-medium text-gray-900 dark:text-white" x-text="formatTimer"></span>
                                </p>
                                <button 
                                    type="button" 
                                    x-show="timer === 0" 
                                    @click="sendOTP" 
                                    class="text-sm font-medium text-blue-600 hover:text-blue-700"
                                    :disabled="loading"
                                >
                                    ارسال مجدد کد
                                </button>
                            </div>
                            
                            <button 
                                type="submit" 
                                class="w-full px-5 py-3 text-base font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                :disabled="loading || code.length !== 6"
                            >
                                <span x-show="!loading">تایید و ورود</span>
                                <span x-show="loading">در حال بررسی...</span>
                            </button>
                        </form>
                    </div>
                    
                    <!-- Messages -->
                    <div x-show="message" x-transition class="mt-6">
                        <div 
                            class="p-4 rounded-lg text-sm"
                            :class="messageType === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200'"
                        >
                            <p x-text="message"></p>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
        
        <!-- Right Side - Branding -->
        <div class="relative hidden lg:flex lg:w-1/2 bg-gradient-to-br <?php echo e($isAdmin ? 'from-blue-600 to-indigo-800' : 'from-emerald-500 to-teal-700'); ?>">
            <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg%20width%3D%2260%22%20height%3D%2260%22%20viewBox%3D%220%200%2060%2060%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cg%20fill%3D%22none%22%20fill-rule%3D%22evenodd%22%3E%3Cg%20fill%3D%22%23ffffff%22%20fill-opacity%3D%220.05%22%3E%3Crect%20x%3D%2236%22%20width%3D%226%22%20height%3D%226%22%2F%3E%3Crect%20x%3D%2218%22%20y%3D%2218%22%20width%3D%226%22%20height%3D%226%22%2F%3E%3Crect%20y%3D%2236%22%20width%3D%226%22%20height%3D%226%22%2F%3E%3Crect%20x%3D%2236%22%20y%3D%2236%22%20width%3D%226%22%20height%3D%226%22%2F%3E%3C%2Fg%3E%3C%2Fg%3E%3C%2Fsvg%3E')] opacity-50"></div>
            <div class="relative flex flex-col items-center justify-center w-full px-12 text-center">
                <!-- Logo -->
                <div class="flex items-center justify-center w-20 h-20 mb-8 bg-white rounded-2xl shadow-lg">
                    <svg class="w-10 h-10 <?php echo e($isAdmin ? 'text-blue-600' : 'text-emerald-600'); ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
                    </svg>
                </div>

                <h2 class="text-3xl font-bold text-white mb-4">تعمیرآنلاین</h2>
                <p class="text-lg <?php echo e($isAdmin ? 'text-blue-100' : 'text-emerald-100'); ?> max-w-sm">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isAdmin): ?>
                    پنل داخلی مدیریت تعمیرات
                    <?php else: ?>
                    خدمات تعمیرات آنلاین
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </p>
                
                <!-- Features -->
                <div class="grid grid-cols-2 gap-4 mt-12 text-right">
                    <div class="flex items-center gap-3 p-4 bg-white/10 rounded-xl backdrop-blur-sm">
                        <div class="flex items-center justify-center w-10 h-10 bg-white/20 rounded-lg">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                        </div>
                        <span class="text-white text-sm">مدیریت مشتریان</span>
                    </div>
                    <div class="flex items-center gap-3 p-4 bg-white/10 rounded-xl backdrop-blur-sm">
                        <div class="flex items-center justify-center w-10 h-10 bg-white/20 rounded-lg">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/>
                            </svg>
                        </div>
                        <span class="text-white text-sm">صدور فاکتور</span>
                    </div>
                    <div class="flex items-center gap-3 p-4 bg-white/10 rounded-xl backdrop-blur-sm">
                        <div class="flex items-center justify-center w-10 h-10 bg-white/20 rounded-lg">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                            </svg>
                        </div>
                        <span class="text-white text-sm">پشتیبانی تیکت</span>
                    </div>
                    <div class="flex items-center gap-3 p-4 bg-white/10 rounded-xl backdrop-blur-sm">
                        <div class="flex items-center justify-center w-10 h-10 bg-white/20 rounded-lg">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
                            </svg>
                        </div>
                        <span class="text-white text-sm">مدیریت سرویس</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function loginForm() {
        return {
            step: 'mobile',
            mobile: '',
            code: '',
            loading: false,
            timer: 0,
            timerInterval: null,
            message: '',
            messageType: 'error',
            errors: {},
            debugCode: null,
            isAdmin: <?php echo e($isAdmin ? 'true' : 'false'); ?>,
            
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
                    const res = await fetch('<?php echo e(route("auth.send-otp")); ?>', {
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
                    const res = await fetch('<?php echo e(route("auth.verify-otp")); ?>', {
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
            }
        }
    }
    </script>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/Tamironline-CRM/Modules/Core/Resources/views/auth/login.blade.php ENDPATH**/ ?>