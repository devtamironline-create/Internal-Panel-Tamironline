@extends('layouts.admin')
@section('page-title', 'اتصال آمادست')
@section('main')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-4">
            <a href="{{ route('warehouse.index') }}" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900">اتصال به آمادست</h1>
                <p class="text-gray-600 mt-1">مدیریت ارسال و رهگیری مرسولات</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Step 1: API Settings Card -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center text-orange-700 font-bold">1</div>
                <h2 class="text-lg font-bold text-gray-900">تنظیمات API</h2>
            </div>

            <form action="{{ route('warehouse.amadest.save') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">سرویس API</label>
                    <select name="api_url" dir="ltr"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 text-sm bg-white">
                        <option value="https://shop-integration.amadast.com" {{ ($settings['api_url'] ?? '') === 'https://shop-integration.amadast.com' ? 'selected' : '' }}>
                            shop-integration.amadast.com
                        </option>
                        <option value="https://api.amadest.com" {{ ($settings['api_url'] ?? '') === 'https://api.amadest.com' ? 'selected' : '' }}>
                            api.amadest.com
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">کد کلاینت (X-Client-Code) <span class="text-gray-400 text-xs font-normal">اختیاری</span></label>
                    <input type="text" name="client_code" value="{{ old('client_code', $settings['client_code'] ?? '') }}" dir="ltr"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 text-sm font-mono"
                           placeholder="اگه دارید وارد کنید - اجباری نیست">
                    <p class="text-gray-400 text-xs mt-1">اگه آمادست بهتون ایمیل کرده وارد کنید. اگه ندارید خالی بذارید - فقط توکن کافیه.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">توکن (Bearer Token)</label>
                    <input type="password" name="api_key" dir="ltr"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 text-sm font-mono"
                           placeholder="{{ $settings['has_token'] ? 'توکن ذخیره شده - برای تغییر مقدار جدید وارد کنید' : 'اختیاری - اگه توکن دارید اینجا وارد کنید' }}">
                    <p class="text-gray-400 text-xs mt-1">اگه توکن رو از قبل دارید اینجا وارد کنید. در غیر این صورت از بخش احراز هویت بگیرید.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">شناسه کاربر (user_id)</label>
                    <input type="text" name="user_id" value="{{ old('user_id', $settings['user_id'] ?? '') }}" dir="ltr"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 text-sm font-mono"
                           placeholder="مثلا 157320">
                    <p class="text-gray-400 text-xs mt-1">برای تمدید خودکار توکن لازمه. اگه ندارید از بخش احراز هویت بسازید.</p>
                </div>

                <div class="flex items-center gap-3 pt-4 border-t">
                    <button type="submit" class="px-6 py-2.5 bg-orange-600 text-white rounded-lg hover:bg-orange-700 font-medium text-sm">ذخیره تنظیمات</button>
                    <button type="button" onclick="testAmadestConnection()" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium text-sm">تست اتصال</button>
                </div>
            </form>

            <!-- Connection Test Result -->
            <div id="amadest-test-result" class="hidden mt-4 p-4 rounded-lg text-sm"></div>
        </div>

        <!-- Step 2: Authentication Card -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center text-blue-700 font-bold">2</div>
                <div>
                    <h2 class="text-lg font-bold text-gray-900">احراز هویت</h2>
                    <p class="text-xs text-gray-500 mt-0.5">ساخت کاربر و دریافت توکن خودکار</p>
                </div>
            </div>

            <!-- Token Status -->
            @if(!empty($settings['user_id']) && $settings['has_token'])
                @php $tokenExpired = ($settings['token_expires_at'] ?? 0) < time(); @endphp
                <div class="{{ $tokenExpired ? 'bg-yellow-50 border-yellow-200' : 'bg-green-50 border-green-200' }} border rounded-lg p-4 mb-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 {{ $tokenExpired ? 'text-yellow-700' : 'text-green-700' }} font-medium text-sm">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ $tokenExpired ? 'توکن منقضی شده (خودکار تمدید میشه)' : 'توکن فعال' }}
                            <span class="text-xs font-normal">(user_id: {{ $settings['user_id'] }})</span>
                        </div>
                        <button type="button" onclick="refreshToken()" class="text-xs text-blue-600 hover:text-blue-800 underline">تمدید دستی</button>
                    </div>
                </div>
            @elseif(!empty($settings['user_id']))
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                    <div class="flex items-center justify-between">
                        <span class="text-yellow-700 font-medium text-sm">کاربر ثبت شده (user_id: {{ $settings['user_id'] }}) - توکن ندارید</span>
                        <button type="button" onclick="refreshToken()" class="text-xs text-blue-600 hover:text-blue-800 underline">دریافت توکن</button>
                    </div>
                </div>
            @else
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
                    <p class="text-gray-600 text-sm">هنوز کاربری در آمادست ساخته نشده. اطلاعات زیر رو پر کن.</p>
                </div>
            @endif

            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نام کامل <span class="text-red-500">*</span></label>
                        <input type="text" id="reg-full-name" value="{{ $settings['sender_name'] ?? '' }}"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm" placeholder="نام و نام خانوادگی">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">موبایل <span class="text-red-500">*</span></label>
                        <input type="text" id="reg-mobile" value="{{ $settings['sender_mobile'] ?? '' }}" dir="ltr"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm" placeholder="09123456789">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">کد ملی</label>
                    <input type="text" id="reg-national-code" dir="ltr"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm" placeholder="0012345678">
                </div>

                <div class="flex items-center gap-3 pt-4 border-t">
                    <button type="button" onclick="registerUser()" id="register-btn"
                            class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm">
                        {{ !empty($settings['user_id']) ? 'ساخت کاربر جدید' : 'ساخت کاربر و دریافت توکن' }}
                    </button>
                    <button type="button" onclick="testAmadestConnection()" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium text-sm">تست اتصال</button>
                </div>
            </div>
            <div id="auth-result" class="hidden mt-4 p-4 rounded-lg text-sm"></div>
        </div>
    </div>

    <!-- Store Selection Card (full width) -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center text-amber-700 font-bold">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-gray-900">انتخاب فروشگاه</h2>
                    <p class="text-xs text-gray-500 mt-0.5">فروشگاهی که سفارشات از طرف اون ثبت میشه</p>
                </div>
            </div>
            <button type="button" onclick="loadStores()" class="px-4 py-2 bg-amber-100 text-amber-700 rounded-lg hover:bg-amber-200 text-sm font-medium">
                دریافت لیست فروشگاه‌ها
            </button>
        </div>

        @if(!empty($settings['store_id']))
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 text-sm text-amber-700">
                فروشگاه فعلی: <strong>store_id = {{ $settings['store_id'] }}</strong>
            </div>
        @endif

        <div id="stores-list" class="hidden">
            <div id="stores-loading" class="text-center py-6 text-gray-500 text-sm">در حال دریافت...</div>
            <div id="stores-content" class="hidden"></div>
        </div>
        <div id="stores-result" class="hidden mt-3 p-3 rounded-lg text-sm"></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Step 3: Sender Info Card -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center text-emerald-700 font-bold">3</div>
                <div>
                    <h2 class="text-lg font-bold text-gray-900">اطلاعات فرستنده</h2>
                    <p class="text-xs text-gray-500 mt-0.5">اطلاعات فرستنده برای ثبت مرسوله</p>
                </div>
            </div>

            <form action="{{ route('warehouse.amadest.save-sender') }}" method="POST" class="space-y-4">
                @csrf
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نام فرستنده <span class="text-red-500">*</span></label>
                        <input type="text" name="sender_name" value="{{ $settings['sender_name'] ?? '' }}"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm" placeholder="نام فرستنده روی بسته" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">موبایل فرستنده <span class="text-red-500">*</span></label>
                        <input type="text" name="sender_mobile" value="{{ $settings['sender_mobile'] ?? '' }}" dir="ltr"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm" placeholder="09123456789" required>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">آدرس انبار</label>
                    <textarea name="warehouse_address" rows="2"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm"
                              placeholder="آدرس کامل انبار">{{ $settings['warehouse_address'] ?? '' }}</textarea>
                </div>
                <div class="pt-4 border-t">
                    <button type="submit" class="w-full px-6 py-2.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 font-medium text-sm">ذخیره اطلاعات فرستنده</button>
                </div>
            </form>
        </div>

        <!-- Tracking Card -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <h2 class="text-lg font-bold text-gray-900">رهگیری مرسوله</h2>
            </div>

            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">شماره سفارش یا شماره موبایل</label>
                    <input type="text" id="tracking-code" dir="ltr"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm"
                           placeholder="شماره سفارش، کد رهگیری یا شماره موبایل">
                </div>
                <button onclick="trackShipment()" class="w-full px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium text-sm">رهگیری</button>
            </div>
            <div id="tracking-result" class="hidden mt-4 p-4 rounded-lg text-sm"></div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const csrfToken = '{{ csrf_token() }}';
const defaultHeaders = { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' };

function showResult(divId, success, message) {
    const div = document.getElementById(divId);
    div.classList.remove('hidden', 'bg-green-50', 'text-green-800', 'bg-red-50', 'text-red-800', 'bg-gray-50', 'text-gray-600', 'bg-yellow-50', 'text-yellow-800');
    div.classList.add(success ? 'bg-green-50' : 'bg-red-50', success ? 'text-green-800' : 'text-red-800');
    div.innerHTML = message;
}

function showLoading(divId, text) {
    const div = document.getElementById(divId);
    div.classList.remove('hidden', 'bg-green-50', 'text-green-800', 'bg-red-50', 'text-red-800');
    div.classList.add('bg-gray-50', 'text-gray-600');
    div.textContent = text || 'در حال پردازش...';
}

function registerUser() {
    const fullName = document.getElementById('reg-full-name').value;
    const mobile = document.getElementById('reg-mobile').value;
    const nationalCode = document.getElementById('reg-national-code').value;

    if (!fullName || !mobile) {
        showResult('auth-result', false, 'نام و شماره موبایل الزامی است.');
        return;
    }

    showLoading('auth-result', 'در حال ساخت کاربر و دریافت توکن...');

    fetch('{{ route("warehouse.amadest.register-user") }}', {
        method: 'POST', headers: defaultHeaders,
        body: JSON.stringify({ full_name: fullName, mobile: mobile, national_code: nationalCode || null }),
    })
    .then(r => r.json())
    .then(data => {
        showResult('auth-result', data.success, (data.success ? '&#10003; ' : '') + (data.message || 'خطا'));
        if (data.success) setTimeout(() => location.reload(), 1500);
    })
    .catch(() => showResult('auth-result', false, 'خطا در ارتباط با سرور'));
}

function refreshToken() {
    showLoading('auth-result', 'در حال تمدید توکن...');

    fetch('{{ route("warehouse.amadest.refresh-token") }}', {
        method: 'POST', headers: defaultHeaders,
    })
    .then(r => r.json())
    .then(data => {
        showResult('auth-result', data.success, (data.success ? '&#10003; ' : '') + (data.message || 'خطا'));
        if (data.success) setTimeout(() => location.reload(), 1500);
    })
    .catch(() => showResult('auth-result', false, 'خطا در ارتباط با سرور'));
}

function testAmadestConnection() {
    showLoading('amadest-test-result', 'در حال تست اتصال...');

    fetch('{{ route("warehouse.amadest.test") }}', { method: 'POST', headers: defaultHeaders })
    .then(r => r.json())
    .then(data => showResult('amadest-test-result', data.success, (data.success ? '&#10003; ' : '') + data.message))
    .catch(() => showResult('amadest-test-result', false, 'خطا در ارتباط با سرور'));
}

function trackShipment() {
    const code = document.getElementById('tracking-code').value;
    if (!code) { showResult('tracking-result', false, 'لطفا کد رهگیری را وارد کنید.'); return; }

    showLoading('tracking-result', 'در حال رهگیری...');

    fetch('{{ route("warehouse.amadest.track") }}', {
        method: 'POST', headers: defaultHeaders,
        body: JSON.stringify({ tracking_code: code }),
    })
    .then(r => r.json())
    .then(data => {
        if ((data.success || data.data) && data.data && data.data.length > 0) {
            let html = '';
            data.data.forEach(o => {
                const statusMap = {
                    'pending': 'در انتظار', 'processing': 'در حال پردازش', 'shipped': 'ارسال شده',
                    'delivered': 'تحویل شده', 'cancelled': 'لغو شده', 'returned': 'مرجوعی'
                };
                const status = statusMap[o.status] || o.status || '-';
                html += `<div class="border border-gray-200 rounded-lg p-4 mb-3">
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div><span class="text-gray-500">شماره سفارش:</span> <strong>${o.external_order_id || '-'}</strong></div>
                        <div><span class="text-gray-500">وضعیت:</span> <strong class="text-blue-600">${status}</strong></div>
                        <div><span class="text-gray-500">گیرنده:</span> ${o.recipient_name || '-'}</div>
                        <div><span class="text-gray-500">موبایل:</span> <span dir="ltr">${o.recipient_mobile || '-'}</span></div>
                        <div><span class="text-gray-500">کد پیگیری آمادست:</span> <strong class="text-green-600">${o.amadast_tracking_code || '-'}</strong></div>
                        <div><span class="text-gray-500">بارکد پست:</span> <strong class="text-orange-600">${o.courier_tracking_code || '-'}</strong></div>
                        <div><span class="text-gray-500">سرویس:</span> ${o.courier_title || '-'}</div>
                        <div><span class="text-gray-500">وزن:</span> ${o.weight ? o.weight + ' گرم' : '-'}</div>
                        ${o.recipient_address ? '<div class="col-span-2"><span class="text-gray-500">آدرس:</span> ' + o.recipient_address + '</div>' : ''}
                    </div>
                </div>`;
            });
            showResult('tracking-result', true, html);
        } else if (data.success && (!data.data || data.data.length === 0)) {
            showResult('tracking-result', false, 'سفارشی با این مشخصات یافت نشد.');
        } else {
            showResult('tracking-result', false, data.message || 'خطا در جستجو');
        }
    })
    .catch(() => showResult('tracking-result', false, 'خطا در ارتباط با سرور'));
}

function loadStores() {
    const list = document.getElementById('stores-list');
    const loading = document.getElementById('stores-loading');
    const content = document.getElementById('stores-content');
    list.classList.remove('hidden');
    loading.classList.remove('hidden');
    content.classList.add('hidden');

    fetch('{{ route("warehouse.amadest.stores") }}', { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } })
    .then(r => r.json())
    .then(data => {
        loading.classList.add('hidden');
        content.classList.remove('hidden');
        const stores = data.data || [];
        if (!stores.length) {
            content.innerHTML = '<p class="text-gray-500 text-sm py-4 text-center">فروشگاهی یافت نشد.</p>';
            return;
        }
        const currentStoreId = '{{ $settings["store_id"] ?? "" }}';
        let html = '<div class="divide-y divide-gray-100">';
        stores.forEach(s => {
            const isActive = String(s.id) === currentStoreId;
            html += `<div class="flex items-center justify-between py-3 px-2 ${isActive ? 'bg-amber-50 rounded-lg' : ''}">
                <div>
                    <span class="font-medium text-sm text-gray-900">${s.title || 'بدون نام'}</span>
                    <span class="text-xs text-gray-400 mr-2">ID: ${s.id}</span>
                    ${s.admin_name ? '<span class="text-xs text-gray-500 mr-2">(' + s.admin_name + ')</span>' : ''}
                    ${s.phone ? '<span class="text-xs text-gray-400 mr-1">' + s.phone + '</span>' : ''}
                </div>
                <button onclick="selectStore(${s.id}, '${(s.title || '').replace(/'/g, "\\'")}')"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium ${isActive ? 'bg-amber-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}">
                    ${isActive ? 'فعال' : 'انتخاب'}
                </button>
            </div>`;
        });
        html += '</div>';
        content.innerHTML = html;
    })
    .catch(() => {
        loading.classList.add('hidden');
        content.classList.remove('hidden');
        content.innerHTML = '<p class="text-red-500 text-sm py-4 text-center">خطا در دریافت لیست فروشگاه‌ها</p>';
    });
}

function selectStore(storeId, title) {
    fetch('{{ route("warehouse.amadest.select-store") }}', {
        method: 'POST', headers: defaultHeaders,
        body: JSON.stringify({ store_id: storeId }),
    })
    .then(r => r.json())
    .then(data => {
        showResult('stores-result', data.success, (data.success ? '&#10003; ' : '') + (data.message || 'خطا') + (title ? ' - ' + title : ''));
        if (data.success) setTimeout(() => location.reload(), 1000);
    })
    .catch(() => showResult('stores-result', false, 'خطا در ارتباط با سرور'));
}
</script>
@endpush
@endsection
