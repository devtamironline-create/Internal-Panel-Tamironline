@extends('layouts.admin')
@section('page-title', 'اتصال ووکامرس')
@section('main')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-4">
            <a href="{{ route('warehouse.index') }}" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900">اتصال به ووکامرس</h1>
                <p class="text-gray-600 mt-1">سینک سفارشات از فروشگاه ووکامرسی</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Settings Card -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <h2 class="text-lg font-bold text-gray-900">تنظیمات API</h2>
            </div>

            <form action="{{ route('warehouse.woocommerce.save') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">آدرس سایت <span class="text-red-500">*</span></label>
                    <input type="url" name="site_url" value="{{ old('site_url', $settings['site_url']) }}" required dir="ltr"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-sm"
                           placeholder="https://yourstore.com">
                    @error('site_url')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Consumer Key <span class="text-red-500">*</span></label>
                    <input type="text" name="consumer_key" value="{{ old('consumer_key', $settings['consumer_key']) }}" required dir="ltr"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-sm font-mono"
                           placeholder="ck_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                    @error('consumer_key')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Consumer Secret <span class="text-red-500">*</span></label>
                    <input type="password" name="consumer_secret" value="{{ old('consumer_secret', $settings['consumer_secret']) }}" required dir="ltr"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-sm font-mono"
                           placeholder="cs_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                    @error('consumer_secret')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center gap-3 pt-4 border-t">
                    <button type="submit" class="px-6 py-2.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-medium text-sm">ذخیره تنظیمات</button>
                    <button type="button" onclick="testWcConnection()" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium text-sm">تست اتصال</button>
                </div>
            </form>

            <!-- Connection Test Result -->
            <div id="wc-test-result" class="hidden mt-4 p-4 rounded-lg text-sm"></div>
        </div>

        <!-- Sync Card -->
        <div class="space-y-6">
            <!-- Status Card -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </div>
                    <h2 class="text-lg font-bold text-gray-900">سینک سفارشات</h2>
                </div>

                @if($lastSync)
                <div class="flex items-center gap-2 mb-4 p-3 bg-green-50 rounded-lg">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-sm text-green-800">آخرین سینک: {{ \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($lastSync))->format('Y/m/d H:i') }}</span>
                </div>
                @else
                <div class="flex items-center gap-2 mb-4 p-3 bg-yellow-50 rounded-lg">
                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    <span class="text-sm text-yellow-800">هنوز سینکی انجام نشده است.</span>
                </div>
                @endif

                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">وضعیت سفارشات ووکامرس</label>
                        <select id="wc-sync-status" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="processing">در حال پردازش (Processing)</option>
                            <option value="on-hold">در انتظار (On Hold)</option>
                            <option value="pending">در انتظار پرداخت (Pending)</option>
                            <option value="completed">تکمیل شده (Completed)</option>
                            <option value="any">همه وضعیت‌ها</option>
                        </select>
                    </div>
                    <button onclick="syncOrders()" id="sync-btn" class="w-full px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" id="sync-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <span id="sync-text">شروع سینک سفارشات</span>
                    </button>
                </div>

                <!-- Sync Result -->
                <div id="wc-sync-result" class="hidden mt-4 p-4 rounded-lg text-sm"></div>
            </div>

            <!-- Help Card -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-sm font-bold text-gray-900 mb-3">راهنمای اتصال</h3>
                <ol class="space-y-2 text-sm text-gray-600 list-decimal list-inside">
                    <li>وارد پنل مدیریت وردپرس شوید</li>
                    <li>به <strong class="text-gray-900">ووکامرس &rarr; تنظیمات &rarr; پیشرفته &rarr; REST API</strong> بروید</li>
                    <li>روی <strong class="text-gray-900">افزودن کلید</strong> کلیک کنید</li>
                    <li>توضیحات را وارد کنید و سطح دسترسی <strong class="text-gray-900">خواندن</strong> را انتخاب کنید</li>
                    <li>کلیدهای Consumer Key و Consumer Secret را کپی کنید</li>
                </ol>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function showResult(divId, success, message) {
    const div = document.getElementById(divId);
    div.classList.remove('hidden', 'bg-green-50', 'text-green-800', 'bg-red-50', 'text-red-800', 'bg-gray-50', 'text-gray-600');
    if (success) {
        div.classList.add('bg-green-50', 'text-green-800');
    } else {
        div.classList.add('bg-red-50', 'text-red-800');
    }
    // Replace newlines with <br> for multi-line messages
    div.innerHTML = message.replace(/\n/g, '<br>');
}

function testWcConnection() {
    const resultDiv = document.getElementById('wc-test-result');
    resultDiv.classList.remove('hidden', 'bg-green-50', 'text-green-800', 'bg-red-50', 'text-red-800');
    resultDiv.classList.add('bg-gray-50', 'text-gray-600');
    resultDiv.textContent = 'در حال تست اتصال...';

    fetch('{{ route("warehouse.woocommerce.test") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
    .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    })
    .then(data => {
        if (data.success) {
            let msg = '<strong>&#10003; ' + data.message + '</strong>';
            if (data.wc_version) msg += '<br>نسخه ووکامرس: ' + data.wc_version;
            showResult('wc-test-result', true, msg);
        } else {
            showResult('wc-test-result', false, data.message);
        }
    })
    .catch(err => {
        showResult('wc-test-result', false, 'خطا: ' + err.message);
    });
}

function syncOrders() {
    const btn = document.getElementById('sync-btn');
    const icon = document.getElementById('sync-icon');
    const text = document.getElementById('sync-text');
    const status = document.getElementById('wc-sync-status').value;

    btn.disabled = true;
    icon.classList.add('animate-spin');
    text.textContent = 'در حال سینک...';
    document.getElementById('wc-sync-result').classList.add('hidden');

    fetch('{{ route("warehouse.woocommerce.sync") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ wc_status: status }),
    })
    .then(r => {
        if (!r.ok) {
            return r.text().then(t => {
                throw new Error('سرور خطا برگرداند (HTTP ' + r.status + ')');
            });
        }
        return r.json();
    })
    .then(data => {
        btn.disabled = false;
        icon.classList.remove('animate-spin');
        text.textContent = 'شروع سینک سفارشات';
        showResult('wc-sync-result', data.success, data.message);
        if (data.success && data.imported > 0) {
            setTimeout(() => location.reload(), 2000);
        }
    })
    .catch(err => {
        btn.disabled = false;
        icon.classList.remove('animate-spin');
        text.textContent = 'شروع سینک سفارشات';
        showResult('wc-sync-result', false, 'خطا: ' + err.message);
    });
}
</script>
@endpush
@endsection
