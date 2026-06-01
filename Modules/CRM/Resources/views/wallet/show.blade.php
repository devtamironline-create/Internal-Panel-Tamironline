@extends('layouts.admin')

@section('page-title', 'کیف‌پول تکنسین')

@section('main')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">کیف‌پول {{ trim($technician->first_name . ' ' . ($technician->last_name ?? '')) }}</h1>
            @php $true = $technician->true_balance; @endphp
            <div class="mt-2">
                <span class="text-3xl font-bold {{ $true >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ number_format(abs($true)) }} <span class="text-base font-normal">تومان</span>
                </span>
                <span class="ms-2 text-sm text-gray-500">{{ $true >= 0 ? 'بستانکار (شرکت بدهکار است)' : 'بدهکار (تکنسین بدهکار است)' }}</span>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-400">
                <span>تراکنش‌های کیف‌پول:
                    <b class="{{ $technician->wallet_balance >= 0 ? 'text-emerald-700' : 'text-red-700' }}" dir="ltr">{{ number_format($technician->wallet_balance) }}</b>
                </span>
                <span class="text-gray-400">−</span>
                <span>سهم شرکت از فاکتورها:
                    <b class="text-amber-700" dir="ltr">{{ number_format($technician->invoice_debt) }}</b>
                </span>
                <span class="text-gray-400">=</span>
                <span>مانده نهایی:
                    <b class="{{ $true >= 0 ? 'text-emerald-700' : 'text-red-700' }}" dir="ltr">{{ number_format($true) }}</b>
                </span>
            </div>
        </div>
        <a href="{{ route('crm.wallet.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm">بازگشت</a>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- تاریخچه با تب‌ها: فعلی + قدیمی (آرشیو) --}}
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden" x-data="{ tab: 'current' }">
            <div class="border-b border-gray-200 dark:border-gray-700 flex items-center gap-1 px-2">
                <button type="button" @click="tab = 'current'"
                        :class="tab === 'current' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-800'"
                        class="px-4 py-3 text-sm font-bold border-b-2 transition-colors">
                    تراکنش‌های جاری
                    <span class="ms-1 px-1.5 py-0.5 rounded text-[10px] bg-gray-100 text-gray-700">{{ number_format($transactions->total()) }}</span>
                </button>
                @if(! empty($archivedTxs))
                <button type="button" @click="tab = 'archive'"
                        :class="tab === 'archive' ? 'border-rose-600 text-rose-600' : 'border-transparent text-gray-500 hover:text-gray-800'"
                        class="px-4 py-3 text-sm font-bold border-b-2 transition-colors">
                    تاریخچهٔ قدیمی
                    <span class="ms-1 px-1.5 py-0.5 rounded text-[10px] bg-rose-100 text-rose-700">{{ number_format(count($archivedTxs)) }}</span>
                </button>
                @endif
            </div>

            {{-- تب فعلی --}}
            <div x-show="tab === 'current'">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-xs">
                        <tr>
                            <th class="px-4 py-2 text-right text-gray-500 uppercase">زمان</th>
                            <th class="px-4 py-2 text-right text-gray-500 uppercase">نوع</th>
                            <th class="px-4 py-2 text-right text-gray-500 uppercase">مبلغ</th>
                            <th class="px-4 py-2 text-right text-gray-500 uppercase">موجودی</th>
                            <th class="px-4 py-2 text-right text-gray-500 uppercase">مرجع / توضیح</th>
                            @can('delete-wallet-transaction')
                                <th class="px-4 py-2 text-right text-gray-500 uppercase w-16"></th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                        @forelse($transactions as $tx)
                        <tr>
                            <td class="px-4 py-2 text-xs text-gray-500" dir="ltr">@jdatetime($tx->created_at)</td>
                            <td class="px-4 py-2"><span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $tx->type->badgeClass() }}">{{ $tx->type->label() }}</span></td>
                            <td class="px-4 py-2 font-bold {{ $tx->amount >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ ($tx->amount >= 0 ? '+' : '') . number_format($tx->amount) }}</td>
                            <td class="px-4 py-2">{{ number_format($tx->balance_after) }}</td>
                            <td class="px-4 py-2 text-xs text-gray-600">
                                @if($tx->invoice)<a href="{{ route('crm.invoices.show', $tx->invoice) }}" class="text-brand-600" dir="ltr">{{ $tx->invoice->invoice_code }}</a>@endif
                                @if($tx->order && !$tx->invoice)<a href="{{ route('crm.orders.show', $tx->order) }}" class="text-brand-600" dir="ltr">{{ $tx->order->order_code }}</a>@endif
                                {{ $tx->note ? '— ' . $tx->note : '' }}
                            </td>
                            @can('delete-wallet-transaction')
                                <td class="px-2 py-2">
                                    <form method="POST" action="{{ route('crm.wallet.transaction.destroy', [$technician, $tx]) }}" class="inline"
                                          onsubmit="return confirm('این تراکنش {{ $tx->amount >= 0 ? '+' : '' }}{{ number_format($tx->amount) }} تومان حذف شود؟\n\nمانده تکنسین به اندازهٔ همین مقدار معکوس می‌شود (یعنی مانده {{ $tx->amount >= 0 ? 'کم' : 'اضافه' }} می‌شود).');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-rose-600 hover:text-rose-700 text-xs" title="حذف این تراکنش">
                                            🗑
                                        </button>
                                    </form>
                                </td>
                            @endcan
                        </tr>
                        @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">تراکنشی ثبت نشده.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-4 py-3">{{ $transactions->links() }}</div>
            </div>

            {{-- تب آرشیو — فقط نمایش، خارج از همه‌ی محاسبات --}}
            @if(! empty($archivedTxs))
            <div x-show="tab === 'archive'" x-cloak>
                <div class="px-4 py-3 bg-rose-50 dark:bg-rose-900/20 border-b border-rose-200 dark:border-rose-800">
                    <div class="flex items-start gap-2 text-xs">
                        <span class="text-rose-700 dark:text-rose-300 font-bold">⚠ فقط نمایش</span>
                        <p class="text-rose-700 dark:text-rose-300 leading-6">
                            این تراکنش‌ها قبل از reset مالی ثبت بودند و الان فقط برای مرور تاریخی نگه‌داری شده‌اند.
                            <b>هیچ‌کدام در محاسبهٔ موجودی فعلی، true_balance، invoice_debt یا گزارش مالی وارد نمی‌شوند.</b>
                            منابع: فایل‌های JSONL (storage/app/crm/wallet-reset-*.jsonl) + جدول crm_wallet_archive_txs (ایمپورت از WP).
                        </p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-xs">
                        <tr>
                            <th class="px-4 py-2 text-right text-gray-500 uppercase">زمان (آرشیو)</th>
                            <th class="px-4 py-2 text-right text-gray-500 uppercase">نوع</th>
                            <th class="px-4 py-2 text-right text-gray-500 uppercase">مبلغ</th>
                            <th class="px-4 py-2 text-right text-gray-500 uppercase">سفارش</th>
                            <th class="px-4 py-2 text-right text-gray-500 uppercase">یادداشت</th>
                            <th class="px-4 py-2 text-right text-gray-500 uppercase">منبع</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                        @foreach($archivedTxs as $a)
                        @php
                            $amount = (int) ($a['amount'] ?? 0);
                            $typeVal = (string) ($a['type'] ?? '—');
                            $typeEnum = \Modules\CRM\Enums\WalletTxType::tryFrom($typeVal);
                            $typeLabel = $typeEnum?->label() ?? $typeVal;
                            $typeBadge = $typeEnum?->badgeClass() ?? 'bg-gray-100 text-gray-700';
                        @endphp
                        <tr class="opacity-75">
                            <td class="px-4 py-2 text-xs text-gray-500" dir="ltr">@jdatetime($a['created_at'])</td>
                            <td class="px-4 py-2"><span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $typeBadge }}">{{ $typeLabel }}</span></td>
                            <td class="px-4 py-2 font-bold {{ $amount >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ ($amount >= 0 ? '+' : '') . number_format($amount) }}</td>
                            <td class="px-4 py-2 text-xs">
                                @php $ord = $a['_order'] ?? null; @endphp
                                @if($ord && $ord['id'])
                                    <a href="{{ route('crm.orders.show', $ord['id']) }}"
                                       class="text-brand-600 hover:underline" dir="ltr"
                                       target="_blank">{{ $ord['code'] }}</a>
                                @elseif($ord && $ord['wp_id'])
                                    <span class="text-gray-400" dir="ltr" title="در پنل نیست">wp:{{ $ord['wp_id'] }}</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-xs text-gray-600">{{ $a['note'] ?? '—' }}</td>
                            <td class="px-4 py-2 text-[10px] text-gray-400" dir="ltr">
                                {{ $a['_source'] ?? $a['_source_file'] ?? '' }}
                                @if(! empty($a['_reset_at']))
                                    <br>@jdatetime($a['_reset_at'])
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>
            @endif
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
