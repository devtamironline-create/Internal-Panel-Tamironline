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
        <!-- Settings Card -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <h2 class="text-lg font-bold text-gray-900">تنظیمات API</h2>
            </div>

            <form action="{{ route('warehouse.amadest.save') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">سرویس API</label>
                    <select name="api_url" dir="ltr"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 text-sm bg-white">
                        <option value="https://shop-integration.amadast.com" {{ ($settings['api_url'] ?? '') === 'https://shop-integration.amadast.com' ? 'selected' : '' }}>
                            shop-integration.amadast.com (آمادست - نسخه فروشگاه)
                        </option>
                        <option value="https://api.amadest.com" {{ ($settings['api_url'] ?? '') === 'https://api.amadest.com' ? 'selected' : '' }}>
                            api.amadest.com (آمادست - نسخه API)
                        </option>
                    </select>
                    @error('api_url')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-gray-400 text-xs mt-1">هر دو رو تست کن، هر کدوم جواب داد همونو انتخاب کن</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">کلید API <span class="text-red-500">*</span></label>
                    <input type="password" name="api_key" value="{{ old('api_key', $settings['api_key']) }}" required dir="ltr"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 text-sm font-mono"
                           placeholder="کلید API آمادست">
                    @error('api_key')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center gap-3 pt-4 border-t">
                    <button type="submit" class="px-6 py-2.5 bg-orange-600 text-white rounded-lg hover:bg-orange-700 font-medium text-sm">ذخیره تنظیمات</button>
                    <button type="button" onclick="testAmadestConnection()" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium text-sm">تست اتصال</button>
                </div>
            </form>

            <!-- Connection Test Result -->
            <div id="amadest-test-result" class="hidden mt-4 p-4 rounded-lg text-sm"></div>
        </div>

        <!-- Store Setup Card -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-gray-900">راه‌اندازی فروشگاه</h2>
                    <p class="text-xs text-gray-500 mt-0.5">اطلاعات فرستنده و انبار برای ثبت مرسوله</p>
                </div>
            </div>

            @if(!empty($settings['store_id']))
                <!-- Already configured -->
                <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 mb-4">
                    <div class="flex items-center gap-2 text-emerald-700 font-medium text-sm mb-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        فروشگاه تنظیم شده است
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-sm text-gray-600">
                        <div>شناسه فروشگاه: <strong class="text-gray-900">{{ $settings['store_id'] }}</strong></div>
                        <div>شناسه مکان: <strong class="text-gray-900">{{ $settings['location_id'] ?? '-' }}</strong></div>
                        <div>فرستنده: <strong class="text-gray-900">{{ $settings['sender_name'] ?? '-' }}</strong></div>
                        <div>موبایل: <strong class="text-gray-900" dir="ltr">{{ $settings['sender_mobile'] ?? '-' }}</strong></div>
                    </div>
                </div>
                <p class="text-gray-500 text-xs mb-4">برای تغییر تنظیمات، فرم زیر را دوباره پر کنید.</p>
            @else
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-4">
                    <div class="flex items-center gap-2 text-amber-700 font-medium text-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                        فروشگاه هنوز تنظیم نشده - ابتدا فرم زیر را پر کنید
                    </div>
                </div>
            @endif

            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نام فرستنده <span class="text-red-500">*</span></label>
                        <input type="text" id="setup-sender-name" value="{{ $settings['sender_name'] ?? '' }}"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm"
                               placeholder="نام فرستنده روی بسته">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">موبایل فرستنده <span class="text-red-500">*</span></label>
                        <input type="text" id="setup-sender-mobile" value="{{ $settings['sender_mobile'] ?? '' }}" dir="ltr"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm"
                               placeholder="09123456789">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">آدرس انبار <span class="text-red-500">*</span></label>
                    <textarea id="setup-warehouse-address" rows="2"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm"
                              placeholder="آدرس کامل انبار">{{ $settings['warehouse_address'] ?? '' }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">شهر <span class="text-red-500">*</span></label>
                    <select id="setup-city"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm bg-white">
                        <option value="">در حال بارگذاری...</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">کد پستی انبار</label>
                        <input type="text" id="setup-postal-code" dir="ltr"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm"
                               placeholder="1234567890">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">عنوان فروشگاه</label>
                        <input type="text" id="setup-store-title"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm"
                               placeholder="فروشگاه اصلی" value="فروشگاه اصلی">
                    </div>
                </div>

                <div class="pt-4 border-t">
                    <button type="button" onclick="setupAmadest()" id="setup-btn"
                            class="w-full px-6 py-2.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 font-medium text-sm flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        {{ !empty($settings['store_id']) ? 'بروزرسانی تنظیمات' : 'راه‌اندازی فروشگاه در آمادست' }}
                    </button>
                </div>
            </div>

            <!-- Setup Result -->
            <div id="setup-result" class="hidden mt-4 p-4 rounded-lg text-sm"></div>
        </div>
    </div>

    <!-- Second Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Tracking & Info -->
        <div class="space-y-6">
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
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                               placeholder="شماره سفارش، کد رهگیری یا شماره موبایل">
                    </div>
                    <button onclick="trackShipment()" class="w-full px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium text-sm flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        رهگیری
                    </button>
                </div>

                <!-- Tracking Result -->
                <div id="tracking-result" class="hidden mt-4 p-4 rounded-lg text-sm"></div>
            </div>

            <!-- Info Card -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-sm font-bold text-gray-900 mb-3">امکانات آمادست</h3>
                <ul class="space-y-2 text-sm text-gray-600">
                    <li class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        ثبت خودکار مرسوله از پنل
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        رهگیری وضعیت مرسولات
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        محاسبه هزینه ارسال
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        دریافت کد رهگیری پستی
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
let citiesMap = {};

// Load cities on page load
document.addEventListener('DOMContentLoaded', function() {
    loadAllCities();
});

function loadAllCities() {
    const citySelect = document.getElementById('setup-city');
    citySelect.innerHTML = '<option value="">در حال بارگذاری شهرها...</option>';

    fetch('{{ route("warehouse.amadest.cities") }}', {
        headers: { 'Accept': 'application/json' },
    })
    .then(r => r.json())
    .then(data => {
        citySelect.innerHTML = '<option value="">انتخاب شهر...</option>';
        if (data.success && data.data && data.data.length > 0) {
            // Log first item to see structure
            console.log('Amadest cities sample:', data.data[0]);
            data.data.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name || c.title;
                citySelect.appendChild(opt);
                // Store full city data for province_id lookup
                citiesMap[c.id] = c;
            });
        } else {
            citySelect.innerHTML = '<option value="">شهری یافت نشد</option>';
        }
    })
    .catch(() => {
        citySelect.innerHTML = '<option value="">خطا در بارگذاری شهرها</option>';
    });
}

function setupAmadest() {
    const senderName = document.getElementById('setup-sender-name').value;
    const senderMobile = document.getElementById('setup-sender-mobile').value;
    const address = document.getElementById('setup-warehouse-address').value;
    const cityId = document.getElementById('setup-city').value;
    const postalCode = document.getElementById('setup-postal-code').value;
    const storeTitle = document.getElementById('setup-store-title').value;

    const resultDiv = document.getElementById('setup-result');
    resultDiv.classList.remove('hidden', 'bg-green-50', 'text-green-800', 'bg-red-50', 'text-red-800');

    if (!senderName || !senderMobile || !address || !cityId) {
        resultDiv.classList.add('bg-red-50', 'text-red-800');
        resultDiv.textContent = 'لطفا تمام فیلدهای ضروری (*) را پر کنید.';
        return;
    }

    resultDiv.classList.add('bg-gray-50', 'text-gray-600');
    resultDiv.textContent = 'در حال راه‌اندازی...';

    const btn = document.getElementById('setup-btn');
    btn.disabled = true;
    btn.classList.add('opacity-50');

    fetch('{{ route("warehouse.amadest.setup") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            sender_name: senderName,
            sender_mobile: senderMobile,
            warehouse_address: address,
            province_id: parseInt(citiesMap[cityId]?.province_id || citiesMap[cityId]?.province || citiesMap[cityId]?.state_id || cityId),
            city_id: parseInt(cityId),
            postal_code: postalCode || null,
            store_title: storeTitle || 'فروشگاه اصلی',
        }),
    })
    .then(r => r.json())
    .then(data => {
        resultDiv.classList.remove('bg-gray-50', 'text-gray-600');
        if (data.success) {
            resultDiv.classList.add('bg-green-50', 'text-green-800');
            let msg = '<strong>&#10003; ' + data.message + '</strong>';
            if (data.data) {
                msg += '<br>شناسه فروشگاه: ' + (data.data.store_id || '-');
                msg += ' | شناسه مکان: ' + (data.data.location_id || '-');
            }
            resultDiv.innerHTML = msg;
            setTimeout(() => location.reload(), 2000);
        } else {
            resultDiv.classList.add('bg-red-50', 'text-red-800');
            resultDiv.textContent = data.message || 'خطا در راه‌اندازی';
        }
    })
    .catch(err => {
        resultDiv.classList.remove('bg-gray-50', 'text-gray-600');
        resultDiv.classList.add('bg-red-50', 'text-red-800');
        resultDiv.textContent = 'خطا در ارتباط با سرور';
    })
    .finally(() => {
        btn.disabled = false;
        btn.classList.remove('opacity-50');
    });
}

function testAmadestConnection() {
    const resultDiv = document.getElementById('amadest-test-result');
    resultDiv.classList.remove('hidden', 'bg-green-50', 'text-green-800', 'bg-red-50', 'text-red-800');
    resultDiv.classList.add('bg-gray-50', 'text-gray-600');
    resultDiv.textContent = 'در حال تست اتصال...';

    fetch('{{ route("warehouse.amadest.test") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
    })
    .then(r => r.json())
    .then(data => {
        resultDiv.classList.remove('bg-gray-50', 'text-gray-600');
        if (data.success) {
            resultDiv.classList.add('bg-green-50', 'text-green-800');
            resultDiv.innerHTML = '<strong>&#10003; ' + data.message + '</strong>';
        } else {
            resultDiv.classList.add('bg-red-50', 'text-red-800');
            resultDiv.textContent = data.message;
        }
    })
    .catch(err => {
        resultDiv.classList.remove('bg-gray-50', 'text-gray-600');
        resultDiv.classList.add('bg-red-50', 'text-red-800');
        resultDiv.textContent = 'خطا در ارتباط با سرور';
    });
}

function trackShipment() {
    const code = document.getElementById('tracking-code').value;
    const resultDiv = document.getElementById('tracking-result');

    if (!code) {
        resultDiv.classList.remove('hidden', 'bg-green-50', 'text-green-800');
        resultDiv.classList.add('bg-red-50', 'text-red-800');
        resultDiv.textContent = 'لطفا کد رهگیری را وارد کنید.';
        return;
    }

    resultDiv.classList.remove('hidden', 'bg-green-50', 'text-green-800', 'bg-red-50', 'text-red-800');
    resultDiv.classList.add('bg-gray-50', 'text-gray-600');
    resultDiv.textContent = 'در حال رهگیری...';

    fetch('{{ route("warehouse.amadest.track") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ tracking_code: code }),
    })
    .then(r => r.json())
    .then(data => {
        resultDiv.classList.remove('bg-gray-50', 'text-gray-600');
        if (data.success) {
            resultDiv.classList.add('bg-green-50', 'text-green-800');
            resultDiv.innerHTML = '<strong>&#10003; اطلاعات مرسوله:</strong><br>' + JSON.stringify(data.data, null, 2);
        } else {
            resultDiv.classList.add('bg-red-50', 'text-red-800');
            resultDiv.textContent = data.message;
        }
    })
    .catch(err => {
        resultDiv.classList.remove('bg-gray-50', 'text-gray-600');
        resultDiv.classList.add('bg-red-50', 'text-red-800');
        resultDiv.textContent = 'خطا در ارتباط با سرور';
    });
}
</script>
@endpush
@endsection
