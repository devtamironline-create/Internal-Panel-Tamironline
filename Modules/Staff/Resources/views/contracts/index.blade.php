@extends('layouts.admin')

@section('page-title', 'قرارداد کارمندان')

@section('main')
@php use Modules\Staff\Models\StaffContract; use Morilog\Jalali\Jalalian; @endphp
<div class="p-6 space-y-4" dir="rtl">

    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-800 dark:text-white">📄 قرارداد کارمندان</h1>
            <p class="text-sm text-gray-500 mt-1">صدور قرارداد برای پرسنل، بررسی مدارک و امضای الکترونیک، و تحویل نسخهٔ مهر و امضاشده.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.staff-contracts.settings') }}" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg text-sm font-bold whitespace-nowrap">⚙️ تنظیمات جدول حقوق</a>
            <a href="{{ route('admin.staff-contracts.create') }}" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold whitespace-nowrap">➕ صدور قرارداد جدید</a>
        </div>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">{{ $errors->first() }}</div>@endif

    {{-- آمار وضعیت --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach(StaffContract::STATUSES as $key => $label)
            @php($tone = ['awaiting_staff' => 'amber', 'submitted' => 'blue', 'approved' => 'emerald', 'rejected' => 'red'][$key])
            <a href="{{ route('admin.staff-contracts.index', ['status' => $key]) }}"
               @class([
                   'rounded-xl border p-3 text-center hover:shadow-sm transition',
                   'bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800' => $tone === 'amber',
                   'bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-800' => $tone === 'blue',
                   'bg-emerald-50 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800' => $tone === 'emerald',
                   'bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800' => $tone === 'red',
                   'ring-2 ring-brand-400' => ($filters['status'] ?? '') === $key,
               ])>
                <div class="text-xl font-black text-gray-800 dark:text-gray-100">{{ number_format($counts[$key] ?? 0) }}</div>
                <div class="text-[11px] text-gray-500 dark:text-gray-400">{{ $label }}</div>
            </a>
        @endforeach
    </div>

    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-3 flex flex-wrap items-end gap-2 text-sm">
        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="نام، شماره قرارداد یا کد ملی…"
               class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm w-56">
        <select name="status" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <option value="">همهٔ وضعیت‌ها</option>
            @foreach(StaffContract::STATUSES as $k => $v)<option value="{{ $k }}" @selected($filters['status'] === $k)>{{ $v }}</option>@endforeach
        </select>
        <button class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm">اعمال</button>
        <a href="{{ route('admin.staff-contracts.index') }}" class="px-3 py-2 text-gray-500 text-sm">پاک‌کردن</a>
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50 text-[11px] text-gray-500">
                    <tr>
                        <th class="p-3 text-right">شماره قرارداد</th>
                        <th class="p-3 text-right">کارمند</th>
                        <th class="p-3">مدت</th>
                        <th class="p-3">پیشرفت تکمیل</th>
                        <th class="p-3">وضعیت</th>
                        <th class="p-3">تأییدکننده</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($contracts as $c)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="p-3 font-mono text-xs text-gray-700 dark:text-gray-200" dir="ltr">{{ $c->contract_number }}</td>
                            <td class="p-3">
                                <div class="font-medium text-gray-800 dark:text-gray-100">{{ $c->party_name }}</div>
                                <div class="text-[11px] text-gray-400" dir="ltr">{{ $c->party_phone }}</div>
                            </td>
                            <td class="p-3 text-center text-[11px] text-gray-500" dir="ltr">
                                {{ Jalalian::fromDateTime($c->start_date)->format('Y/m/d') }}
                                @if($c->end_date) — {{ Jalalian::fromDateTime($c->end_date)->format('Y/m/d') }} @endif
                            </td>
                            <td class="p-3">
                                <div class="flex items-center gap-2 justify-center">
                                    <div class="w-16 h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                        @php($p = $c->completionPercent())
                                        <div @class(['h-full rounded-full', 'bg-emerald-500' => $p === 100, 'bg-amber-500' => $p > 0 && $p < 100, 'bg-gray-300' => $p === 0]) style="width: {{ $p }}%"></div>
                                    </div>
                                    <span class="text-[11px] text-gray-500" dir="ltr">{{ $p }}%</span>
                                </div>
                            </td>
                            <td class="p-3 text-center">
                                @php($tone = ['awaiting_staff' => 'amber', 'submitted' => 'blue', 'approved' => 'emerald', 'rejected' => 'red'][$c->status] ?? 'gray')
                                <span @class([
                                    'inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium',
                                    'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => $tone === 'amber',
                                    'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' => $tone === 'blue',
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $tone === 'emerald',
                                    'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => $tone === 'red',
                                ])>{{ $c->statusLabel() }}</span>
                            </td>
                            <td class="p-3 text-center text-[11px] text-gray-500">{{ $c->approver?->full_name ?: '—' }}</td>
                            <td class="p-3 text-center whitespace-nowrap">
                                <a href="{{ route('admin.staff-contracts.show', $c) }}" class="text-xs text-brand-600 hover:underline">بررسی</a>
                                @if($c->status === 'approved' && $c->final_pdf_path)
                                    <a href="{{ route('admin.staff-contracts.download', $c) }}" class="text-xs text-emerald-600 hover:underline mr-2">PDF</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-12 text-gray-400">
                            <div class="text-3xl mb-2">📄</div>
                            <p class="text-sm">هنوز قراردادی صادر نشده است.</p>
                            <a href="{{ route('admin.staff-contracts.create') }}" class="text-xs text-brand-600 hover:underline">صدور اولین قرارداد</a>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $contracts->links() }}</div>
</div>
@endsection
