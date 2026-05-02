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

    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 flex items-end gap-3">
        <div class="flex-1 max-w-xs">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">وضعیت</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— همه —</option>
                @foreach($statuses as $key => $label)
                <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800">اعمال</button>
    </form>

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
                        <div class="text-xs text-gray-500" dir="ltr">{{ $order->customer_mobile }}</div>
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
