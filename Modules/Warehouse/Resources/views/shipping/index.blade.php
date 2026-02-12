@extends('layouts.admin')
@section('page-title', 'روش‌های حمل‌ونقل')
@section('main')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-4">
            <a href="{{ route('warehouse.index') }}" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900">روش‌های حمل‌ونقل</h1>
                <p class="text-gray-600 mt-1">مدیریت روش‌های ارسال ووکامرس و نقشه‌برداری به انواع داخلی</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="syncShippingMethods()" id="sync-btn" class="px-5 py-2.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-medium text-sm flex items-center gap-2">
                <svg class="w-5 h-5" id="sync-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span id="sync-text">دریافت از ووکامرس</span>
            </button>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-4 text-center">
            <div class="text-2xl font-bold text-purple-700">{{ $wcMethods->count() }}</div>
            <div class="text-xs text-gray-500 mt-1">روش ارسال</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 text-center">
            <div class="text-2xl font-bold text-blue-700">{{ $wcMethods->pluck('zone_name')->unique()->count() }}</div>
            <div class="text-xs text-gray-500 mt-1">منطقه ارسال</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 text-center">
            <div class="text-2xl font-bold {{ $wcMethods->where('mapped_shipping_type', '!=', null)->count() === $wcMethods->count() && $wcMethods->count() > 0 ? 'text-green-700' : 'text-yellow-700' }}">
                {{ $wcMethods->where('mapped_shipping_type', '!=', null)->count() }} / {{ $wcMethods->count() }}
            </div>
            <div class="text-xs text-gray-500 mt-1">مپ شده</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 text-center">
            <div class="text-sm font-medium text-gray-900">
                @if($lastSync)
                    {{ \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($lastSync))->format('Y/m/d H:i') }}
                @else
                    <span class="text-yellow-600">هنوز سینک نشده</span>
                @endif
            </div>
            <div class="text-xs text-gray-500 mt-1">آخرین سینک</div>
        </div>
    </div>

    <!-- Sync Result -->
    <div id="sync-result" class="hidden p-4 rounded-lg text-sm"></div>

    @if($wcMethods->isEmpty())
    <!-- Empty State -->
    <div class="bg-white rounded-xl shadow-sm p-12 text-center">
        <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
        </div>
        <h3 class="text-lg font-bold text-gray-900 mb-2">هنوز روش ارسالی دریافت نشده</h3>
        <p class="text-gray-500 mb-6">ابتدا اتصال ووکامرس را برقرار کنید، سپس روش‌های ارسال را از فروشگاه دریافت کنید.</p>
        <div class="flex justify-center gap-3">
            <a href="{{ route('warehouse.woocommerce.index') }}" class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium text-sm">تنظیمات ووکامرس</a>
            <button onclick="syncShippingMethods()" class="px-5 py-2.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-medium text-sm">دریافت از ووکامرس</button>
        </div>
    </div>
    @else
    <!-- Shipping Methods by Zone -->
    <div x-data="shippingManager()" class="space-y-6">
        @php
            $groupedMethods = $wcMethods->groupBy('zone_name');
        @endphp

        @foreach($groupedMethods as $zoneName => $methods)
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="bg-gray-50 px-6 py-3 border-b flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <h3 class="font-bold text-gray-900">{{ $zoneName }}</h3>
                    <span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full">{{ $methods->count() }} روش</span>
                </div>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50/50">
                    <tr>
                        <th class="px-6 py-2.5 text-right font-medium text-gray-600">روش ارسال</th>
                        <th class="px-6 py-2.5 text-right font-medium text-gray-600">شناسه</th>
                        <th class="px-6 py-2.5 text-right font-medium text-gray-600">وضعیت</th>
                        <th class="px-6 py-2.5 text-right font-medium text-gray-600">تعداد سفارش</th>
                        <th class="px-6 py-2.5 text-right font-medium text-gray-600">تشخیص خودکار</th>
                        <th class="px-6 py-2.5 text-right font-medium text-gray-600 min-w-[200px]">نوع ارسال داخلی</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($methods as $method)
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-6 py-3">
                            <span class="font-medium text-gray-900">{{ $method->method_title }}</span>
                        </td>
                        <td class="px-6 py-3">
                            <code class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded" dir="ltr">{{ $method->method_id }}</code>
                        </td>
                        <td class="px-6 py-3">
                            @if($method->enabled)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700">
                                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
                                    فعال
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-700">
                                    <span class="w-1.5 h-1.5 bg-red-500 rounded-full"></span>
                                    غیرفعال
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-gray-500">
                            {{ $orderCounts[$method->method_id] ?? 0 }}
                        </td>
                        <td class="px-6 py-3">
                            @if($method->auto_detected_type)
                                <span class="text-xs px-2 py-0.5 rounded bg-blue-50 text-blue-600">{{ $method->auto_detected_type }}</span>
                            @else
                                <span class="text-xs text-gray-400">--</span>
                            @endif
                        </td>
                        <td class="px-6 py-3">
                            <select
                                onchange="updateMapping({{ $method->id }}, this.value)"
                                class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                <option value="">-- انتخاب نوع ارسال --</option>
                                @foreach($shippingTypes as $type)
                                <option value="{{ $type->slug }}" {{ $method->mapped_shipping_type === $type->slug ? 'selected' : '' }}>
                                    {{ $type->name }} ({{ $type->slug }})
                                </option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endforeach

        <!-- Actions -->
        <div class="flex items-center justify-between bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-3">
                <button onclick="redetectShipping()" id="redetect-btn" class="px-5 py-2.5 bg-orange-600 text-white rounded-lg hover:bg-orange-700 font-medium text-sm flex items-center gap-2">
                    <svg class="w-5 h-5" id="redetect-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span id="redetect-text">بازتشخیص سفارشات موجود</span>
                </button>
                <p class="text-xs text-gray-500">نوع ارسال سفارشات فعلی را بر اساس نقشه‌برداری جدید دوباره تشخیص می‌دهد.</p>
            </div>
            <a href="{{ route('warehouse.settings.index') }}" class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium text-sm">
                مدیریت انواع ارسال داخلی
            </a>
        </div>

        <!-- Redetect Result -->
        <div id="redetect-result" class="hidden p-4 rounded-lg text-sm"></div>
    </div>

    <!-- Internal Shipping Types Reference -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="font-bold text-gray-900 mb-4">انواع ارسال داخلی</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($shippingTypes as $type)
            <div class="flex items-center justify-between p-3 border rounded-lg {{ $type->is_active ? 'border-gray-200' : 'border-red-200 bg-red-50/30' }}">
                <div class="flex items-center gap-3">
                    <span class="font-medium text-gray-900">{{ $type->name }}</span>
                    <code class="text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded" dir="ltr">{{ $type->slug }}</code>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-blue-600">{{ $type->timer_label }}</span>
                    <span class="px-2 py-0.5 rounded text-xs {{ $type->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                        {{ $type->is_active ? 'فعال' : 'غیرفعال' }}
                    </span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
function showResult(divId, success, message) {
    const div = document.getElementById(divId);
    div.classList.remove('hidden', 'bg-green-50', 'text-green-800', 'bg-red-50', 'text-red-800');
    div.classList.add(success ? 'bg-green-50' : 'bg-red-50', success ? 'text-green-800' : 'text-red-800');
    div.innerHTML = message.replace(/\n/g, '<br>');
}

function syncShippingMethods() {
    const btn = document.getElementById('sync-btn');
    const icon = document.getElementById('sync-icon');
    const text = document.getElementById('sync-text');

    btn.disabled = true;
    icon.classList.add('animate-spin');
    text.textContent = 'در حال دریافت...';

    fetch('{{ route("warehouse.shipping.sync") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
    .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    })
    .then(data => {
        btn.disabled = false;
        icon.classList.remove('animate-spin');
        text.textContent = 'دریافت از ووکامرس';
        showResult('sync-result', data.success, data.message);
        if (data.success) {
            setTimeout(() => location.reload(), 1500);
        }
    })
    .catch(err => {
        btn.disabled = false;
        icon.classList.remove('animate-spin');
        text.textContent = 'دریافت از ووکامرس';
        showResult('sync-result', false, 'خطا: ' + err.message);
    });
}

function updateMapping(methodId, shippingType) {
    fetch('/warehouse/shipping/method/' + methodId, {
        method: 'PUT',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ mapped_shipping_type: shippingType }),
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) alert(data.message);
    })
    .catch(err => alert('خطا: ' + err.message));
}

function redetectShipping() {
    const btn = document.getElementById('redetect-btn');
    const icon = document.getElementById('redetect-icon');
    const text = document.getElementById('redetect-text');

    btn.disabled = true;
    icon.classList.add('animate-spin');
    text.textContent = 'در حال بازتشخیص...';

    fetch('{{ route("warehouse.shipping.redetect") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
    .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    })
    .then(data => {
        btn.disabled = false;
        icon.classList.remove('animate-spin');
        text.textContent = 'بازتشخیص سفارشات موجود';

        let msg = `از ${data.total} سفارش: ${data.updated} تغییر کرد، ${data.skipped} بدون تغییر`;
        if (data.details && data.details.length > 0) {
            msg += '\n' + data.details.join('\n');
        }
        showResult('redetect-result', data.success, msg);
    })
    .catch(err => {
        btn.disabled = false;
        icon.classList.remove('animate-spin');
        text.textContent = 'بازتشخیص سفارشات موجود';
        showResult('redetect-result', false, 'خطا: ' + err.message);
    });
}

function shippingManager() {
    return {};
}
</script>
@endpush
@endsection
