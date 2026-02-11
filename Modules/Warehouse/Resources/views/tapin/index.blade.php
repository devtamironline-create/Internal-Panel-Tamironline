@extends('layouts.admin')
@section('page-title', 'اتصال تاپین')
@section('main')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-4">
            <a href="{{ route('warehouse.index') }}" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900">اتصال به تاپین</h1>
                <p class="text-gray-600 mt-1">پیشخوان مجازی پست - ثبت و رهگیری مرسولات</p>
            </div>
        </div>
        <a href="{{ route('warehouse.amadest.index') }}" class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">تنظیمات آمادست</a>
    </div>

    <!-- Provider Selector -->
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-700">سرویس‌دهنده ارسال فعال:</span>
                <div class="flex gap-2">
                    <button onclick="setProvider('amadest')" id="btn-amadest"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition {{ ($settings['shipping_provider'] ?? 'amadest') === 'amadest' ? 'bg-orange-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        آمادست
                    </button>
                    <button onclick="setProvider('tapin')" id="btn-tapin"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition {{ ($settings['shipping_provider'] ?? 'amadest') === 'tapin' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        تاپین
                    </button>
                </div>
            </div>
            <span id="provider-status" class="text-xs text-gray-500">
                @if(($settings['shipping_provider'] ?? 'amadest') === 'tapin')
                    سفارشات از طریق تاپین ثبت می‌شوند
                @else
                    سفارشات از طریق آمادست ثبت می‌شوند
                @endif
            </span>
        </div>
        <div id="provider-result" class="hidden mt-3 p-3 rounded-lg text-sm"></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- API Settings -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center text-blue-700 font-bold">1</div>
                <h2 class="text-lg font-bold text-gray-900">تنظیمات API تاپین</h2>
            </div>

            <form action="{{ route('warehouse.tapin.save') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">آدرس API</label>
                    <input type="url" name="api_url" value="{{ $settings['api_url'] ?? 'https://api.tapin.ir' }}" dir="ltr"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">API Key (توکن) <span class="text-red-500">*</span></label>
                    <input type="password" name="api_key" dir="ltr"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm font-mono"
                           placeholder="{{ $settings['has_key'] ? 'کلید ذخیره شده - برای تغییر مقدار جدید وارد کنید' : 'کلید API تاپین' }}">
                    <p class="text-gray-400 text-xs mt-1">از پنل تاپین بخش API بگیرید.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">شناسه فروشگاه (Shop ID) <span class="text-red-500">*</span></label>
                    <input type="text" name="shop_id" value="{{ $settings['shop_id'] ?? '' }}" dir="ltr"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm font-mono"
                           placeholder="شناسه فروشگاه در تاپین">
                    <p class="text-gray-400 text-xs mt-1">از پنل تاپین بخش فروشگاه‌ها بگیرید.</p>
                </div>

                <div class="flex items-center gap-3 pt-4 border-t">
                    <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm">ذخیره تنظیمات</button>
                    <button type="button" onclick="testTapinConnection()" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium text-sm">تست اتصال</button>
                </div>
            </form>
            <div id="tapin-test-result" class="hidden mt-4 p-4 rounded-lg text-sm"></div>
        </div>

        <!-- Sender Info + Credit -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center text-emerald-700 font-bold">2</div>
                <h2 class="text-lg font-bold text-gray-900">اطلاعات فرستنده</h2>
            </div>

            <form action="{{ route('warehouse.tapin.save') }}" method="POST" class="space-y-4">
                @csrf
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نام فرستنده</label>
                        <input type="text" name="sender_name" value="{{ $settings['sender_name'] ?? '' }}"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm" placeholder="نام فرستنده">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">موبایل فرستنده</label>
                        <input type="text" name="sender_mobile" value="{{ $settings['sender_mobile'] ?? '' }}" dir="ltr"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm" placeholder="09123456789">
                    </div>
                </div>
                <div class="pt-4 border-t">
                    <button type="submit" class="w-full px-6 py-2.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 font-medium text-sm">ذخیره</button>
                </div>
            </form>

            <!-- Credit Check -->
            <div class="mt-6 pt-4 border-t">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-medium text-gray-700">اعتبار فروشگاه</span>
                    <button type="button" onclick="checkCredit()" class="text-xs text-blue-600 hover:text-blue-800 underline">بررسی اعتبار</button>
                </div>
                <div id="credit-result" class="hidden p-3 rounded-lg text-sm"></div>
            </div>
        </div>
    </div>

    <!-- Tracking -->
    <div class="bg-white rounded-xl shadow-sm p-6 max-w-xl">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <h2 class="text-lg font-bold text-gray-900">رهگیری مرسوله</h2>
        </div>
        <div class="flex gap-3">
            <input type="text" id="tracking-code" dir="ltr"
                   class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm"
                   placeholder="کد رهگیری پستی">
            <button onclick="trackTapin()" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium text-sm">رهگیری</button>
        </div>
        <div id="tracking-result" class="hidden mt-4 p-4 rounded-lg text-sm"></div>
    </div>
</div>

@push('scripts')
<script>
const csrfToken = '{{ csrf_token() }}';
const defaultHeaders = { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' };

function showResult(divId, success, message) {
    const div = document.getElementById(divId);
    div.classList.remove('hidden', 'bg-green-50', 'text-green-800', 'bg-red-50', 'text-red-800', 'bg-gray-50', 'text-gray-600');
    div.classList.add(success ? 'bg-green-50' : 'bg-red-50', success ? 'text-green-800' : 'text-red-800');
    div.innerHTML = message;
}

function showLoading(divId, text) {
    const div = document.getElementById(divId);
    div.classList.remove('hidden', 'bg-green-50', 'text-green-800', 'bg-red-50', 'text-red-800');
    div.classList.add('bg-gray-50', 'text-gray-600');
    div.textContent = text || 'در حال پردازش...';
}

function setProvider(provider) {
    fetch('{{ route("warehouse.tapin.set-provider") }}', {
        method: 'POST', headers: defaultHeaders,
        body: JSON.stringify({ provider: provider }),
    })
    .then(r => r.json())
    .then(data => {
        showResult('provider-result', data.success, (data.success ? '&#10003; ' : '') + (data.message || 'خطا'));
        if (data.success) setTimeout(() => location.reload(), 800);
    })
    .catch(() => showResult('provider-result', false, 'خطا در ارتباط'));
}

function testTapinConnection() {
    showLoading('tapin-test-result', 'در حال تست اتصال...');
    fetch('{{ route("warehouse.tapin.test") }}', { method: 'POST', headers: defaultHeaders })
    .then(r => r.json())
    .then(data => showResult('tapin-test-result', data.success, (data.success ? '&#10003; ' : '') + data.message))
    .catch(() => showResult('tapin-test-result', false, 'خطا در ارتباط'));
}

function checkCredit() {
    showLoading('credit-result', 'در حال بررسی...');
    fetch('{{ route("warehouse.tapin.credit") }}', { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const d = data.data || {};
            showResult('credit-result', true, '<strong>اعتبار:</strong> ' + JSON.stringify(d, null, 2));
        } else {
            showResult('credit-result', false, data.message || 'خطا');
        }
    })
    .catch(() => showResult('credit-result', false, 'خطا در ارتباط'));
}

function trackTapin() {
    const code = document.getElementById('tracking-code').value;
    if (!code) { showResult('tracking-result', false, 'کد رهگیری وارد کنید'); return; }
    showLoading('tracking-result', 'در حال رهگیری...');
    fetch('{{ route("warehouse.tapin.track") }}', {
        method: 'POST', headers: defaultHeaders,
        body: JSON.stringify({ tracking_code: code }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showResult('tracking-result', true, '<pre dir="ltr" class="text-xs mt-1">' + JSON.stringify(data.data, null, 2) + '</pre>');
        } else {
            showResult('tracking-result', false, data.message || 'یافت نشد');
        }
    })
    .catch(() => showResult('tracking-result', false, 'خطا در ارتباط'));
}
</script>
@endpush
@endsection
