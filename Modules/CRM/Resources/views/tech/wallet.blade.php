@extends('layouts.admin')

@section('page-title', 'کیف‌پول من')

@section('main')
<div class="p-6 space-y-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">کیف‌پول من</h1>
    </div>

    <div class="bg-gradient-to-l from-brand-500 to-brand-700 rounded-xl shadow-sm p-6 text-white">
        <div class="text-sm opacity-90">موجودی فعلی</div>
        <div class="text-3xl font-bold mt-1">
            {{ number_format(abs($technician->wallet_balance)) }}
            <span class="text-base font-normal">تومان</span>
        </div>
        <div class="text-xs opacity-90 mt-1">
            {{ $technician->wallet_balance >= 0 ? 'شرکت به شما بدهکار است' : 'شما به شرکت بدهکار هستید' }}
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">تاریخچه تراکنش‌ها</h2>
        </div>
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700 text-xs">
                <tr>
                    <th class="px-4 py-2 text-right text-gray-500">زمان</th>
                    <th class="px-4 py-2 text-right text-gray-500">نوع</th>
                    <th class="px-4 py-2 text-right text-gray-500">مبلغ</th>
                    <th class="px-4 py-2 text-right text-gray-500">موجودی پس از</th>
                    <th class="px-4 py-2 text-right text-gray-500">مرجع / توضیح</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                @forelse($transactions as $tx)
                <tr>
                    <td class="px-4 py-2 text-xs text-gray-500" dir="ltr">@jdatetime($tx->created_at)</td>
                    <td class="px-4 py-2">
                        <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $tx->type->badgeClass() }}">{{ $tx->type->label() }}</span>
                    </td>
                    <td class="px-4 py-2 font-bold {{ $tx->amount >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ ($tx->amount >= 0 ? '+' : '') . number_format($tx->amount) }}
                    </td>
                    <td class="px-4 py-2">{{ number_format($tx->balance_after) }}</td>
                    <td class="px-4 py-2 text-xs text-gray-600">
                        @if($tx->order) <a href="{{ route('crm.tech.orders.show', $tx->order) }}" class="text-brand-600" dir="ltr">{{ $tx->order->order_code }}</a>@endif
                        {{ $tx->note ? '— ' . $tx->note : '' }}
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">تراکنشی ثبت نشده.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3">{{ $transactions->links() }}</div>
    </div>
</div>
@endsection
