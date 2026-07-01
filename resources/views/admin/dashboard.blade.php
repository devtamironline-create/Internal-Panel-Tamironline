@extends('layouts.admin')
@section('page-title', 'داشبورد')
@section('main')
@php($r = $stats['repair'] ?? [])
<div class="space-y-6">

    {{-- ─── خوش‌آمد ─── --}}
    <div class="bg-gradient-to-r from-gray-700 to-gray-800 dark:from-gray-800 dark:to-gray-900 rounded-xl shadow-sm p-6 text-white border border-gray-600 dark:border-gray-700">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-xl font-bold">سلام {{ auth()->user()->first_name }}!</h2>
                <p class="text-gray-300 mt-1">{{ \Morilog\Jalali\Jalalian::now()->format('l، j F Y') }}</p>
                <p class="text-gray-400 text-sm mt-1">وضعیت کلیِ خدمات تعمیرات</p>
            </div>
            @canany(['view-crm-dashboard', 'view-crm-orders', 'manage-permissions'])
            @if(Route::has('crm.dashboard'))
            <a href="{{ route('crm.dashboard') }}" class="px-4 py-2 bg-white/15 rounded-lg hover:bg-white/25 transition text-sm font-medium">
                داشبورد کاملِ تعمیرات ↗
            </a>
            @endif
            @endcanany
        </div>
    </div>

    @canany(['view-crm-dashboard', 'view-crm-orders', 'view-crm-reports', 'manage-permissions'])

    {{-- ─── کاشی‌های وضعیت ─── --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        {{-- سفارش‌های باز --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($r['open'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">سفارش‌های باز</p>
        </div>
        {{-- ثبت‌شده امروز --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($r['today_new'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">ثبت‌شده امروز</p>
        </div>
        {{-- تکمیل‌شده امروز --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($r['today_completed'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">تکمیل‌شده امروز</p>
        </div>
        {{-- این ماه --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-3xl font-bold text-gray-800 dark:text-gray-100">{{ number_format($r['month_total'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">سفارش‌های این ماه</p>
        </div>
        {{-- معطل بیش از ۳ روز --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border {{ ($r['delayed_open'] ?? 0) > 0 ? 'border-rose-200 dark:border-rose-800' : 'border-gray-100 dark:border-gray-700' }}">
            <p class="text-3xl font-bold {{ ($r['delayed_open'] ?? 0) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-800 dark:text-gray-100' }}">{{ number_format($r['delayed_open'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">معطل (+۳ روز)</p>
        </div>
        {{-- تکنسین‌های فعال --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-3xl font-bold text-amber-600 dark:text-amber-400">{{ number_format($r['techs_active'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">تکنسین فعال</p>
        </div>
    </div>

    {{-- ─── خلاصهٔ ماه + مشتری‌ها ─── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-sm text-gray-500 dark:text-gray-400">تکمیل‌شدهٔ این ماه</p>
            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ number_format($r['month_completed'] ?? 0) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-sm text-gray-500 dark:text-gray-400">لغو/رد‌شدهٔ این ماه</p>
            <p class="text-2xl font-bold text-rose-600 dark:text-rose-400 mt-1">{{ number_format($r['month_cancelled'] ?? 0) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-sm text-gray-500 dark:text-gray-400">کل مشتری‌ها</p>
            <p class="text-2xl font-bold text-gray-800 dark:text-gray-100 mt-1">{{ number_format($r['customers_total'] ?? 0) }}</p>
        </div>
    </div>

    {{-- ─── آخرین سفارش‌های تعمیر ─── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100">آخرین سفارش‌های تعمیر</h3>
            @if(Route::has('crm.orders.index'))
            <a href="{{ route('crm.orders.index') }}" class="text-xs text-blue-600 hover:underline">همهٔ سفارش‌ها ↗</a>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900 text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-2 text-right font-medium">مشتری</th>
                        <th class="px-4 py-2 text-right font-medium">دستگاه</th>
                        <th class="px-4 py-2 text-right font-medium">تکنسین</th>
                        <th class="px-4 py-2 text-right font-medium">وضعیت</th>
                        <th class="px-4 py-2 text-right font-medium">تاریخ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($recentOrders as $o)
                        @php($tech = trim(($o->technician->first_name ?? '').' '.($o->technician->last_name ?? '')) ?: ($o->technician->firstname_tech ?? '—'))
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-2 text-gray-800 dark:text-gray-100">
                                @if(Route::has('crm.orders.show'))
                                <a href="{{ route('crm.orders.show', $o) }}" class="hover:underline">{{ $o->customer->first_name ?? '—' }}</a>
                                @else
                                {{ $o->customer->first_name ?? '—' }}
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ $o->device->name ?? '—' }}{{ $o->brand ? ' / '.$o->brand->name : '' }}</td>
                            <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ $tech }}</td>
                            <td class="px-4 py-2">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs {{ $o->status?->badgeClass() ?? 'bg-gray-100 text-gray-700' }}">{{ $o->status?->label() ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-2 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $o->created_at ? \Morilog\Jalali\Jalalian::fromCarbon($o->created_at)->format('Y/m/d H:i') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">سفارشی برای نمایش نیست.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @else
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-8 text-center text-gray-500 border border-gray-100 dark:border-gray-700">
        به پنل تعمیرآنلاین خوش آمدید.
    </div>
    @endcanany

    {{-- ─── تولدهای پیشِ‌رو ─── --}}
    @if(!empty($stats['birthdays']['today']) || !empty($stats['birthdays']['upcoming']))
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
        <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100 mb-3">🎂 تولدهای پیشِ‌رو</h3>
        <div class="flex flex-wrap gap-2">
            @foreach($stats['birthdays']['today'] as $b)
                <span class="px-3 py-1.5 rounded-lg bg-pink-50 dark:bg-pink-900/20 text-pink-700 dark:text-pink-300 text-sm">🎉 {{ $b['name'] }} — امروز ({{ $b['age'] }} سالگی)</span>
            @endforeach
            @foreach($stats['birthdays']['upcoming'] as $b)
                <span class="px-3 py-1.5 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm">{{ $b['name'] }} — {{ $b['jalali_date'] }} ({{ $b['days_until'] }} روز دیگر)</span>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection
