@extends('layouts.admin')

@section('title', 'تنظیمات انبار')

@section('main')
<div class="p-6" x-data="warehouseSettings()">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('warehouse.dashboard') }}" class="p-2 text-slate-400 hover:text-slate-200 hover:bg-slate-700 rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-slate-100">تنظیمات انبار</h1>
                <p class="text-slate-400 mt-1">پیکربندی اتصال به ووکامرس</p>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-900/50 border border-green-700 text-green-300 rounded-lg p-4 mb-6">
        {{ session('success') }}
    </div>
    @endif

    <form action="{{ route('warehouse.settings.update') }}" method="POST" class="max-w-2xl">
        @csrf

        <!-- WooCommerce Settings -->
        <div class="bg-slate-800 rounded-lg p-6 mb-6">
            <h2 class="text-lg font-semibold text-slate-100 mb-4">تنظیمات WooCommerce</h2>
            <p class="text-slate-400 text-sm mb-6">
                برای اتصال به ووکامرس، نیاز به کلیدهای API دارید.
                این کلیدها را از بخش WooCommerce > تنظیمات > پیشرفته > REST API در پنل وردپرس دریافت کنید.
            </p>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">آدرس فروشگاه</label>
                    <input type="url" name="woocommerce_store_url"
                           value="{{ old('woocommerce_store_url', $settings['woocommerce_store_url']) }}"
                           placeholder="https://example.com"
                           class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 focus:ring-blue-500 focus:border-blue-500 @error('woocommerce_store_url') border-red-500 @enderror">
                    @error('woocommerce_store_url')
                    <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Consumer Key</label>
                    <input type="text" name="woocommerce_consumer_key"
                           value="{{ old('woocommerce_consumer_key', $settings['woocommerce_consumer_key']) }}"
                           placeholder="ck_..."
                           class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 focus:ring-blue-500 focus:border-blue-500 @error('woocommerce_consumer_key') border-red-500 @enderror">
                    @error('woocommerce_consumer_key')
                    <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Consumer Secret</label>
                    <input type="password" name="woocommerce_consumer_secret"
                           value="{{ old('woocommerce_consumer_secret', $settings['woocommerce_consumer_secret']) }}"
                           placeholder="cs_..."
                           class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 focus:ring-blue-500 focus:border-blue-500 @error('woocommerce_consumer_secret') border-red-500 @enderror">
                    @error('woocommerce_consumer_secret')
                    <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Webhook Secret (اختیاری)</label>
                    <input type="text" name="woocommerce_webhook_secret"
                           value="{{ old('woocommerce_webhook_secret', $settings['woocommerce_webhook_secret']) }}"
                           placeholder="برای دریافت اعلان‌های خودکار"
                           class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <!-- Test Connection Button -->
                <div class="pt-4">
                    <button type="button" @click="testConnection()"
                            :disabled="testing"
                            class="flex items-center gap-2 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition">
                        <svg class="w-4 h-4" :class="testing && 'animate-spin'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span x-text="testing ? 'در حال تست...' : 'تست اتصال'"></span>
                    </button>

                    <div x-show="testResult" x-transition class="mt-3 p-3 rounded-lg"
                         :class="testSuccess ? 'bg-green-900/50 text-green-300' : 'bg-red-900/50 text-red-300'">
                        <span x-text="testResult"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sync Settings -->
        <div class="bg-slate-800 rounded-lg p-6 mb-6">
            <h2 class="text-lg font-semibold text-slate-100 mb-4">تنظیمات همگام‌سازی</h2>

            <div class="space-y-4">
                <div class="flex items-center justify-between p-4 bg-slate-700/50 rounded-lg">
                    <div>
                        <div class="font-medium text-slate-200">همگام‌سازی خودکار</div>
                        <div class="text-sm text-slate-400">سفارشات به صورت خودکار همگام‌سازی شوند</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="warehouse_auto_sync" value="1"
                               {{ old('warehouse_auto_sync', $settings['warehouse_auto_sync']) ? 'checked' : '' }}
                               class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">فاصله همگام‌سازی (دقیقه)</label>
                    <input type="number" name="warehouse_sync_interval"
                           value="{{ old('warehouse_sync_interval', $settings['warehouse_sync_interval']) }}"
                           min="5" max="1440"
                           class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-slate-500 text-sm mt-1">حداقل 5 دقیقه، حداکثر 1440 دقیقه (24 ساعت)</p>
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div class="flex justify-end">
            <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">
                ذخیره تنظیمات
            </button>
        </div>
    </form>

    <!-- Warehouse Operational Settings -->
    <div class="max-w-2xl mt-8">
        <form action="{{ route('warehouse.settings.warehouse.update') }}" method="POST">
            @csrf
            <div class="bg-slate-800 rounded-lg p-6">
                <h2 class="text-lg font-semibold text-slate-100 mb-4">تنظیمات عملیاتی انبار</h2>
                <p class="text-slate-400 text-sm mb-6">
                    تنظیمات مربوط به وزن، تلرانس و اعلان‌ها
                </p>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">تلرانس وزن (درصد)</label>
                            <input type="number" name="weight_tolerance_percent"
                                   value="{{ old('weight_tolerance_percent', $settings['weight_tolerance_percent']) }}"
                                   min="0" max="100" step="0.1"
                                   class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                            <p class="text-slate-500 text-sm mt-1">اختلاف مجاز بین وزن واقعی و وزن ثبت شده در سایت</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">وزن پیش‌فرض کارتن (گرم)</label>
                            <input type="number" name="default_carton_weight"
                                   value="{{ old('default_carton_weight', $settings['default_carton_weight']) }}"
                                   min="0" step="1"
                                   class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                            <p class="text-slate-500 text-sm mt-1">وزن پیش‌فرض کارتن بسته‌بندی</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">موبایل مدیر برای هشدارها</label>
                        <input type="text" name="manager_mobile_for_alerts"
                               value="{{ old('manager_mobile_for_alerts', $settings['manager_mobile_for_alerts']) }}"
                               placeholder="09123456789"
                               class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-slate-500 text-sm mt-1">برای دریافت پیامک هشدار پرینت تکراری و سایر اعلان‌ها</p>
                    </div>
                </div>

                <!-- Queue Timer Settings -->
                <div class="border-t border-slate-700 pt-6 mt-6">
                    <h3 class="text-md font-medium text-slate-200 mb-4">تنظیمات صف آماده‌سازی</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">ددلاین پست (دقیقه)</label>
                            <input type="number" name="queue_post_deadline_minutes"
                                   value="{{ old('queue_post_deadline_minutes', $settings['queue_post_deadline_minutes'] ?? 60) }}"
                                   min="1" max="1440"
                                   class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                            <p class="text-slate-500 text-sm mt-1">پیش‌فرض: ۶۰ دقیقه (۱ ساعت)</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">ددلاین پیک (دقیقه)</label>
                            <input type="number" name="queue_courier_deadline_minutes"
                                   value="{{ old('queue_courier_deadline_minutes', $settings['queue_courier_deadline_minutes'] ?? 420) }}"
                                   min="1" max="1440"
                                   class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                            <p class="text-slate-500 text-sm mt-1">پیش‌فرض: ۴۲۰ دقیقه (۷ ساعت)</p>
                        </div>
                    </div>
                </div>

                <!-- Auto Status Change Settings -->
                <div class="border-t border-slate-700 pt-6 mt-6">
                    <h3 class="text-md font-medium text-slate-200 mb-4">تغییر خودکار وضعیت</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-slate-700/50 rounded-lg">
                            <div>
                                <div class="font-medium text-slate-200">تغییر وضعیت بعد از پرینت</div>
                                <div class="text-sm text-slate-400">وقتی فاکتور پرینت شد، وضعیت خودکار عوض شود</div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="auto_status_on_print" value="1"
                                       {{ old('auto_status_on_print', $settings['auto_status_on_print'] ?? true) ? 'checked' : '' }}
                                       class="sr-only peer">
                                <div class="w-11 h-6 bg-slate-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">وضعیت بعد از پرینت</label>
                            <select name="print_status_change_to"
                                    class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                                <option value="confirmed" {{ ($settings['print_status_change_to'] ?? 'picking') === 'confirmed' ? 'selected' : '' }}>تایید شده</option>
                                <option value="picking" {{ ($settings['print_status_change_to'] ?? 'picking') === 'picking' ? 'selected' : '' }}>در حال جمع‌آوری</option>
                                <option value="packed" {{ ($settings['print_status_change_to'] ?? 'picking') === 'packed' ? 'selected' : '' }}>بسته‌بندی شده</option>
                            </select>
                            <p class="text-slate-500 text-sm mt-1">وضعیتی که بعد از اولین پرینت اعمال شود</p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end mt-6">
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">
                        ذخیره تنظیمات عملیاتی
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Plugin Download Section -->
    <div class="max-w-2xl mt-8">
        <div class="bg-slate-800 rounded-lg p-6">
            <h2 class="text-lg font-semibold text-slate-100 mb-4">پلاگین وردپرس</h2>
            <p class="text-slate-400 text-sm mb-4">
                برای ارتباط بهتر و دریافت اعلان‌های آنی، پلاگین زیر را در وردپرس نصب کنید.
                این پلاگین امکان ارسال خودکار سفارشات جدید به پنل را فراهم می‌کند.
            </p>
            <div class="flex items-center gap-4">
                <a href="{{ asset('plugins/tamir-warehouse-connector.zip') }}"
                   class="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    دانلود پلاگین
                </a>
                <span class="text-slate-500 text-sm">نسخه 1.0.0</span>
            </div>
        </div>
    </div>

    <!-- Amadast Settings -->
    <div class="max-w-2xl mt-8">
        <div class="bg-slate-800 rounded-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-100">تنظیمات آمادست</h2>
                    <p class="text-slate-400 text-sm mt-1">سرویس حمل و نقل برای ارسال سفارشات</p>
                </div>
                @if($settings['amadast_store_id'])
                <span class="px-3 py-1 bg-green-900/50 text-green-400 rounded-full text-sm">فعال</span>
                @else
                <span class="px-3 py-1 bg-yellow-900/50 text-yellow-400 rounded-full text-sm">نیاز به تنظیم</span>
                @endif
            </div>

            @if($settings['amadast_store_id'])
            <!-- Already configured - show status -->
            <div class="bg-slate-700/50 rounded-lg p-4 mb-4">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-slate-400">نام فرستنده:</span>
                        <span class="text-slate-200 mr-2">{{ $settings['amadast_sender_name'] }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400">موبایل:</span>
                        <span class="text-slate-200 mr-2">{{ $settings['amadast_sender_mobile'] }}</span>
                    </div>
                    <div class="col-span-2">
                        <span class="text-slate-400">آدرس انبار:</span>
                        <span class="text-slate-200 mr-2">{{ $settings['amadast_warehouse_address'] }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400">Store ID:</span>
                        <span class="text-slate-200 mr-2">{{ $settings['amadast_store_id'] }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400">Location ID:</span>
                        <span class="text-slate-200 mr-2">{{ $settings['amadast_location_id'] }}</span>
                    </div>
                </div>
            </div>

            <!-- Update client code form -->
            <form action="{{ route('warehouse.settings.amadast.update') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">کد کلاینت (X-Client-Code)</label>
                    <input type="text" name="amadast_client_code"
                           value="{{ old('amadast_client_code', $settings['amadast_client_code']) }}"
                           class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">شهر پیش‌فرض گیرنده (City ID)</label>
                    <input type="number" name="amadast_default_city_id"
                           value="{{ old('amadast_default_city_id', $settings['amadast_default_city_id']) }}"
                           class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-slate-500 text-sm mt-1">اگر شهر گیرنده پیدا نشد از این شهر استفاده می‌شود</p>
                </div>

                <div class="flex items-center justify-between p-4 bg-slate-700/50 rounded-lg">
                    <div>
                        <div class="font-medium text-slate-200">فعال‌سازی ارسال خودکار</div>
                        <div class="text-sm text-slate-400">سفارشات جدید خودکار به آمادست ارسال شوند</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="amadast_enabled" value="1"
                               {{ old('amadast_enabled', $settings['amadast_enabled']) ? 'checked' : '' }}
                               class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">
                        بروزرسانی تنظیمات
                    </button>
                </div>
            </form>

            @else
            <!-- Setup form -->
            <form @submit.prevent="setupAmadast()" class="space-y-4">
                <!-- Result Message -->
                <div x-show="setupResult" x-transition class="p-4 rounded-lg"
                     :class="setupSuccess ? 'bg-green-900/50 border border-green-700 text-green-300' : 'bg-red-900/50 border border-red-700 text-red-300'">
                    <span x-text="setupResult"></span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">کد کلاینت (X-Client-Code) *</label>
                    <input type="text" x-model="amadastForm.client_code"
                           required
                           class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-slate-500 text-sm mt-1">این کد را از آمادست دریافت کنید</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">نام فرستنده *</label>
                        <input type="text" x-model="amadastForm.sender_name"
                               required
                               class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">موبایل فرستنده *</label>
                        <input type="text" x-model="amadastForm.sender_mobile"
                               placeholder="09123456789"
                               required
                               class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">نام انبار *</label>
                    <input type="text" x-model="amadastForm.warehouse_title"
                           required
                           class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">آدرس انبار *</label>
                    <textarea x-model="amadastForm.warehouse_address" rows="2" required
                              class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">استان *</label>
                        <select x-model="amadastForm.province_id" required
                                class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">انتخاب کنید</option>
                            <option value="8">تهران</option>
                            <option value="10">اصفهان</option>
                            <option value="3">آذربایجان شرقی</option>
                            <option value="2">آذربایجان غربی</option>
                            <option value="1">اردبیل</option>
                            <option value="6">بوشهر</option>
                            <option value="7">چهارمحال و بختیاری</option>
                            <option value="11">فارس</option>
                            <option value="12">گیلان</option>
                            <option value="13">گلستان</option>
                            <option value="14">همدان</option>
                            <option value="15">هرمزگان</option>
                            <option value="16">ایلام</option>
                            <option value="17">کرمان</option>
                            <option value="18">کرمانشاه</option>
                            <option value="4">خراسان جنوبی</option>
                            <option value="9">خراسان رضوی</option>
                            <option value="5">خراسان شمالی</option>
                            <option value="19">خوزستان</option>
                            <option value="20">کهگیلویه و بویراحمد</option>
                            <option value="21">کردستان</option>
                            <option value="22">لرستان</option>
                            <option value="23">مرکزی</option>
                            <option value="24">مازندران</option>
                            <option value="25">قزوین</option>
                            <option value="26">قم</option>
                            <option value="27">سمنان</option>
                            <option value="28">سیستان و بلوچستان</option>
                            <option value="29">البرز</option>
                            <option value="30">یزد</option>
                            <option value="31">زنجان</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">شهر *</label>
                        <input type="number" x-model="amadastForm.city_id" required placeholder="شناسه شهر"
                               class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-slate-500 text-xs mt-1">شناسه شهر در آمادست (مثلاً تهران = 360)</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">کد پستی انبار *</label>
                        <input type="text" x-model="amadastForm.postal_code"
                               required maxlength="10"
                               class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">نام فروشگاه *</label>
                        <input type="text" x-model="amadastForm.store_title"
                               required
                               class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" :disabled="settingUp"
                            class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 disabled:bg-purple-800 text-white rounded-lg transition">
                        <span x-text="settingUp ? 'در حال راه‌اندازی...' : 'راه‌اندازی آمادست'"></span>
                    </button>
                </div>
            </form>
            @endif
        </div>
    </div>
</div>

<script>
function warehouseSettings() {
    return {
        testing: false,
        testResult: '',
        testSuccess: false,
        settingUp: false,
        setupResult: '',
        setupSuccess: false,
        amadastForm: {
            client_code: '{{ $settings['amadast_client_code'] ?? '' }}',
            sender_name: '{{ $settings['amadast_sender_name'] ?? '' }}',
            sender_mobile: '{{ $settings['amadast_sender_mobile'] ?? '09123456789' }}',
            warehouse_title: 'انبار اصلی',
            warehouse_address: '{{ $settings['amadast_warehouse_address'] ?? '' }}',
            province_id: '',
            city_id: '',
            postal_code: '{{ $settings['amadast_postal_code'] ?? '' }}',
            store_title: 'فروشگاه آنلاین'
        },

        async testConnection() {
            this.testing = true;
            this.testResult = '';

            try {
                const response = await fetch('{{ route('warehouse.settings.test-connection') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                const data = await response.json();

                this.testSuccess = data.success;
                if (data.success) {
                    this.testResult = 'اتصال برقرار شد!' + (data.store_name ? ' فروشگاه: ' + data.store_name : '');
                } else {
                    this.testResult = data.message || 'خطا در اتصال';
                }
            } catch (error) {
                this.testSuccess = false;
                this.testResult = 'خطا در برقراری ارتباط';
            } finally {
                this.testing = false;
            }
        },

        async setupAmadast() {
            this.settingUp = true;
            this.setupResult = '';

            try {
                const response = await fetch('{{ route('warehouse.settings.amadast.setup') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        amadast_client_code: this.amadastForm.client_code,
                        sender_name: this.amadastForm.sender_name,
                        sender_mobile: this.amadastForm.sender_mobile,
                        warehouse_title: this.amadastForm.warehouse_title,
                        warehouse_address: this.amadastForm.warehouse_address,
                        province_id: this.amadastForm.province_id,
                        city_id: this.amadastForm.city_id,
                        postal_code: this.amadastForm.postal_code,
                        store_title: this.amadastForm.store_title
                    })
                });

                const data = await response.json();
                console.log('Setup response:', data);

                if (response.ok && data.success) {
                    this.setupSuccess = true;
                    this.setupResult = data.message || 'تنظیمات با موفقیت انجام شد';
                    setTimeout(() => location.reload(), 2000);
                } else if (data.errors) {
                    // Validation errors
                    this.setupSuccess = false;
                    const errors = Object.values(data.errors).flat();
                    this.setupResult = errors.join(' - ');
                } else {
                    this.setupSuccess = false;
                    this.setupResult = data.message || data.error || 'خطا در راه‌اندازی';
                }
            } catch (error) {
                console.error('Setup error:', error);
                this.setupSuccess = false;
                this.setupResult = 'خطا در برقراری ارتباط با سرور';
            } finally {
                this.settingUp = false;
            }
        }
    }
}
</script>
@endsection
