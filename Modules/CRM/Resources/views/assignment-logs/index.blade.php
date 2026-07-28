@extends('layouts.admin')

@section('page-title', 'گزارش تخصیص تکنسین')

@section('main')
<div class="p-4 md:p-6 space-y-4 max-w-7xl">

    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">🧭 گزارش تخصیص تکنسین</h1>
        <p class="text-xs text-gray-500 mt-1">
            برای هر سفارش ثبت می‌شود که تکنسین با چه منطقی انتخاب شده — دستی، پیشنهاد هوشمند، تخصیص گروهی یا پخش خودکار.
        </p>
    </div>

    {{-- ─── جمع‌بندی بر اساس حالت ─── --}}
    @if(! empty($byMode))
        <div class="grid grid-cols-2 md:grid-cols-6 gap-2">
            @foreach($modes as $key => $label)
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-2.5">
                    <div class="text-[10px] text-gray-500 leading-4">{{ $label }}</div>
                    <div class="text-lg font-bold mt-0.5">{{ number_format($byMode[$key] ?? 0) }}</div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ─── فیلترها ─── --}}
    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-3 grid grid-cols-1 md:grid-cols-6 gap-2">
        <input name="order_code" value="{{ $filters['orderCode'] }}" placeholder="کد سفارش"
               class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">

        <select name="technician_id" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm"
                data-searchable data-placeholder="همهٔ تکنسین‌ها">
            <option value="">همهٔ تکنسین‌ها</option>
            @foreach($technicians as $t)
                <option value="{{ $t->id }}" @selected($filters['technicianId'] == $t->id)>
                    {{ trim($t->firstname_tech ?: $t->first_name) ?: 'تکنسین #'.$t->id }}
                </option>
            @endforeach
        </select>

        <select name="mode" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            <option value="">همهٔ حالت‌ها</option>
            @foreach($modes as $key => $label)
                <option value="{{ $key }}" @selected($filters['mode'] === $key)>{{ $label }}</option>
            @endforeach
        </select>

        <input type="date" name="from" value="{{ $filters['from'] }}" dir="ltr"
               class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
        <input type="date" name="to" value="{{ $filters['to'] }}" dir="ltr"
               class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">

        <div class="flex gap-2">
            <button class="flex-1 px-3 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm">فیلتر</button>
            <a href="{{ route('crm.assignment-logs.index') }}" class="px-3 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm text-gray-600 dark:text-gray-300">پاک</a>
        </div>
    </form>

    {{-- ─── جدول ─── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/40 text-[11px] text-gray-500">
                    <tr>
                        <th class="px-3 py-2 text-right font-medium">زمان</th>
                        <th class="px-3 py-2 text-right font-medium">سفارش</th>
                        <th class="px-3 py-2 text-right font-medium">تکنسین</th>
                        <th class="px-3 py-2 text-right font-medium">حالت</th>
                        <th class="px-3 py-2 text-right font-medium">امتیاز</th>
                        <th class="px-3 py-2 text-right font-medium">دلیل</th>
                        <th class="px-3 py-2 text-right font-medium">توسط</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($logs as $log)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-3 py-2 text-[11px] text-gray-500 whitespace-nowrap" dir="ltr">@jdatetime($log->created_at)</td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                @if($log->order)
                                    <a href="{{ route('crm.orders.show', $log->order_id) }}" class="text-brand-600 hover:underline font-medium">
                                        {{ $log->order->order_code }}
                                    </a>
                                    <div class="text-[10px] text-gray-400">{{ $log->order->device?->name }}</div>
                                @else
                                    <span class="text-gray-400">#{{ $log->order_id }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                @if($log->technician)
                                    {{ trim($log->technician->firstname_tech ?: $log->technician->first_name) ?: 'تکنسین #'.$log->technician_id }}
                                @else
                                    <span class="text-amber-600 text-xs">— برداشته شد —</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="text-[10px] rounded px-1.5 py-0.5
                                    {{ $log->isAutomatic() ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                                    {{ $log->modeLabel() }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-center">
                                @if($log->score !== null)
                                    <span class="font-bold">{{ $log->score }}</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-[11px] text-gray-600 dark:text-gray-300 leading-5 max-w-lg">{{ $log->note }}</td>
                            <td class="px-3 py-2 text-[11px] text-gray-500 whitespace-nowrap">
                                {{ $log->assigner?->full_name ?? 'سیستم' }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-10 text-center text-gray-400 text-sm">رکوردی با این فیلترها پیدا نشد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $logs->links() }}
</div>
@endsection
