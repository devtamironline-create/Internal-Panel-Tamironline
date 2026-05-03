@extends('layouts.admin')

@section('page-title', 'سفارش‌های من')

@section('main')
@php use Modules\CRM\Enums\OrderStatus; @endphp
<div class="p-6 space-y-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">سفارش‌های من</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">سفارش‌های تخصیص‌یافته به شما ({{ trim($technician->first_name . ' ' . $technician->last_name) }})</p>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    {{-- نوار فیلتر آمادهٔ وضعیت --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm px-2 overflow-x-auto">
        <div class="flex items-center gap-1 min-w-max">
            @php $isAll = $status === ''; @endphp
            <a href="{{ route('crm.orders.my') }}"
               class="relative px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors
                      {{ $isAll ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-200' }}">
                همه فعال‌ها
                <span class="ms-1 inline-flex items-center justify-center min-w-[1.5rem] h-5 px-1.5 text-xs font-bold rounded-full
                            {{ $isAll ? 'bg-brand-100 text-brand-700' : ($activeCount > 0 ? 'bg-gray-100 text-gray-700' : 'bg-gray-50 text-gray-400') }}">
                    {{ number_format($activeCount) }}
                </span>
            </a>
            @foreach($tabs as $key => $tab)
            @php $isActive = $status === $key; @endphp
            <a href="{{ route('crm.orders.my', ['status' => $key]) }}"
               class="relative px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors
                      {{ $isActive ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-200' }}">
                {{ $tab['label'] }}
                <span class="ms-1 inline-flex items-center justify-center min-w-[1.5rem] h-5 px-1.5 text-xs font-bold rounded-full
                            {{ $isActive ? 'bg-brand-100 text-brand-700' : ($tab['count'] > 0 ? 'bg-gray-100 text-gray-700' : 'bg-gray-50 text-gray-400') }}">
                    {{ number_format($tab['count']) }}
                </span>
            </a>
            @endforeach

            {{-- آرشیو: شامل کنسل/تکمیل/ایاب و ذهاب/رد --}}
            @php $isArchive = $status === 'archive'; @endphp
            <a href="{{ route('crm.orders.my', ['status' => 'archive']) }}"
               class="relative px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors
                      {{ $isArchive ? 'border-amber-600 text-amber-600' : 'border-transparent text-gray-400 hover:text-gray-700 hover:border-gray-200' }}">
                آرشیو
                <span class="ms-1 inline-flex items-center justify-center min-w-[1.5rem] h-5 px-1.5 text-xs font-bold rounded-full
                            {{ $isArchive ? 'bg-amber-100 text-amber-700' : ($archiveCount > 0 ? 'bg-gray-100 text-gray-700' : 'bg-gray-50 text-gray-400') }}">
                    {{ number_format($archiveCount) }}
                </span>
            </a>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">کد</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">مشتری</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">دستگاه</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">محل</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">زمان مراجعه</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($orders as $order)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-4 text-sm font-medium" dir="ltr">{{ $order->order_code }}</td>
                    <td class="px-6 py-4 text-sm">
                        <div class="text-gray-900 dark:text-gray-100">{{ $order->customer_name }}</div>
                        <div class="text-xs">@tel($order->customer_mobile)</div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $order->brand?->name }}{{ $order->device ? ' / ' . $order->device->name : '' }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $order->province?->name }}{{ $order->city ? ' / ' . $order->city->name : '' }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2.5 py-1 text-xs font-medium rounded-full {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400" dir="ltr">@jdatetime($order->visit_scheduled_at)</td>
                    <td class="px-6 py-4">
                        <a href="{{ route('crm.tech.orders.show', $order) }}" class="text-brand-600 hover:underline text-sm">مشاهده</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-6 py-12 text-center text-gray-500">سفارشی به شما تخصیص داده نشده.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $orders->links() }}</div>
</div>
@endsection
