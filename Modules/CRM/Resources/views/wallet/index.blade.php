@extends('layouts.admin')

@section('page-title', 'کیف‌پول تکنسین‌ها')

@section('main')
@php use Modules\CRM\Enums\WalletTxType; @endphp
<div class="p-6 space-y-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">کیف‌پول تکنسین‌ها</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">موجودی و تراکنش‌های مالی تکنسین‌ها</p>
    </div>

    {{-- ─── خلاصه موجودی هر تکنسین، تفکیک‌شده به بدهکار/بستانکار ─── --}}
    <div class="space-y-5">

        {{-- بدهکارها (حاشیه قرمز) --}}
        <div class="rounded-xl border-2 border-rose-300 dark:border-rose-700 bg-rose-50/30 dark:bg-rose-900/10 p-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-base font-bold text-rose-700 dark:text-rose-300 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                    </svg>
                    بدهکار به شرکت
                </h2>
                <span class="text-xs text-rose-700 dark:text-rose-300 font-medium">
                    {{ number_format($debtors->count()) }} تکنسین
                </span>
            </div>

            @if($debtors->isEmpty())
                <p class="text-xs text-gray-500 italic py-2">هیچ تکنسین بدهکاری وجود ندارد.</p>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($debtors as $t)
                        <a href="{{ route('crm.wallet.show', $t) }}"
                           class="bg-white dark:bg-gray-800 rounded-lg border border-rose-200 dark:border-rose-800 shadow-sm p-4 hover:shadow-md hover:border-rose-400 transition">
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $t->full_name }}</div>
                            <div class="mt-2 text-2xl font-bold text-rose-600">
                                −{{ number_format(abs((int) $t->true_balance)) }}
                                <span class="text-sm font-normal">تومان</span>
                            </div>
                            <div class="text-xs text-rose-700 mt-1">بدهکار</div>
                            <div class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-700 text-[11px] text-gray-500 grid grid-cols-2 gap-1">
                                <span>کیف‌پول:</span>
                                <span class="text-left {{ $t->wallet_balance >= 0 ? 'text-emerald-700' : 'text-red-700' }}" dir="ltr">{{ number_format($t->wallet_balance) }}</span>
                                <span>سهم شرکت:</span>
                                <span class="text-left text-amber-700" dir="ltr">−{{ number_format($t->invoice_debt) }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- بستانکارها (حاشیه سبز) --}}
        <div class="rounded-xl border-2 border-emerald-300 dark:border-emerald-700 bg-emerald-50/30 dark:bg-emerald-900/10 p-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-base font-bold text-emerald-700 dark:text-emerald-300 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                    </svg>
                    بستانکار از شرکت
                </h2>
                <span class="text-xs text-emerald-700 dark:text-emerald-300 font-medium">
                    {{ number_format($creditors->count()) }} تکنسین
                </span>
            </div>

            @if($creditors->isEmpty())
                <p class="text-xs text-gray-500 italic py-2">هیچ تکنسین بستانکاری وجود ندارد.</p>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($creditors as $t)
                        <a href="{{ route('crm.wallet.show', $t) }}"
                           class="bg-white dark:bg-gray-800 rounded-lg border border-emerald-200 dark:border-emerald-800 shadow-sm p-4 hover:shadow-md hover:border-emerald-400 transition">
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $t->full_name }}</div>
                            <div class="mt-2 text-2xl font-bold text-emerald-600">
                                {{ number_format(abs((int) $t->true_balance)) }}
                                <span class="text-sm font-normal">تومان</span>
                            </div>
                            <div class="text-xs text-emerald-700 mt-1">بستانکار</div>
                            <div class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-700 text-[11px] text-gray-500 grid grid-cols-2 gap-1">
                                <span>کیف‌پول:</span>
                                <span class="text-left {{ $t->wallet_balance >= 0 ? 'text-emerald-700' : 'text-red-700' }}" dir="ltr">{{ number_format($t->wallet_balance) }}</span>
                                <span>سهم شرکت:</span>
                                <span class="text-left text-amber-700" dir="ltr">−{{ number_format($t->invoice_debt) }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        @php $hiddenZeros = $technicians->count() - $debtors->count() - $creditors->count(); @endphp
        @if($hiddenZeros > 0)
            <p class="text-xs text-gray-400 text-center">
                {{ number_format($hiddenZeros) }} تکنسین با مانده صفر در این لیست نمایش داده نشدند.
            </p>
        @endif
    </div>

    {{-- لیست تراکنش‌ها --}}
    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">تکنسین</label>
            <select name="technician_id" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                <option value="">— همه —</option>
                @foreach($technicians as $t)
                <option value="{{ $t->id }}" @selected($technicianId === $t->id)>{{ $t->full_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">نوع</label>
            <select name="type" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                <option value="">— همه —</option>
                @foreach($types as $k => $v)
                <option value="{{ $k }}" @selected($type === $k)>{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <button class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800 text-sm">فیلتر</button>
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">زمان</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تکنسین</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">نوع</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">مبلغ</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">پس از تراکنش</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">مرجع</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">توضیح</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($transactions as $tx)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-3 text-xs text-gray-600 whitespace-nowrap" dir="ltr">{{ $tx->created_at?->format('Y-m-d H:i') }}</td>
                    <td class="px-6 py-3 text-sm">{{ $tx->technician->full_name }}</td>
                    <td class="px-6 py-3">
                        <span class="px-2.5 py-1 text-xs font-medium rounded-full {{ $tx->type->badgeClass() }}">{{ $tx->type->label() }}</span>
                    </td>
                    <td class="px-6 py-3 text-sm font-bold {{ $tx->amount >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ ($tx->amount >= 0 ? '+' : '') . number_format($tx->amount) }}
                    </td>
                    <td class="px-6 py-3 text-sm">{{ number_format($tx->balance_after) }}</td>
                    <td class="px-6 py-3 text-xs">
                        @if($tx->invoice)
                        <a href="{{ route('crm.invoices.show', $tx->invoice) }}" class="text-brand-600 hover:underline" dir="ltr">{{ $tx->invoice->invoice_code }}</a>
                        @elseif($tx->order)
                        <a href="{{ route('crm.orders.show', $tx->order) }}" class="text-brand-600 hover:underline" dir="ltr">{{ $tx->order->order_code }}</a>
                        @else
                        —
                        @endif
                    </td>
                    <td class="px-6 py-3 text-xs text-gray-600">{{ $tx->note ?: '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-6 py-12 text-center text-gray-500">تراکنشی ثبت نشده.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $transactions->links() }}</div>
</div>
@endsection
