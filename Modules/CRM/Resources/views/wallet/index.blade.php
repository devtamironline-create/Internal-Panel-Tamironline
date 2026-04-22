@extends('layouts.admin')

@section('page-title', 'کیف‌پول تکنسین‌ها')

@section('main')
@php use Modules\CRM\Enums\WalletTxType; @endphp
<div class="p-6 space-y-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">کیف‌پول تکنسین‌ها</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">موجودی و تراکنش‌های مالی تکنسین‌ها</p>
    </div>

    {{-- خلاصه موجودی هر تکنسین --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($technicians as $t)
        <a href="{{ route('crm.wallet.show', $t) }}" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 hover:shadow-md transition">
            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ trim($t->first_name . ' ' . ($t->last_name ?? '')) }}</div>
            <div class="mt-2 text-2xl font-bold {{ $t->wallet_balance >= 0 ? 'text-green-600' : 'text-red-600' }}">
                {{ number_format(abs($t->wallet_balance)) }} <span class="text-sm font-normal">تومان</span>
            </div>
            <div class="text-xs text-gray-500 mt-1">
                {{ $t->wallet_balance >= 0 ? 'بستانکار' : 'بدهکار' }}
            </div>
        </a>
        @endforeach
    </div>

    {{-- لیست تراکنش‌ها --}}
    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">تکنسین</label>
            <select name="technician_id" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                <option value="">— همه —</option>
                @foreach($technicians as $t)
                <option value="{{ $t->id }}" @selected($technicianId === $t->id)>{{ trim($t->first_name . ' ' . ($t->last_name ?? '')) }}</option>
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
                    <td class="px-6 py-3 text-sm">{{ trim($tx->technician->first_name . ' ' . ($tx->technician->last_name ?? '')) }}</td>
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
