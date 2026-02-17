@extends('layouts.admin')
@section('page-title', 'اتصال پستکس')
@section('main')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-4">
            <a href="{{ route('warehouse.index') }}" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900">اتصال به پستکس</h1>
                <p class="text-gray-600 mt-1">سرویس ارسال مرسولات پستی</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('warehouse.tapin.index') }}" class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">تنظیمات تاپین</a>
            <a href="{{ route('warehouse.amadest.index') }}" class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">تنظیمات آمادست</a>
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
                </div>
            </div>
            <span id="provider-status" class="text-xs text-gray-500">
                @if(($settings['shipping_provider'] ?? 'amadest') === 'postex')
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
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center text-purple-700 font-bold">1</div>
                <h2 class="text-lg font-bold text-gray-900">تنظیمات API پستکس</h2>
            </div>

            <form action="{{ route('warehouse.postex.save') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">آدرس API</label>
                    <input type="url" name="api_url" value="{{ $settings['api_url'] ?? 'https://api.postex.ir' }}" dir="ltr"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">API Key (کلید دسترسی) <span class="text-red-500">*</span></label>
                    <input type="password" name="api_key" dir="ltr"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-sm font-mono"
                           placeholder="{{ $settings['has_key'] ? 'کلید ذخیره شده - برای تغییر مقدار جدید وارد کنید' : 'کلید API پستکس' }}">
                    @if(!empty($settings['key_preview']))
                        <p class="text-purple-500 text-xs mt-1 font-mono" dir="ltr">ذخیره شده: {{ $settings['key_preview'] }}</p>
                    @else
                        <p class="text-gray-400 text-xs mt-1">از پنل پستکس بخش API بگیرید.</p>
                    @endif
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نوع جمع‌آوری مرسوله</label>
                    <select name="collection_type" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm">
                        <option value="pick_up" {{ ($settings['collection_type'] ?? 'pick_up') == 'pick_up' ? 'selected' : '' }}>پیک‌آپ - پیک می‌آید (Pick Up)</option>
                        <option value="courier_drop_off" {{ ($settings['collection_type'] ?? 'pick_up') == 'courier_drop_off' ? 'selected' : '' }}>تحویل به پیک (Courier Drop Off)</option>
                        <option value="postex_drop_off" {{ ($settings['collection_type'] ?? 'pick_up') == 'postex_drop_off' ? 'selected' : '' }}>تحویل به نمایندگی پستکس - نیاز به نمایندگی در شهر مبدا (Postex Drop Off)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نوع پیک (courier) <span class="text-red-500">*</span></label>
                    <select name="courier" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm">
                        <option value="post" {{ ($settings['courier'] ?? 'post') == 'post' ? 'selected' : '' }}>پست (post)</option>
                        <option value="pishtaz" {{ ($settings['courier'] ?? 'post') == 'pishtaz' ? 'selected' : '' }}>پیشتاز (pishtaz)</option>
                        <option value="mahex" {{ ($settings['courier'] ?? 'post') == 'mahex' ? 'selected' : '' }}>ماهکس (mahex)</option>
                        <option value="snap" {{ ($settings['courier'] ?? 'post') == 'snap' ? 'selected' : '' }}>اسنپ (snap)</option>
                        <option value="tipax" {{ ($settings['courier'] ?? 'post') == 'tipax' ? 'selected' : '' }}>تیپاکس (tipax)</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نوع پرداخت (paymenttype)</label>
                        <select name="payment_type" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm">
                            <option value="0" {{ ($settings['payment_type'] ?? '0') == '0' ? 'selected' : '' }}>0 - پیش‌پرداخت</option>
                            <option value="1" {{ ($settings['payment_type'] ?? '0') == '1' ? 'selected' : '' }}>1 - پرداخت در محل (COD)</option>
                            <option value="2" {{ ($settings['payment_type'] ?? '0') == '2' ? 'selected' : '' }}>2 - اعتباری</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نوع سرویس (servicetype)</label>
                        <select name="service_type" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm">
                            <option value="0" {{ ($settings['service_type'] ?? '0') == '0' ? 'selected' : '' }}>0 - عادی</option>
                            <option value="1" {{ ($settings['service_type'] ?? '0') == '1' ? 'selected' : '' }}>1 - ویژه / سریع</option>
                            <option value="2" {{ ($settings['service_type'] ?? '0') == '2' ? 'selected' : '' }}>2 - اقتصادی</option>
                        </select>
                    </div>
                </div>

                <!-- اطلاعات فرستنده (مبدا) - فیلدهای اجباری API -->
                <div class="pt-3 border-t border-purple-100">
                    <h3 class="text-sm font-semibold text-purple-700 mb-3">اطلاعات فرستنده (مبدا) <span class="text-red-500">*</span></h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">نام فرستنده</label>
                            <input type="text" name="from_name" value="{{ $settings['from_name'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="نام و نام خانوادگی">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">موبایل فرستنده (mobileno)</label>
                            <input type="text" name="from_phone" value="{{ $settings['from_phone'] ?? '' }}" dir="ltr"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono" placeholder="09xxxxxxxxx">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">تلفن ثابت فرستنده (telephoneno)</label>
                            <input type="text" name="from_telephone" value="{{ $settings['from_telephone'] ?? '' }}" dir="ltr"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono" placeholder="02100000000">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">کد شهر مبدا</label>
                            <input type="number" name="from_city_code" value="{{ $settings['from_city_code'] ?? '444' }}" dir="ltr"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="444">
                            <p class="text-gray-400 text-xs mt-1">تهران: 444</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">کد پستی مبدا (۱۰ رقم)</label>
                            <input type="text" name="from_postcode" value="{{ $settings['from_postcode'] ?? '' }}" dir="ltr"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono" maxlength="10" placeholder="1234567890">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="block text-xs font-medium text-gray-600 mb-1">آدرس فرستنده</label>
                        <input type="text" name="from_address" value="{{ $settings['from_address'] ?? '' }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="تهران، خیابان ...">
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-4 border-t">
                    <button type="submit" class="px-6 py-2.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-medium text-sm">ذخیره تنظیمات</button>
                    <button type="button" onclick="testPostexConnection()" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium text-sm">تست اتصال</button>
                    <button type="button" onclick="debugCreateShipment()" class="px-6 py-2.5 bg-orange-100 text-orange-700 rounded-lg hover:bg-orange-200 font-medium text-sm">تست ثبت سفارش</button>
                    <button type="button" onclick="probeEndpoints()" class="px-6 py-2.5 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 font-medium text-sm">کشف endpoint</button>
                </div>
            </form>
            <div id="postex-test-result" class="hidden mt-4 p-4 rounded-lg text-sm"></div>

            <!-- Debug Create Form -->
            <div id="debug-create-section" class="hidden mt-4 p-4 bg-orange-50 border border-orange-200 rounded-lg">
                <h3 class="text-sm font-bold text-orange-800 mb-3">تست ثبت سفارش در پستکس (با داده نمونه)</h3>
                <div class="grid grid-cols-3 gap-3 mb-3">
                    <div>
                        <label class="text-xs text-gray-600">کد پستی (۱۰ رقم)</label>
                        <input type="text" id="debug-postcode" value="1234567890" dir="ltr" class="w-full mt-1 px-3 py-2 border rounded text-sm font-mono">
                    </div>
                    <div>
                        <label class="text-xs text-gray-600">شهر</label>
                        <input type="text" id="debug-city" value="تهران" class="w-full mt-1 px-3 py-2 border rounded text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-600">استان</label>
                        <input type="text" id="debug-state" value="تهران" class="w-full mt-1 px-3 py-2 border rounded text-sm">
                    </div>
                </div>
                <button onclick="runDebugCreate()" class="px-4 py-2 bg-orange-600 text-white rounded text-sm font-medium">اجرای تست</button>
                <pre id="debug-create-result" class="hidden mt-3 p-3 bg-gray-900 text-green-400 text-xs rounded overflow-auto max-h-64 text-left" dir="ltr"></pre>
            </div>
        </div>

        <!-- Wallet & Profile -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center text-emerald-700 font-bold">2</div>
                <h2 class="text-lg font-bold text-gray-900">اطلاعات حساب</h2>
            </div>

            <!-- Wallet Balance -->
            <div class="mb-6">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-medium text-gray-700">موجودی کیف پول</span>
                    <button type="button" onclick="checkWallet()" class="text-xs text-purple-600 hover:text-purple-800 underline">بررسی موجودی</button>
                </div>
                <div id="wallet-result" class="hidden p-3 rounded-lg text-sm"></div>
            </div>

            <!-- User Profile -->
            <div class="pt-4 border-t">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-medium text-gray-700">پروفایل کاربری</span>
                    <button type="button" onclick="checkProfile()" class="text-xs text-purple-600 hover:text-purple-800 underline">مشاهده پروفایل</button>
                </div>
                <div id="profile-result" class="hidden p-3 rounded-lg text-sm"></div>
            </div>
        </div>
    </div>

    <!-- Geography Tools -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
            </div>
            <h2 class="text-lg font-bold text-gray-900">استان‌ها و شهرها</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <button onclick="loadProvinces()" class="w-full px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm">بارگذاری استان‌ها</button>
                <div id="provinces-result" class="hidden mt-4 p-4 rounded-lg text-sm max-h-96 overflow-y-auto"></div>
            </div>
            <div>
                <div class="flex gap-2">
                    <input type="number" id="province-code" dir="ltr" placeholder="کد استان" class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm">
                    <button onclick="loadCities()" class="px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm">بارگذاری شهرها</button>
                </div>
                <div id="cities-result" class="hidden mt-4 p-4 rounded-lg text-sm max-h-96 overflow-y-auto"></div>
            </div>
        </div>
    </div>

    <!-- Price Calculator -->
    <div class="bg-white rounded-xl shadow-sm p-6 max-w-2xl">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </div>
            <h2 class="text-lg font-bold text-gray-900">محاسبه هزینه ارسال</h2>
        </div>
        <div class="grid grid-cols-2 gap-3 mb-3">
            <input type="number" id="calc-to-city" dir="ltr" placeholder="کد شهر مقصد" class="px-4 py-2.5 border border-gray-300 rounded-lg text-sm">
            <input type="number" id="calc-weight" dir="ltr" placeholder="وزن (گرم)" class="px-4 py-2.5 border border-gray-300 rounded-lg text-sm">
        </div>
        <button onclick="calculatePrice()" class="w-full px-6 py-2.5 bg-orange-600 text-white rounded-lg hover:bg-orange-700 font-medium text-sm">محاسبه هزینه</button>
        <div id="price-result" class="hidden mt-4 p-4 rounded-lg text-sm"></div>
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
                   placeholder="کد رهگیری">
            <button onclick="trackPostex()" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium text-sm">رهگیری</button>
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
    fetch('{{ route("warehouse.postex.set-provider") }}', {
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

function testPostexConnection() {
    showLoading('postex-test-result', 'در حال تست اتصال...');
    fetch('{{ route("warehouse.postex.test") }}', { method: 'POST', headers: defaultHeaders })
    .then(r => r.json())
    .then(data => showResult('postex-test-result', data.success, (data.success ? '&#10003; ' : '') + data.message))
    .catch(() => showResult('postex-test-result', false, 'خطا در ارتباط'));
}

function checkWallet() {
    showLoading('wallet-result', 'در حال بررسی...');
    fetch('{{ route("warehouse.postex.wallet") }}', { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const d = data.data || {};
            showResult('wallet-result', true, '<strong>موجودی کیف پول:</strong> ' + (d.formatted || d.balance || 'نامشخص'));
        } else {
            showResult('wallet-result', false, data.message || 'خطا');
        }
    })
    .catch(() => showResult('wallet-result', false, 'خطا در ارتباط'));
}

function checkProfile() {
    showLoading('profile-result', 'در حال بررسی...');
    fetch('{{ route("warehouse.postex.profile") }}', { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const div = document.getElementById('profile-result');
            div.classList.remove('hidden', 'bg-green-50', 'text-green-800', 'bg-red-50', 'text-red-800', 'bg-gray-50', 'text-gray-600');
            div.classList.add('bg-blue-50', 'text-blue-800');
            div.innerHTML = '<pre dir="ltr" class="text-xs mt-1">' + JSON.stringify(data.data, null, 2) + '</pre>';
        } else {
            showResult('profile-result', false, data.message || 'خطا');
        }
    })
    .catch(() => showResult('profile-result', false, 'خطا در ارتباط'));
}

function loadProvinces() {
    showLoading('provinces-result', 'در حال بارگذاری استان‌ها...');
    fetch('{{ route("warehouse.postex.provinces") }}', { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.data && data.data.length > 0) {
            let html = '<div class="text-xs font-bold text-gray-700 mb-2">' + data.count + ' استان یافت شد</div>';
            html += '<table class="w-full text-xs"><thead class="bg-gray-50"><tr><th class="p-2 text-right">کد</th><th class="p-2 text-right">نام استان</th></tr></thead><tbody>';
            data.data.forEach(p => {
                html += `<tr class="border-t"><td class="p-2 font-mono">${p.code || p.id || '-'}</td><td class="p-2">${p.name || p.title || '-'}</td></tr>`;
            });
            html += '</tbody></table>';
            const div = document.getElementById('provinces-result');
            div.classList.remove('hidden', 'bg-green-50', 'text-green-800', 'bg-red-50', 'text-red-800', 'bg-gray-50', 'text-gray-600');
            div.classList.add('bg-blue-50', 'text-blue-800');
            div.innerHTML = html;
        } else {
            showResult('provinces-result', false, 'استانی یافت نشد');
        }
    })
    .catch(() => showResult('provinces-result', false, 'خطا در ارتباط'));
}

function loadCities() {
    const provinceCode = document.getElementById('province-code').value;
    if (!provinceCode) {
        showResult('cities-result', false, 'کد استان را وارد کنید');
        return;
    }
    showLoading('cities-result', 'در حال بارگذاری شهرها...');
    fetch('{{ route("warehouse.postex.cities") }}?province_code=' + provinceCode, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.data && data.data.length > 0) {
            let html = '<div class="text-xs font-bold text-gray-700 mb-2">' + data.count + ' شهر یافت شد</div>';
            html += '<table class="w-full text-xs"><thead class="bg-gray-50"><tr><th class="p-2 text-right">کد</th><th class="p-2 text-right">نام شهر</th></tr></thead><tbody>';
            data.data.forEach(c => {
                html += `<tr class="border-t"><td class="p-2 font-mono">${c.code || c.id || '-'}</td><td class="p-2">${c.name || c.title || '-'}</td></tr>`;
            });
            html += '</tbody></table>';
            const div = document.getElementById('cities-result');
            div.classList.remove('hidden', 'bg-green-50', 'text-green-800', 'bg-red-50', 'text-red-800', 'bg-gray-50', 'text-gray-600');
            div.classList.add('bg-blue-50', 'text-blue-800');
            div.innerHTML = html;
        } else {
            showResult('cities-result', false, 'شهری یافت نشد');
        }
    })
    .catch(() => showResult('cities-result', false, 'خطا در ارتباط'));
}

function calculatePrice() {
    const toCityCode = document.getElementById('calc-to-city').value;
    const weight = document.getElementById('calc-weight').value;
    if (!toCityCode || !weight) {
        showResult('price-result', false, 'کد شهر مقصد و وزن را وارد کنید');
        return;
    }
    showLoading('price-result', 'در حال محاسبه...');
    fetch('{{ route("warehouse.postex.calculate-price") }}', {
        method: 'POST',
        headers: defaultHeaders,
        body: JSON.stringify({
            parcels: [{
                to_city_code: parseInt(toCityCode),
                weight: parseInt(weight)
            }]
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const div = document.getElementById('price-result');
            div.classList.remove('hidden', 'bg-green-50', 'text-green-800', 'bg-red-50', 'text-red-800', 'bg-gray-50', 'text-gray-600');
            div.classList.add('bg-blue-50', 'text-blue-800');
            div.innerHTML = '<pre dir="ltr" class="text-xs mt-1">' + JSON.stringify(data.data, null, 2) + '</pre>';
        } else {
            showResult('price-result', false, data.message || 'خطا');
        }
    })
    .catch(() => showResult('price-result', false, 'خطا در ارتباط'));
}

function trackPostex() {
    const code = document.getElementById('tracking-code').value;
    if (!code) { showResult('tracking-result', false, 'کد رهگیری وارد کنید'); return; }
    showLoading('tracking-result', 'در حال رهگیری...');
    fetch('{{ route("warehouse.postex.track") }}', {
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

function debugCreateShipment() {
    const section = document.getElementById('debug-create-section');
    section.classList.toggle('hidden');
}

function runDebugCreate() {
    const postcode = document.getElementById('debug-postcode').value;
    const city     = document.getElementById('debug-city').value;
    const state    = document.getElementById('debug-state').value;
    const result   = document.getElementById('debug-create-result');

    result.classList.remove('hidden');
    result.textContent = 'در حال ارسال درخواست به پستکس...';

    fetch(`{{ route('warehouse.postex.debug-create') }}?postcode=${encodeURIComponent(postcode)}&city=${encodeURIComponent(city)}&state=${encodeURIComponent(state)}`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        result.textContent = JSON.stringify(data, null, 2);
    })
    .catch(e => {
        result.textContent = 'خطا: ' + e.message;
    });
}

function probeEndpoints() {
    const probeEl = document.getElementById('probe-result-section');
    if (probeEl) { probeEl.remove(); }

    const section = document.createElement('div');
    section.id = 'probe-result-section';
    section.className = 'mt-4 p-4 bg-red-50 border border-red-200 rounded-lg';
    section.innerHTML = '<p class="text-sm font-bold text-red-800 mb-2">در حال بررسی endpointها... (ممکنه چند ثانیه طول بکشه)</p><pre id="probe-output" class="p-3 bg-gray-900 text-green-400 text-xs rounded overflow-auto max-h-96" dir="ltr">loading...</pre>';
    document.getElementById('postex-test-result').after(section);

    fetch('{{ route('warehouse.postex.probe-endpoints') }}', {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        const out = document.getElementById('probe-output');
        let positives = '', negatives = '';
        for (const [path, res] of Object.entries(data)) {
            const status = res.status;
            const notFound = status === 404 || status === 'err';
            const ok = !notFound;
            const line = `[${status}] ${path}` + (res.url ? `\n    URL: ${res.url}` : '') + '\n';
            if (ok) {
                positives += '★ ' + line + '    body: ' + JSON.stringify(res.body).substring(0, 300) + '\n\n';
            } else {
                negatives += '  ' + line;
            }
        }
        out.textContent = positives
            ? '=== مسیرهای فعال ===\n' + positives + '\n=== مسیرهای 404 ===\n' + negatives
            : '=== همه 404 هستند ===\n' + negatives;
    })
    .catch(e => {
        document.getElementById('probe-output').textContent = 'خطا: ' + e.message;
    });
}
</script>
@endpush
@endsection
