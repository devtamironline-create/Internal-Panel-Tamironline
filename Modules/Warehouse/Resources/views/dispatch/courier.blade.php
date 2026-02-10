@extends('layouts.admin')
@section('page-title', 'ایستگاه ارسال پیک')
@section('main')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('warehouse.index') }}" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900">ایستگاه ارسال پیک</h1>
                <p class="text-gray-600 mt-1">مدیریت سفارشات پیکی و ثبت اطلاعات پیک</p>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="border-b flex gap-0">
            <a href="{{ route('warehouse.dispatch.courier', ['tab' => 'ready']) }}"
               class="px-6 py-3 text-sm font-medium border-b-2 {{ $tab === 'ready' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                در انتظار پیک <span class="bg-purple-100 text-purple-700 text-xs px-2 py-0.5 rounded-full mr-1">{{ $readyCount }}</span>
            </a>
            <a href="{{ route('warehouse.dispatch.courier', ['tab' => 'dispatched']) }}"
               class="px-6 py-3 text-sm font-medium border-b-2 {{ $tab === 'dispatched' ? 'border-amber-500 text-amber-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                تحویل پیک <span class="bg-amber-100 text-amber-700 text-xs px-2 py-0.5 rounded-full mr-1">{{ $dispatchedCount }}</span>
            </a>
            <a href="{{ route('warehouse.dispatch.courier', ['tab' => 'shipped']) }}"
               class="px-6 py-3 text-sm font-medium border-b-2 {{ $tab === 'shipped' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                ارسال شده <span class="bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full mr-1">{{ $shippedCount }}</span>
            </a>
        </div>

        <div class="p-6">
            @if($orders->isEmpty())
                <div class="text-center py-12 text-gray-500">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                    <p>سفارش پیکی در این وضعیت وجود ندارد</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($orders as $order)
                    <div class="border rounded-xl p-4 hover:bg-gray-50 transition-colors" x-data="{ showForm: false }">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div>
                                    <a href="{{ route('warehouse.show', $order) }}" class="font-bold text-gray-900 hover:text-purple-600">{{ $order->order_number }}</a>
                                    <div class="text-sm text-gray-500">{{ $order->customer_name }}</div>
                                </div>
                                @if($order->customer_mobile)
                                <span class="text-sm text-gray-400" dir="ltr">{{ $order->customer_mobile }}</span>
                                @endif
                                <span class="text-sm text-gray-500">{{ number_format($order->actual_weight_grams ?: $order->total_weight_grams) }}g</span>
                                @php
                                    $wcData = is_array($order->wc_order_data) ? $order->wc_order_data : [];
                                    $shipping = $wcData['shipping'] ?? [];
                                    $billing = $wcData['billing'] ?? [];
                                    $city = ($shipping['city'] ?? '') ?: ($billing['city'] ?? '');
                                    $state = ($shipping['state'] ?? '') ?: ($billing['state'] ?? '');
                                    $address = ($shipping['address_1'] ?? '') ?: ($billing['address_1'] ?? '');
                                @endphp
                                @if($city || $state)
                                <span class="text-xs text-gray-400">{{ implode('، ', array_filter([$state, $city])) }}</span>
                                @endif
                            </div>

                            <div class="flex items-center gap-2">
                                @if($tab === 'ready')
                                    <button @click="showForm = !showForm"
                                            class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-lg text-sm hover:bg-purple-700 font-medium transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        ثبت پیک
                                    </button>
                                    <a href="{{ route('warehouse.print.label', $order) }}" target="_blank" class="px-3 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200">برچسب</a>
                                @elseif($tab === 'dispatched')
                                    <div class="text-left">
                                        <div class="text-sm font-medium text-gray-900">{{ $order->driver_name }}</div>
                                        <div class="text-xs text-gray-500" dir="ltr">{{ $order->driver_phone }}</div>
                                    </div>
                                    @if($order->courier_dispatched_at)
                                        @php
                                            $remaining = now()->diffInMinutes($order->courier_dispatched_at->addHours(4), false);
                                        @endphp
                                        @if($remaining > 0)
                                            <span class="px-2.5 py-1 text-xs font-medium bg-amber-100 text-amber-700 rounded-full">
                                                {{ floor($remaining / 60) }}:{{ str_pad($remaining % 60, 2, '0', STR_PAD_LEFT) }} تا ارسال خودکار
                                            </span>
                                        @else
                                            <span class="px-2.5 py-1 text-xs font-medium bg-green-100 text-green-700 rounded-full animate-pulse">
                                                در حال تغییر به ارسال شده...
                                            </span>
                                        @endif
                                    @endif
                                    <button onclick="forceShip({{ $order->id }})"
                                            class="px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700 font-medium">
                                        ارسال فوری
                                    </button>
                                @elseif($tab === 'shipped')
                                    @if($order->driver_name)
                                    <div class="text-left">
                                        <div class="text-sm font-medium text-gray-900">{{ $order->driver_name }}</div>
                                        <div class="text-xs text-gray-500" dir="ltr">{{ $order->driver_phone }}</div>
                                    </div>
                                    @endif
                                    @if($order->shipped_at)
                                    <span class="text-sm text-green-600">{{ \Morilog\Jalali\Jalalian::fromCarbon($order->shipped_at)->format('Y/m/d H:i') }}</span>
                                    @endif
                                @endif
                            </div>
                        </div>

                        <!-- Items Preview -->
                        @if($order->items->count() > 0 && $tab === 'ready')
                        <div class="mt-2 flex flex-wrap gap-1">
                            @foreach($order->items as $item)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-100 text-gray-600 rounded text-xs">
                                <span class="font-medium">{{ $item->quantity }}x</span>
                                {{ \Illuminate\Support\Str::limit($item->product_name, 30) }}
                            </span>
                            @endforeach
                        </div>
                        @endif

                        <!-- Address -->
                        @if($address && $tab !== 'shipped')
                        <div class="mt-2 text-xs text-gray-400">{{ \Illuminate\Support\Str::limit($address, 80) }}</div>
                        @endif

                        <!-- Courier Assignment Form -->
                        @if($tab === 'ready')
                        <div x-show="showForm" x-collapse x-cloak class="mt-4 pt-4 border-t border-gray-200">
                            <div class="flex items-end gap-3">
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">نام و نام خانوادگی پیک</label>
                                    <input type="text" id="courier-name-{{ $order->id }}"
                                           class="w-full px-3 py-2.5 border-2 border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                           placeholder="نام پیک">
                                </div>
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">شماره موبایل پیک</label>
                                    <input type="text" id="courier-phone-{{ $order->id }}"
                                           class="w-full px-3 py-2.5 border-2 border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                           dir="ltr" placeholder="09...">
                                </div>
                                <button onclick="assignCourier({{ $order->id }})"
                                        class="px-5 py-2.5 bg-purple-600 text-white rounded-lg text-sm hover:bg-purple-700 font-medium whitespace-nowrap transition-colors">
                                    <svg class="w-4 h-4 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    ثبت و تحویل پیک
                                </button>
                            </div>
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>

                <div class="mt-6">{{ $orders->appends(request()->query())->links() }}</div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
const headers = { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

function assignCourier(orderId) {
    const name = document.getElementById('courier-name-' + orderId).value.trim();
    const phone = document.getElementById('courier-phone-' + orderId).value.trim();

    if (!name) { alert('نام پیک الزامی است.'); return; }
    if (!phone) { alert('شماره موبایل پیک الزامی است.'); return; }

    fetch('/warehouse/' + orderId + '/assign-courier', {
        method: 'POST',
        headers,
        body: JSON.stringify({ driver_name: name, driver_phone: phone })
    })
    .then(r => r.json())
    .then(d => {
        alert(d.message);
        if (d.success) location.reload();
    })
    .catch(() => alert('خطا در ارتباط با سرور'));
}

function forceShip(orderId) {
    if (!confirm('ارسال فوری این سفارش بدون انتظار ۴ ساعت؟')) return;
    fetch('/warehouse/' + orderId + '/status', {
        method: 'PATCH',
        headers,
        body: JSON.stringify({ status: 'shipped' })
    })
    .then(r => r.json())
    .then(d => {
        alert(d.message);
        if (d.success) location.reload();
    })
    .catch(() => alert('خطا در ارتباط با سرور'));
}
</script>
@endpush
@endsection
