@extends('layouts.admin')
@section('page-title', 'اتصال COD24')
@section('main')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-4">
            <a href="{{ route('warehouse.index') }}" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900">اتصال به COD24</h1>
                <p class="text-gray-600 mt-1">سرویس ارسال مرسولات پستی COD24</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('warehouse.tapin.index') }}" class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">تنظیمات تاپین</a>
            <a href="{{ route('warehouse.amadest.index') }}" class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">تنظیمات آمادست</a>
            <a href="{{ route('warehouse.postex.index') }}" class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">تنظیمات پستکس</a>
        </div>
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
                    <button onclick="setProvider('postex')" id="btn-postex"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition {{ ($settings['shipping_provider'] ?? 'amadest') === 'postex' ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        پستکس
                    </button>
                    <button onclick="setProvider('cod24')" id="btn-cod24"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition {{ ($settings['shipping_provider'] ?? 'amadest') === 'cod24' ? 'bg-teal-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        COD24
                    </button>
                </div>
            </div>
            <span id="provider-status" class="text-xs text-gray-500">
                @if(($settings['shipping_provider'] ?? 'amadest') === 'cod24')
                    سفارشات از طریق COD24 ثبت می‌شوند
                @elseif(($settings['shipping_provider'] ?? 'amadest') === 'postex')
                    سفارشات از طریق پستکس ثبت می‌شوند
                @elseif(($settings['shipping_provider'] ?? 'amadest') === 'tapin')
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
                <div class="w-10 h-10 bg-teal-100 rounded-lg flex items-center justify-center text-teal-700 font-bold">1</div>
                <h2 class="text-lg font-bold text-gray-900">تنظیمات API سی‌او‌دی۲۴</h2>
            </div>

            <form action="{{ route('warehouse.cod24.save') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">آدرس API</label>
                    <input type="url" name="api_url" value="{{ $settings['api_url'] ?? 'https://api.cod24.ir' }}" dir="ltr"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-sm font-mono">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نام کاربری <span class="text-red-500">*</span></label>
                        <input type="text" name="username" value="{{ $settings['username'] ?? '' }}" dir="ltr"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-sm font-mono"
                               placeholder="نام کاربری COD24">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">رمز عبور <span class="text-red-500">*</span></label>
                        <input type="password" name="password" dir="ltr"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-sm font-mono"
                               placeholder="{{ $settings['has_password'] ? 'رمز ذخیره شده — برای تغییر مقدار جدید وارد کنید' : 'رمز عبور COD24' }}">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نوع ارسال (idTypeSend)</label>
                        <select name="id_type_send" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm">
                            <option value="1" {{ ($settings['id_type_send'] ?? '1') == '1' ? 'selected' : '' }}>پیشتاز (1)</option>
                            <option value="2" {{ ($settings['id_type_send'] ?? '1') == '2' ? 'selected' : '' }}>سفارشی (2)</option>
                            <option value="3" {{ ($settings['id_type_send'] ?? '1') == '3' ? 'selected' : '' }}>ویژه (3)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">روش پرداخت (idPayMethod)</label>
                        <select name="id_pay_method" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm">
                            <option value="0" {{ ($settings['id_pay_method'] ?? '0') == '0' ? 'selected' : '' }}>پسکرایه (0)</option>
                            <option value="1" {{ ($settings['id_pay_method'] ?? '0') == '1' ? 'selected' : '' }}>پیشکرایه (1)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نوع بسته (idPacketType)</label>
                        <select name="id_packet_type" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm">
                            <option value="1" {{ ($settings['id_packet_type'] ?? '1') == '1' ? 'selected' : '' }}>بسته (1)</option>
                            <option value="2" {{ ($settings['id_packet_type'] ?? '1') == '2' ? 'selected' : '' }}>پاکت (2)</option>
                        </select>
                    </div>
                </div>

                <!-- اطلاعات فرستنده -->
                <div class="pt-3 border-t border-teal-100">
                    <h3 class="text-sm font-semibold text-teal-700 mb-3">اطلاعات فرستنده</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">نام فرستنده</label>
                            <input type="text" name="sender_name" value="{{ $settings['sender_name'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="نام و نام خانوادگی">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">موبایل فرستنده</label>
                            <input type="text" name="sender_mobile" value="{{ $settings['sender_mobile'] ?? '' }}" dir="ltr"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono" placeholder="09xxxxxxxxx">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">کد شهر پیش‌فرض مقصد</label>
                            <input type="number" id="fallback_city_code_input" name="fallback_city_code" value="{{ $settings['fallback_city_code'] ?? '' }}" dir="ltr"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="مثلاً 110">
                            <p class="text-gray-400 text-xs mt-1">اگه شهر سفارش در COD24 پیدا نشد، از این کد استفاده میشه</p>
                        </div>
                    </div>
                </div>

                <!-- نقشه شهرها -->
                <div class="pt-3 border-t border-teal-100">
                    <h3 class="text-sm font-semibold text-teal-700 mb-1">نقشه کد شهرهای دستی (اختیاری)</h3>
                    <p class="text-xs text-gray-500 mb-2">هر خط: <span dir="ltr" class="font-mono bg-gray-100 px-1">نام شهر:کد</span></p>
                    <textarea name="city_map" rows="4" dir="ltr"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono"
                              placeholder="سروستان:123&#10;شیراز:456">{{ $settings['city_map'] ?? '' }}</textarea>
                </div>

                <div class="flex items-center gap-3 pt-4 border-t">
                    <button type="submit" class="px-6 py-2.5 bg-teal-600 text-white rounded-lg hover:bg-teal-700 font-medium text-sm">ذخیره تنظیمات</button>
                    <button type="button" onclick="testConnection()" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium text-sm">تست اتصال</button>
                </div>
            </form>
            <div id="test-result" class="hidden mt-4 p-4 rounded-lg text-sm"></div>
        </div>

        <!-- Wallet & Info -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center text-emerald-700 font-bold">2</div>
                <h2 class="text-lg font-bold text-gray-900">اطلاعات حساب</h2>
            </div>

            <!-- Wallet Balance -->
            <div class="mb-6">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-medium text-gray-700">موجودی کیف پول</span>
                    <button type="button" onclick="checkWallet()" class="text-xs text-teal-600 hover:text-teal-800 underline">بررسی موجودی</button>
                </div>
                <div id="wallet-result" class="hidden p-3 rounded-lg text-sm"></div>
            </div>

            <!-- States -->
            <div class="pt-4 border-t">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-medium text-gray-700">لیست استان‌ها</span>
                    <button type="button" onclick="loadStates()" class="text-xs text-teal-600 hover:text-teal-800 underline">بارگذاری استان‌ها</button>
                </div>
                <div id="states-result" class="hidden p-3 rounded-lg text-sm max-h-64 overflow-y-auto"></div>
            </div>
        </div>
    </div>

    <!-- City Search -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
            </div>
            <div>
                <h2 class="text-lg font-bold text-gray-900">جستجوی کد شهر COD24</h2>
                <p class="text-xs text-gray-500">شهرها از API سی‌او‌دی۲۴ بارگذاری می‌شوند</p>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-xs text-gray-600 mb-2 font-medium">جستجوی شهر:</p>
                <div class="flex gap-2 mb-2">
                    <input type="text" id="city-search" dir="rtl" placeholder="نام شهر (مثلاً تهران)" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <button onclick="searchCity()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">جستجو</button>
                </div>
                <div id="cities-result" class="hidden mt-3 p-3 rounded-lg text-sm max-h-72 overflow-y-auto"></div>
            </div>
            <div>
                <p class="text-xs text-gray-600 mb-2 font-medium">جستجو با کد استان:</p>
                <div class="flex gap-2 mb-2">
                    <input type="number" id="state-code-input" dir="ltr" placeholder="کد استان (عددی)" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <button onclick="searchByState()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">شهرهای استان</button>
                </div>
                <div id="state-cities-result" class="hidden mt-3 p-3 rounded-lg text-sm max-h-72 overflow-y-auto"></div>
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
                   placeholder="بارکد COD24">
            <button onclick="trackCod24()" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium text-sm">رهگیری</button>
            <button onclick="checkBarcodeStatus()" class="px-6 py-2.5 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 font-medium text-sm">وضعیت بارکد</button>
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
    div.classList.remove('hidden', 'bg-green-50', 'text-green-800', 'bg-red-50', 'text-red-800', 'bg-gray-50', 'text-gray-600', 'bg-blue-50', 'text-blue-800');
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
    fetch('{{ route("warehouse.cod24.set-provider") }}', {
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

function testConnection() {
    showLoading('test-result', 'در حال تست اتصال...');
    fetch('{{ route("warehouse.cod24.test") }}', { method: 'POST', headers: defaultHeaders })
    .then(r => r.json())
    .then(data => showResult('test-result', data.success, (data.success ? '&#10003; ' : '') + data.message))
    .catch(() => showResult('test-result', false, 'خطا در ارتباط'));
}

function checkWallet() {
    showLoading('wallet-result', 'در حال بررسی...');
    fetch('{{ route("warehouse.cod24.wallet") }}', { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const d = data.data || {};
            showResult('wallet-result', true, '<strong>موجودی کیف پول:</strong> ' + (d.formatted || d.amount || 'نامشخص'));
        } else {
            showResult('wallet-result', false, data.message || 'خطا');
        }
    })
    .catch(() => showResult('wallet-result', false, 'خطا در ارتباط'));
}

function loadStates() {
    showLoading('states-result', 'در حال بارگذاری...');
    fetch('{{ route("warehouse.cod24.states") }}', { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.data && data.data.length > 0) {
            let html = '<div class="text-xs font-bold text-gray-700 mb-2">' + data.count + ' استان یافت شد</div>';
            html += '<table class="w-full text-xs"><thead class="bg-gray-50"><tr><th class="p-2 text-right">کد</th><th class="p-2 text-right">نام استان</th></tr></thead><tbody>';
            data.data.forEach(s => {
                const code = s.code ?? s.stateCode ?? s.id ?? '-';
                const name = s.name ?? s.stateName ?? s.title ?? '-';
                html += `<tr class="border-t cursor-pointer hover:bg-teal-50" onclick="document.getElementById('state-code-input').value='${code}'">
                    <td class="p-2 font-mono font-bold text-teal-700">${code}</td><td class="p-2">${name}</td></tr>`;
            });
            html += '</tbody></table>';
            const div = document.getElementById('states-result');
            div.classList.remove('hidden','bg-red-50','text-red-800','bg-gray-50','text-gray-600');
            div.classList.add('bg-blue-50','text-blue-800');
            div.innerHTML = html;
        } else {
            showResult('states-result', false, 'استانی یافت نشد');
        }
    })
    .catch(() => showResult('states-result', false, 'خطا در ارتباط'));
}

function searchCity() {
    const q = (document.getElementById('city-search').value || '').trim();
    if (!q) { showResult('cities-result', false, 'نام شهر را وارد کنید'); return; }
    showLoading('cities-result', 'در حال جستجو...');
    fetch('{{ route("warehouse.cod24.cities") }}?search=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.data && data.data.length > 0) {
            let html = '<div class="text-xs font-bold mb-2">' + data.count + ' شهر یافت شد</div>';
            html += '<table class="w-full text-xs"><thead class="bg-gray-50"><tr><th class="p-1 text-right">کد</th><th class="p-1 text-right">نام</th></tr></thead><tbody>';
            data.data.forEach(c => {
                const code = c.code ?? c.cityCode ?? c.id ?? '-';
                const name = c.name ?? c.cityName ?? c.title ?? '-';
                html += `<tr class="border-t cursor-pointer hover:bg-blue-50" onclick="document.getElementById('fallback_city_code_input').value='${code}'">
                    <td class="p-1 font-mono font-bold text-blue-700">${code}</td><td class="p-1">${name}</td></tr>`;
            });
            html += '</tbody></table><p class="text-xs text-gray-400 mt-2">روی هر ردیف کلیک کنید تا کد شهر کپی شود</p>';
            const div = document.getElementById('cities-result');
            div.classList.remove('hidden','bg-red-50','text-red-800','bg-gray-50','text-gray-600');
            div.classList.add('bg-blue-50','text-blue-800');
            div.innerHTML = html;
        } else {
            showResult('cities-result', false, 'شهری یافت نشد');
        }
    }).catch(() => showResult('cities-result', false, 'خطا در ارتباط'));
}

function searchByState() {
    const code = (document.getElementById('state-code-input').value || '').trim();
    if (!code) { showResult('state-cities-result', false, 'کد استان را وارد کنید'); return; }
    showLoading('state-cities-result', 'در حال بارگذاری...');
    fetch('{{ route("warehouse.cod24.cities") }}?state_code=' + encodeURIComponent(code), { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.data && data.data.length > 0) {
            let html = '<div class="text-xs font-bold mb-2">' + data.count + ' شهر در این استان</div>';
            html += '<table class="w-full text-xs"><thead class="bg-gray-50"><tr><th class="p-1 text-right">کد</th><th class="p-1 text-right">نام</th></tr></thead><tbody>';
            data.data.forEach(c => {
                const code = c.code ?? c.cityCode ?? c.id ?? '-';
                const name = c.name ?? c.cityName ?? c.title ?? '-';
                html += `<tr class="border-t cursor-pointer hover:bg-green-50" onclick="document.getElementById('fallback_city_code_input').value='${code}'">
                    <td class="p-1 font-mono font-bold text-green-700">${code}</td><td class="p-1">${name}</td></tr>`;
            });
            html += '</tbody></table>';
            const div = document.getElementById('state-cities-result');
            div.classList.remove('hidden','bg-red-50','text-red-800','bg-gray-50','text-gray-600');
            div.classList.add('bg-green-50','text-green-800');
            div.innerHTML = html;
        } else {
            showResult('state-cities-result', false, 'شهری یافت نشد');
        }
    }).catch(() => showResult('state-cities-result', false, 'خطا در ارتباط'));
}

function trackCod24() {
    const code = document.getElementById('tracking-code').value;
    if (!code) { showResult('tracking-result', false, 'بارکد وارد کنید'); return; }
    showLoading('tracking-result', 'در حال رهگیری...');
    fetch('{{ route("warehouse.cod24.track") }}', {
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

function checkBarcodeStatus() {
    const code = document.getElementById('tracking-code').value;
    if (!code) { showResult('tracking-result', false, 'بارکد وارد کنید'); return; }
    showLoading('tracking-result', 'در حال بررسی وضعیت...');
    fetch('{{ route("warehouse.cod24.barcode-status") }}', {
        method: 'POST', headers: defaultHeaders,
        body: JSON.stringify({ barcode: code }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showResult('tracking-result', true, '<pre dir="ltr" class="text-xs mt-1">' + JSON.stringify(data.data, null, 2) + '</pre>');
        } else {
            showResult('tracking-result', false, data.message || 'خطا');
        }
    })
    .catch(() => showResult('tracking-result', false, 'خطا در ارتباط'));
}
</script>
@endpush
@endsection
