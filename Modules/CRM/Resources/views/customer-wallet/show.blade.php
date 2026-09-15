@extends('layouts.admin')

@section('page-title', 'کیف‌پول مشتری')

@section('main')
<div class="space-y-4 max-w-4xl" dir="rtl">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">کیف‌پول {{ $customer->full_name ?: 'مشتری' }}</h1>
            <p class="text-sm text-gray-500 mt-1" dir="ltr">{{ $customer->mobile }}</p>
        </div>
        <a href="{{ route('crm.customer-wallet.withdrawals') }}" class="px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg text-sm">درخواست‌های برداشت</a>
    </div>

    @if(session('success'))<div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm">{{ $errors->first() }}</div>@endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="text-xs text-gray-400">موجودی فعلی</div>
            <div class="text-3xl font-bold text-teal-600 mt-1" dir="ltr">{{ number_format((int) $customer->wallet_balance) }} <span class="text-sm font-normal text-gray-400">تومان</span></div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <h2 class="text-sm font-bold text-gray-800 dark:text-gray-100 mb-2">تعدیلِ دستی</h2>
            <form method="POST" action="{{ route('crm.customer-wallet.adjust', $customer) }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <select name="direction" class="px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                    <option value="credit">افزایش (+)</option>
                    <option value="debit">کاهش (−)</option>
                </select>
                <input type="number" name="amount" min="1" required placeholder="مبلغ (تومان)" dir="ltr"
                       class="flex-1 px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                <input type="text" name="note" required placeholder="علت"
                       class="flex-1 px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                <button class="px-4 py-1.5 bg-brand-600 hover:bg-brand-700 text-white rounded text-sm font-bold">ثبت</button>
            </form>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/40 text-xs text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="p-3 text-start">تاریخ</th>
                    <th class="p-3 text-start">نوع</th>
                    <th class="p-3 text-start">مبلغ</th>
                    <th class="p-3 text-start">موجودی بعد</th>
                    <th class="p-3 text-start">توضیح</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($transactions as $t)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                        <td class="p-3 text-xs text-gray-500 whitespace-nowrap" dir="ltr">@jdatetime($t->created_at)</td>
                        <td class="p-3"><span class="px-2 py-0.5 rounded-full text-[11px] {{ $t->typeEnum()?->badgeClass() ?? 'bg-gray-100 text-gray-700' }}">{{ $t->typeLabel() }}</span></td>
                        <td class="p-3 font-bold whitespace-nowrap {{ $t->amount >= 0 ? 'text-emerald-600' : 'text-rose-600' }}" dir="ltr">{{ number_format($t->amount) }}</td>
                        <td class="p-3 text-xs text-gray-500" dir="ltr">{{ number_format($t->balance_after) }}</td>
                        <td class="p-3 text-xs text-gray-600 dark:text-gray-300">{{ $t->note }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-8 text-center text-gray-400 text-sm">تراکنشی ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $transactions->links() }}
</div>
@endsection
