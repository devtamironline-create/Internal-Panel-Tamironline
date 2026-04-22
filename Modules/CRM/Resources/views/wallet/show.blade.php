@extends('layouts.admin')

@section('page-title', 'کیف‌پول تکنسین')

@section('main')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">کیف‌پول {{ trim($technician->first_name . ' ' . ($technician->last_name ?? '')) }}</h1>
            <div class="mt-2">
                <span class="text-3xl font-bold {{ $technician->wallet_balance >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ number_format(abs($technician->wallet_balance)) }} <span class="text-base font-normal">تومان</span>
                </span>
                <span class="ms-2 text-sm text-gray-500">{{ $technician->wallet_balance >= 0 ? 'بستانکار (شرکت بدهکار است)' : 'بدهکار (تکنسین بدهکار است)' }}</span>
            </div>
        </div>
        <a href="{{ route('crm.wallet.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm">بازگشت</a>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- تاریخچه --}}
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">تاریخچه تراکنش‌ها</h2>
            </div>
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700 text-xs">
                    <tr>
                        <th class="px-4 py-2 text-right text-gray-500 uppercase">زمان</th>
                        <th class="px-4 py-2 text-right text-gray-500 uppercase">نوع</th>
                        <th class="px-4 py-2 text-right text-gray-500 uppercase">مبلغ</th>
                        <th class="px-4 py-2 text-right text-gray-500 uppercase">موجودی</th>
                        <th class="px-4 py-2 text-right text-gray-500 uppercase">مرجع / توضیح</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                    @forelse($transactions as $tx)
                    <tr>
                        <td class="px-4 py-2 text-xs text-gray-500" dir="ltr">{{ $tx->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-2"><span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $tx->type->badgeClass() }}">{{ $tx->type->label() }}</span></td>
                        <td class="px-4 py-2 font-bold {{ $tx->amount >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ ($tx->amount >= 0 ? '+' : '') . number_format($tx->amount) }}</td>
                        <td class="px-4 py-2">{{ number_format($tx->balance_after) }}</td>
                        <td class="px-4 py-2 text-xs text-gray-600">
                            @if($tx->invoice)<a href="{{ route('crm.invoices.show', $tx->invoice) }}" class="text-brand-600" dir="ltr">{{ $tx->invoice->invoice_code }}</a>@endif
                            @if($tx->order && !$tx->invoice)<a href="{{ route('crm.orders.show', $tx->order) }}" class="text-brand-600" dir="ltr">{{ $tx->order->order_code }}</a>@endif
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

        {{-- فرم ثبت تراکنش دستی --}}
        @can('manage-crm-wallet')
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">ثبت تراکنش دستی</h2>
            <form action="{{ route('crm.wallet.transaction.store', $technician) }}" method="POST" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نوع</label>
                    <select name="type" id="wallet-tx-type" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                        <option value="reward">جایزه (+)</option>
                        <option value="penalty">جریمه (-)</option>
                        <option value="payout">پرداخت به تکنسین (-)</option>
                        <option value="credit">واریز تکنسین به شرکت (-)</option>
                        <option value="adjustment">تعدیل دستی</option>
                    </select>
                </div>

                <div id="direction-wrapper" style="display:none;">
                    <label class="block text-sm font-medium text-gray-700 mb-1">جهت</label>
                    <select name="direction" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                        <option value="credit">بستانکار (+)</option>
                        <option value="debit">بدهکار (-)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">مبلغ (تومان)</label>
                    <input type="number" name="amount" min="1" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">توضیح</label>
                    <textarea name="note" rows="2" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm"></textarea>
                </div>

                <button class="w-full px-3 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm">ثبت تراکنش</button>
            </form>
        </div>
        @endcan
    </div>
</div>

<script>
(function () {
    const typeEl = document.getElementById('wallet-tx-type');
    const dirWrap = document.getElementById('direction-wrapper');
    if (!typeEl || !dirWrap) return;
    const toggle = () => { dirWrap.style.display = typeEl.value === 'adjustment' ? '' : 'none'; };
    typeEl.addEventListener('change', toggle);
    toggle();
})();
</script>
@endsection
