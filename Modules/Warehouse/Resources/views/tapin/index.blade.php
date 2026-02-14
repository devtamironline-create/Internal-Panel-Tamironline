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
                    @if(!empty($settings['key_preview']))
                        <p class="text-blue-500 text-xs mt-1 font-mono" dir="ltr">ذخیره شده: {{ $settings['key_preview'] }}</p>
                    @else
                        <p class="text-gray-400 text-xs mt-1">از پنل تاپین بخش API بگیرید.</p>
                    @endif
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
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نوع ثبت سفارش</label>
                        <select name="register_type" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm">
                            <option value="0" {{ ($settings['register_type'] ?? '2') == '0' ? 'selected' : '' }}>بدون بارکد (تست)</option>
                            <option value="1" {{ ($settings['register_type'] ?? '2') == '1' ? 'selected' : '' }}>با بارکد - آماده پرینت</option>
                            <option value="2" {{ ($settings['register_type'] ?? '2') == '2' ? 'selected' : '' }}>با بارکد - آماده ارسال</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نوع سفارش</label>
                        <select name="order_type" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm">
                            <option value="1" {{ ($settings['order_type'] ?? '1') == '1' ? 'selected' : '' }}>پیشتاز</option>
                            <option value="2" {{ ($settings['order_type'] ?? '1') == '2' ? 'selected' : '' }}>عادی</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">شناسه بسته پستی (box_id)</label>
                        <input type="number" name="box_id" value="{{ $settings['box_id'] ?? '10' }}" dir="ltr"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm" placeholder="10">
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

{{-- پردازش دسته‌ای --}}
<div class="bg-white rounded-xl shadow-sm p-6">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </div>
            <div>
                <h2 class="text-lg font-bold text-gray-900">پردازش دسته‌ای سفارشات</h2>
                <p class="text-xs text-gray-500">سفارشات پستی در مرحله «پردازش» بدون بارکد</p>
            </div>
        </div>
        <div class="flex gap-2">
            <button onclick="clearOldBarcodes()" id="btn-clear" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-medium">پاک کردن همه بارکدها</button>
            <button onclick="loadPendingOrders()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm font-medium">بارگذاری لیست</button>
            <button onclick="bulkRegister()" id="btn-bulk" class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 text-sm font-medium hidden">ثبت همه در تاپین</button>
        </div>
    </div>
    <div id="pending-orders" class="hidden">
        <div id="pending-count" class="mb-3 text-sm text-gray-600"></div>
        <div id="pending-list" class="max-h-96 overflow-y-auto border rounded-lg"></div>
    </div>
    <div id="bulk-result" class="hidden mt-4 p-4 rounded-lg text-sm"></div>
</div>

{{-- نوار اطلاعات هزینه پستی و باکس (بعد از ثبت نمایش داده میشه) --}}
<div id="shipping-info-bar" class="hidden">
    <div class="bg-blue-50 border border-blue-200 rounded-xl shadow-sm p-4 mt-4">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-sm font-bold text-blue-800">اطلاعات ثبت سفارشات در تاپین</span>
            </div>
            <button onclick="document.getElementById('shipping-info-bar').classList.add('hidden')" class="text-blue-400 hover:text-blue-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div id="shipping-info-content" class="text-sm text-blue-900"></div>
    </div>
</div>

{{-- مودال دیباگ API --}}
<div id="debug-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" style="background: rgba(0,0,0,0.5)">
    <div class="flex items-start justify-center min-h-screen pt-8 pb-8">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl mx-4">
            <div class="flex items-center justify-between p-4 border-b">
                <h3 class="text-lg font-bold text-gray-900">دیتای ارسالی و دریافتی API تاپین</h3>
                <button onclick="closeDebugModal()" class="p-1 text-gray-400 hover:text-gray-600 rounded">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div id="debug-modal-content" class="p-4 max-h-[80vh] overflow-y-auto"></div>
            <div class="flex justify-end p-4 border-t">
                <button onclick="closeDebugModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">بستن</button>
            </div>
        </div>
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
            showResult('credit-result', true, '<strong>اعتبار فروشگاه:</strong> ' + (d.formatted || d.credit || 'نامشخص'));
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

function loadPendingOrders() {
    const container = document.getElementById('pending-orders');
    const list = document.getElementById('pending-list');
    const countEl = document.getElementById('pending-count');
    const btnBulk = document.getElementById('btn-bulk');
    container.classList.remove('hidden');
    list.innerHTML = '<div class="p-4 text-center text-gray-500">در حال بارگذاری...</div>';
    btnBulk.classList.add('hidden');

    fetch('{{ route("warehouse.tapin.pending-orders") }}', { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } })
    .then(r => r.json())
    .then(data => {
        if (data.count === 0) {
            countEl.textContent = 'سفارشی در صف نیست.';
            list.innerHTML = '<div class="p-4 text-center text-gray-400">همه سفارشات ثبت شده‌اند</div>';
            return;
        }
        countEl.textContent = data.count + ' سفارش در صف ثبت';
        btnBulk.classList.remove('hidden');
        let html = '<table class="w-full text-sm"><thead class="bg-gray-50"><tr><th class="p-2 text-right">شماره</th><th class="p-2 text-right">مشتری</th><th class="p-2 text-right">موبایل</th><th class="p-2 text-right">وزن</th></tr></thead><tbody>';
        data.orders.forEach(o => {
            html += `<tr class="border-t"><td class="p-2 font-mono text-xs">${o.order_number}</td><td class="p-2">${o.customer_name}</td><td class="p-2 font-mono text-xs" dir="ltr">${o.customer_mobile||'-'}</td><td class="p-2">${o.total_weight||0}g</td></tr>`;
        });
        html += '</tbody></table>';
        list.innerHTML = html;
    })
    .catch(() => { list.innerHTML = '<div class="p-4 text-center text-red-500">خطا در بارگذاری</div>'; });
}

function clearOldBarcodes() {
    if (!confirm('همه بارکدهای سفارشات ارسال‌نشده پاک بشه؟\nبعد از پاک شدن میتونید دوباره پرینت بزنید تا در تاپین ثبت بشه.')) return;
    const btn = document.getElementById('btn-clear');
    btn.disabled = true;
    btn.textContent = 'در حال پاک‌سازی...';
    showLoading('bulk-result', 'در حال پاک کردن بارکدها...');

    fetch('{{ route("warehouse.tapin.clear-barcodes") }}', {
        method: 'POST', headers: defaultHeaders, body: '{}',
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = 'پاک کردن همه بارکدها';
        showResult('bulk-result', data.success, data.message || 'انجام شد');
        if (data.success) loadPendingOrders();
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = 'پاک کردن همه بارکدها';
        showResult('bulk-result', false, 'خطا در ارتباط');
    });
}

// ذخیره دیتای دیباگ آخرین ثبت
let lastBulkDebugData = [];

function bulkRegister() {
    if (!confirm('آیا همه سفارشات در صف رو در تاپین ثبت کنم؟')) return;
    const btn = document.getElementById('btn-bulk');
    btn.disabled = true;
    btn.textContent = 'در حال ثبت...';
    showLoading('bulk-result', 'در حال ثبت دسته‌ای سفارشات... لطفا صبر کنید');

    fetch('{{ route("warehouse.tapin.bulk-register") }}', {
        method: 'POST', headers: defaultHeaders, body: '{}',
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = 'ثبت همه در تاپین';
        if (!data.results || data.results.length === 0) {
            showResult('bulk-result', true, data.message || 'سفارشی نبود');
            return;
        }

        // ذخیره دیتای دیباگ
        lastBulkDebugData = data.results;

        // نمایش نوار اطلاعات هزینه پستی و باکس
        showShippingInfoBar(data.results);

        let html = '<div class="flex items-center justify-between mb-3"><strong>' + data.message + '</strong>';
        html += '<button onclick="showDebugModal()" class="px-3 py-1 bg-gray-700 text-white rounded text-xs hover:bg-gray-800">مشاهده دیتای API</button></div>';
        html += '<table class="w-full text-xs mt-2"><thead class="bg-gray-50"><tr><th class="p-1 text-right">سفارش</th><th class="p-1 text-right">مشتری</th><th class="p-1 text-right">وضعیت</th><th class="p-1 text-right">هزینه پستی</th><th class="p-1 text-right">باکس</th><th class="p-1 text-right">وزن</th><th class="p-1 text-right">بارکد/پیام</th></tr></thead><tbody>';
        data.results.forEach((r, idx) => {
            const color = r.status === 'success' ? 'text-green-600' : (r.status === 'skipped' ? 'text-yellow-600' : 'text-red-600');
            const statusText = r.status === 'success' ? 'موفق' : (r.status === 'skipped' ? 'اسکیپ' : 'خطا');
            const info = r.barcode || r.order_id || r.message || '-';
            const cost = r.shipping_cost ? Number(r.shipping_cost).toLocaleString('fa-IR') + ' ریال' : '-';
            const boxLabel = r.box_id ? ('باکس ' + r.box_id + (r.box_info ? ' (' + r.box_info.size + ')' : '')) : '-';
            const weight = r.weight ? (r.weight >= 1000 ? (r.weight / 1000).toFixed(1) + 'kg' : r.weight + 'g') : '-';
            html += `<tr class="border-t">
                <td class="p-1 font-mono">${r.order_number}</td>
                <td class="p-1">${r.customer || '-'}</td>
                <td class="p-1 ${color} font-bold">${statusText}</td>
                <td class="p-1">${cost}</td>
                <td class="p-1 text-xs">${boxLabel}</td>
                <td class="p-1 text-xs">${weight}</td>
                <td class="p-1 text-xs">${info}</td>
            </tr>`;
        });
        html += '</tbody></table>';
        showResult('bulk-result', true, html);
        loadPendingOrders();
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = 'ثبت همه در تاپین';
        showResult('bulk-result', false, 'خطا در ارتباط');
    });
}

function showShippingInfoBar(results) {
    const bar = document.getElementById('shipping-info-bar');
    const content = document.getElementById('shipping-info-content');
    bar.classList.remove('hidden');

    const successResults = results.filter(r => r.status === 'success');
    const totalShippingCost = successResults.reduce((sum, r) => sum + (r.shipping_cost || 0), 0);
    const boxCounts = {};
    successResults.forEach(r => {
        if (r.box_id) {
            const key = 'باکس ' + r.box_id + (r.box_info ? ' (' + r.box_info.size + ')' : '');
            boxCounts[key] = (boxCounts[key] || 0) + 1;
        }
    });

    let html = '<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">';

    // هزینه پستی کل
    html += '<div class="bg-white rounded-lg p-3 border border-blue-100">';
    html += '<div class="text-xs text-blue-600 mb-1">هزینه پستی کل (تاپین)</div>';
    html += '<div class="text-lg font-bold text-blue-900">' + (totalShippingCost > 0 ? Number(totalShippingCost).toLocaleString('fa-IR') + ' <span class="text-xs font-normal">ریال</span>' : 'نامشخص') + '</div>';
    html += '</div>';

    // تعداد سفارشات ثبت‌شده
    html += '<div class="bg-white rounded-lg p-3 border border-blue-100">';
    html += '<div class="text-xs text-blue-600 mb-1">سفارشات ثبت‌شده</div>';
    html += '<div class="text-lg font-bold text-blue-900">' + successResults.length + ' <span class="text-xs font-normal">از ' + results.length + '</span></div>';
    html += '</div>';

    // سایز کارتن‌ها
    html += '<div class="bg-white rounded-lg p-3 border border-blue-100">';
    html += '<div class="text-xs text-blue-600 mb-1">کارتن‌های استفاده‌شده</div>';
    if (Object.keys(boxCounts).length > 0) {
        html += '<div class="text-sm text-blue-900">';
        for (const [box, count] of Object.entries(boxCounts)) {
            html += '<div>' + box + ': <strong>' + count + '</strong> عدد</div>';
        }
        html += '</div>';
    } else {
        html += '<div class="text-sm text-blue-900">نامشخص</div>';
    }
    html += '</div>';

    html += '</div>';

    // هزینه به تفکیک هر سفارش
    if (successResults.some(r => r.shipping_cost)) {
        html += '<div class="mt-3 pt-3 border-t border-blue-100">';
        html += '<div class="text-xs text-blue-600 mb-2">هزینه پستی هر سفارش:</div>';
        html += '<div class="flex flex-wrap gap-2">';
        successResults.forEach(r => {
            const cost = r.shipping_cost ? Number(r.shipping_cost).toLocaleString('fa-IR') : '?';
            html += '<span class="inline-flex items-center gap-1 bg-white border border-blue-100 rounded px-2 py-1 text-xs">';
            html += '<span class="font-mono text-blue-800">' + r.order_number + '</span>';
            html += '<span class="text-blue-500">' + cost + ' ریال</span>';
            html += '</span>';
        });
        html += '</div></div>';
    }

    content.innerHTML = html;
}

function showDebugModal() {
    const modal = document.getElementById('debug-modal');
    const content = document.getElementById('debug-modal-content');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    if (!lastBulkDebugData || lastBulkDebugData.length === 0) {
        content.innerHTML = '<p class="text-gray-500">دیتای دیباگ موجود نیست</p>';
        return;
    }

    let html = '';
    lastBulkDebugData.forEach((r, idx) => {
        if (r.status === 'skipped') return;
        const debug = r._debug || {};
        const isOpen = lastBulkDebugData.length <= 3 ? 'open' : '';
        html += `<details ${isOpen} class="mb-4 border rounded-lg overflow-hidden">
            <summary class="p-3 bg-gray-50 cursor-pointer hover:bg-gray-100 flex items-center justify-between">
                <span class="font-mono text-sm font-bold">${r.order_number} - ${r.customer || ''}</span>
                <span class="text-xs ${r.status === 'success' ? 'text-green-600' : 'text-red-600'}">${r.status === 'success' ? 'موفق' : 'خطا'}</span>
            </summary>
            <div class="p-3 space-y-3">
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-bold text-orange-700 uppercase">Request Payload (ارسالی)</span>
                        <button onclick="copyToClipboard(this, 'payload-${idx}')" class="text-xs text-blue-500 hover:underline">کپی</button>
                    </div>
                    <pre id="payload-${idx}" dir="ltr" class="bg-orange-50 text-orange-900 p-3 rounded text-xs overflow-x-auto max-h-64 overflow-y-auto">${JSON.stringify(debug.payload || {}, null, 2)}</pre>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-bold text-green-700 uppercase">Response (دریافتی)</span>
                        <button onclick="copyToClipboard(this, 'response-${idx}')" class="text-xs text-blue-500 hover:underline">کپی</button>
                    </div>
                    <pre id="response-${idx}" dir="ltr" class="bg-green-50 text-green-900 p-3 rounded text-xs overflow-x-auto max-h-64 overflow-y-auto">${JSON.stringify(debug.raw_response || {}, null, 2)}</pre>
                </div>
                ${r.shipping_cost ? '<div class="text-sm"><strong>هزینه پستی:</strong> ' + Number(r.shipping_cost).toLocaleString('fa-IR') + ' ریال</div>' : ''}
                ${debug.box_id ? '<div class="text-sm"><strong>باکس تاپین:</strong> ' + debug.box_id + '</div>' : ''}
                ${debug.package_weight ? '<div class="text-sm"><strong>وزن بسته:</strong> ' + debug.package_weight + ' گرم</div>' : ''}
            </div>
        </details>`;
    });

    content.innerHTML = html || '<p class="text-gray-500">دیتای دیباگ موجود نیست</p>';
}

function closeDebugModal() {
    document.getElementById('debug-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

function copyToClipboard(btn, elementId) {
    const text = document.getElementById(elementId).textContent;
    navigator.clipboard.writeText(text).then(() => {
        const originalText = btn.textContent;
        btn.textContent = 'کپی شد!';
        setTimeout(() => btn.textContent = originalText, 1500);
    });
}
</script>
@endpush
@endsection
