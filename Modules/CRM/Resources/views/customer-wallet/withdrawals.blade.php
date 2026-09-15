@extends('layouts.admin')

@section('page-title', 'درخواست‌های برداشت مشتری')

@section('main')
<div class="space-y-4" dir="rtl">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">درخواست‌های برداشت مشتری</h1>
    </div>

    @if(session('success'))<div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm">{{ $errors->first() }}</div>@endif

    {{-- فیلترِ وضعیت --}}
    <div class="flex flex-wrap gap-2 text-sm">
        @foreach(['pending' => 'در انتظار', 'paid' => 'واریزشده', 'rejected' => 'ردشده', 'canceled' => 'لغوشده'] as $key => $label)
            <a href="{{ route('crm.customer-wallet.withdrawals', ['status' => $key]) }}"
               class="px-3 py-1.5 rounded-full border {{ $status === $key ? 'bg-brand-600 text-white border-brand-600' : 'bg-white dark:bg-gray-800 text-gray-600 border-gray-200 dark:border-gray-700' }}">
                {{ $label }} <span class="text-xs opacity-70">({{ (int) ($counts[$key] ?? 0) }})</span>
            </a>
        @endforeach
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/40 text-xs text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="p-3 text-start">تاریخ</th>
                    <th class="p-3 text-start">مشتری</th>
                    <th class="p-3 text-start">مبلغ</th>
                    <th class="p-3 text-start">مقصد واریز</th>
                    <th class="p-3 text-start">وضعیت</th>
                    <th class="p-3 text-start w-64"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($requests as $r)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 align-top">
                        <td class="p-3 text-xs text-gray-500 whitespace-nowrap" dir="ltr">@jdatetime($r->created_at)</td>
                        <td class="p-3">
                            <a href="{{ route('crm.customer-wallet.show', $r->customer_id) }}" class="text-brand-700 hover:underline">
                                {{ $r->customer?->full_name ?: '—' }}
                            </a>
                            <div class="text-[11px] text-gray-400" dir="ltr">{{ $r->customer?->mobile }}</div>
                            <div class="text-[11px] text-gray-400">موجودی فعلی: {{ number_format((int) ($r->customer?->wallet_balance ?? 0)) }}</div>
                        </td>
                        <td class="p-3 font-bold text-amber-600 whitespace-nowrap" dir="ltr">{{ number_format($r->amount) }}</td>
                        <td class="p-3 text-xs text-gray-600 dark:text-gray-300">
                            @if($r->sheba)<div dir="ltr">شبا: {{ $r->sheba }}</div>@endif
                            @if($r->card_number)<div dir="ltr">کارت: {{ $r->card_number }}</div>@endif
                            @if($r->account_holder)<div>به‌نام: {{ $r->account_holder }}</div>@endif
                            @if($r->customer_note)<div class="text-gray-400 mt-1">یادداشت: {{ $r->customer_note }}</div>@endif
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $r->statusBadge() }}">{{ $r->statusLabel() }}</span>
                            @if($r->admin_note)<div class="text-[11px] text-gray-400 mt-1">{{ $r->admin_note }}</div>@endif
                        </td>
                        <td class="p-3">
                            @if($r->isPending())
                                <form method="POST" action="{{ route('crm.customer-wallet.withdrawals.approve', $r) }}" class="mb-2"
                                      onsubmit="return confirm('واریز انجام شد و درخواست تایید شود؟');">
                                    @csrf @method('PUT')
                                    <input type="text" name="admin_note" placeholder="یادداشت (اختیاری)"
                                           class="w-full mb-1 px-2 py-1 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-[11px]">
                                    <button class="w-full py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-bold">✅ تایید و واریز شد</button>
                                </form>
                                <form method="POST" action="{{ route('crm.customer-wallet.withdrawals.reject', $r) }}"
                                      onsubmit="return confirm('درخواست رد و مبلغ به کیف‌پول برگردد؟');">
                                    @csrf @method('PUT')
                                    <input type="text" name="admin_note" required placeholder="دلیل رد (الزامی)"
                                           class="w-full mb-1 px-2 py-1 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-[11px]">
                                    <button class="w-full py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded text-xs">✖️ رد و بازگشت وجه</button>
                                </form>
                            @else
                                <span class="text-[11px] text-gray-400">
                                    {{ $r->processor?->full_name }} @if($r->processed_at)· @jdatetime($r->processed_at)@endif
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-8 text-center text-gray-400 text-sm">درخواستی در این وضعیت نیست.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $requests->links() }}
</div>
@endsection
