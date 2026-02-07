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
</div>

<script>
function warehouseSettings() {
    return {
        testing: false,
        testResult: '',
        testSuccess: false,

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
        }
    }
}
</script>
@endsection
