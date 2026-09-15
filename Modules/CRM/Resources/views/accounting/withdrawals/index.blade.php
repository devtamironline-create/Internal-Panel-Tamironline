@extends('layouts.admin')

@section('page-title', 'برداشت سرمایه')

@section('main')
<div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">برداشت سرمایه (مالک/شرکا)</h1>
            <p class="text-sm text-gray-500 mt-1">خروجِ وجه توسط مالک/شرکا — جدا از هزینه‌های شرکت و بدون اثر بر سود.</p>
        </div>
        <a href="{{ route('crm.withdrawals.create') }}" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm font-bold">+ ثبت برداشت سرمایه</a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm">{{ session('error') }}</div>
    @endif

    {{-- فیلتر --}}
    <form method="GET" action="{{ route('crm.withdrawals.index') }}"
          class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <div>
            <label class="block text-xs text-gray-500 mb-1">از تاریخ</label>
            <input type="text" name="from_date" value="{{ $filters['from_date'] }}" dir="ltr" readonly
                   class="jalali-datepicker w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg bg-white text-sm">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">تا تاریخ</label>
            <input type="text" name="to_date" value="{{ $filters['to_date'] }}" dir="ltr" readonly
                   class="jalali-datepicker w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg bg-white text-sm">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">حساب پرداخت</label>
            <select name="account_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                <option value="">همه</option>
                @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}" @selected($filters['account_id'] == $acc->id)>{{ $acc->title }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-2">
            <button class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800 text-sm">اعمال فیلتر</button>
            @if($hasFilter)
                <a href="{{ route('crm.withdrawals.index') }}" class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm">حذف</a>
            @endif
        </div>
    </form>

    {{-- KPI --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="text-xs text-gray-400">برداشت سرمایهٔ این ماه</div>
            <div class="text-2xl font-bold text-indigo-600 mt-1">{{ number_format($monthTotal) }} <span class="text-xs font-normal text-gray-400">تومان</span></div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="text-xs text-gray-400">جمع مبلغ (با فیلتر فعلی)</div>
            <div class="text-2xl font-bold text-gray-800 dark:text-gray-100 mt-1">{{ number_format($totalAmount) }} <span class="text-xs font-normal text-gray-400">تومان</span></div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="text-xs text-gray-400">تعداد برداشت</div>
            <div class="text-2xl font-bold text-gray-800 dark:text-gray-100 mt-1">{{ number_format($totalCount) }}</div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/40 text-xs text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="p-3 text-start">تاریخ</th>
                    <th class="p-3 text-start">شرح</th>
                    <th class="p-3 text-start">مبلغ</th>
                    <th class="p-3 text-start">حساب پرداخت</th>
                    <th class="p-3 text-start">دریافت‌کننده</th>
                    <th class="p-3 text-start">ثبت‌کننده</th>
                    <th class="p-3 text-start w-28"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($withdrawals as $w)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                        <td class="p-3 text-xs text-gray-500 whitespace-nowrap" dir="ltr">@jdatetime($w->withdrawn_at)</td>
                        <td class="p-3">
                            <div class="font-medium">{{ $w->description ?: 'برداشت سرمایه' }}</div>
                            <span class="inline-block mt-1 text-[10.5px] px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">برداشت سرمایه</span>
                            @if($w->attachment_path)
                                <a href="{{ route('crm.withdrawals.attachment', $w) }}" target="_blank" class="text-[10.5px] text-brand-700 hover:underline ms-1">📎 ضمیمه</a>
                            @endif
                        </td>
                        <td class="p-3 font-bold text-indigo-600 whitespace-nowrap" dir="ltr">{{ number_format($w->amount) }}</td>
                        <td class="p-3 text-xs text-gray-600 dark:text-gray-300">{{ $w->account?->title ?? '—' }}</td>
                        <td class="p-3 text-xs text-gray-600 dark:text-gray-300">{{ $w->payee ?: '—' }}</td>
                        <td class="p-3 text-xs text-gray-500">{{ $w->creator?->first_name }} {{ $w->creator?->last_name }}</td>
                        <td class="p-3">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('crm.withdrawals.edit', $w) }}" class="text-xs text-brand-700 hover:underline">ویرایش</a>
                                <form method="POST" action="{{ route('crm.withdrawals.destroy', $w) }}"
                                      onsubmit="return confirm('این برداشت حذف شود؟ (قابلِ بازیابی از سابقه)');">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-rose-600 hover:underline">حذف</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-8 text-center text-gray-400 text-sm">هیچ برداشتی ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $withdrawals->links() }}
</div>
@endsection
