@extends('layouts.admin')
@section('page-title', 'مدیریت ارسال')
@section('main')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('warehouse.index') }}" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900">مدیریت ارسال</h1>
                <p class="text-gray-600 mt-1">ارسال پستی و پیکی سفارشات</p>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="border-b flex gap-0">
            <a href="{{ route('warehouse.dispatch.index', ['tab' => 'ready']) }}"
               class="px-6 py-3 text-sm font-medium border-b-2 {{ $tab === 'ready' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                آماده ارسال <span class="bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded-full mr-1">{{ $readyCount }}</span>
            </a>
            <a href="{{ route('warehouse.dispatch.index', ['tab' => 'shipped']) }}"
               class="px-6 py-3 text-sm font-medium border-b-2 {{ $tab === 'shipped' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                ارسال شده <span class="bg-indigo-100 text-indigo-700 text-xs px-2 py-0.5 rounded-full mr-1">{{ $shippedCount }}</span>
            </a>
            <a href="{{ route('warehouse.dispatch.index', ['tab' => 'delivered']) }}"
               class="px-6 py-3 text-sm font-medium border-b-2 {{ $tab === 'delivered' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                تحویل شده
            </a>
        </div>

        <div class="p-6">
            @if($orders->isEmpty())
                <div class="text-center py-12 text-gray-500">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    <p>سفارشی در این وضعیت وجود ندارد</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($orders as $order)
                    <div class="border rounded-lg p-4 hover:bg-gray-50" x-data="{ showCourier: false }">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div>
                                    <div class="font-bold text-gray-900">{{ $order->order_number }}</div>
                                    <div class="text-sm text-gray-500">{{ $order->customer_name }} - {{ $order->customer_mobile }}</div>
                                </div>
                                <span class="px-2 py-1 rounded-full text-xs font-medium {{ $order->shipping_type === 'courier' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                                    {{ $order->shipping_type === 'courier' ? 'پیک' : 'پست' }}
                                </span>
                                <span class="text-sm text-gray-500">{{ $order->actual_weight ?? $order->total_weight }} kg</span>
                            </div>

                            <div class="flex items-center gap-2">
                                @if($tab === 'ready')
                                    @if($order->shipping_type === 'post')
                                        <button onclick="shipPost({{ $order->id }})" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">ارسال پستی (آمادست)</button>
                                    @else
                                        <button @click="showCourier = !showCourier" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm hover:bg-purple-700">تخصیص پیک</button>
                                    @endif
                                    <a href="{{ route('warehouse.print.label', $order) }}" target="_blank" class="px-3 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200">چاپ برچسب</a>
                                @elseif($tab === 'shipped')
                                    <button onclick="markDelivered({{ $order->id }})" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700">تحویل شد</button>
                                    <button onclick="markReturned({{ $order->id }})" class="px-3 py-2 bg-red-100 text-red-700 rounded-lg text-sm hover:bg-red-200">مرجوعی</button>
                                    @if($order->tracking_code)
                                        <span class="text-sm text-gray-500" dir="ltr">{{ $order->tracking_code }}</span>
                                    @endif
                                    @if($order->driver_name)
                                        <span class="text-sm text-gray-500">پیک: {{ $order->driver_name }}</span>
                                    @endif
                                @elseif($tab === 'delivered')
                                    <span class="text-sm text-green-600">{{ $order->delivered_at ? \Morilog\Jalali\Jalalian::fromCarbon($order->delivered_at)->format('Y/m/d H:i') : '-' }}</span>
                                @endif
                            </div>
                        </div>

                        <!-- Courier Form -->
                        <div x-show="showCourier" x-collapse class="mt-4 pt-4 border-t">
                            <div class="flex items-end gap-3">
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">نام راننده</label>
                                    <input type="text" id="driver-name-{{ $order->id }}" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="نام راننده">
                                </div>
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">تلفن راننده</label>
                                    <input type="text" id="driver-phone-{{ $order->id }}" class="w-full px-3 py-2 border rounded-lg text-sm" dir="ltr" placeholder="09...">
                                </div>
                                <button onclick="shipCourier({{ $order->id }})" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm hover:bg-purple-700">ثبت و ارسال</button>
                            </div>
                        </div>
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

function shipPost(orderId) {
    if (!confirm('ارسال از طریق پست (آمادست)؟')) return;
    fetch('/warehouse/' + orderId + '/ship-post', { method: 'POST', headers })
        .then(r => r.json()).then(d => { alert(d.message); if (d.success) location.reload(); });
}

function shipCourier(orderId) {
    const name = document.getElementById('driver-name-' + orderId).value;
    const phone = document.getElementById('driver-phone-' + orderId).value;
    if (!name) { alert('نام راننده الزامی است.'); return; }
    fetch('/warehouse/' + orderId + '/ship-courier', { method: 'POST', headers, body: JSON.stringify({ driver_name: name, driver_phone: phone }) })
        .then(r => r.json()).then(d => { alert(d.message); if (d.success) location.reload(); });
}

function markDelivered(orderId) {
    if (!confirm('تایید تحویل؟')) return;
    fetch('/warehouse/' + orderId + '/delivered', { method: 'POST', headers })
        .then(r => r.json()).then(d => { alert(d.message); if (d.success) location.reload(); });
}

function markReturned(orderId) {
    const notes = prompt('دلیل مرجوعی:');
    fetch('/warehouse/' + orderId + '/returned', { method: 'POST', headers, body: JSON.stringify({ notes: notes }) })
        .then(r => r.json()).then(d => { alert(d.message); if (d.success) location.reload(); });
}
</script>
@endpush
@endsection
