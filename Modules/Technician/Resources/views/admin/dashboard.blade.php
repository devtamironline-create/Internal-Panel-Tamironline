@extends('layouts.admin')

@section('page-title', 'داشبورد تکنسین‌ها')

@section('main')
@php use Morilog\Jalali\Jalalian; @endphp
<div class="p-6 space-y-4" dir="rtl">

    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-800 dark:text-white">🧰 داشبورد مدیریتی تکنسین‌ها</h1>
            <p class="text-sm text-gray-500 mt-1">
                رتبه‌بندی بر اساس تعداد سفارش، وضعیت لحظه‌ای کارها و کامل‌بودن پروفایل —
                بازه: <b>{{ $ranges[$range] ?? $range }}</b>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('crm.technicians.index') }}" class="px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg text-sm">مدیریت تکنسین‌ها</a>
            <a href="{{ route('technician.admin.registrations') }}" class="px-3 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm">درخواست‌های ثبت‌نام</a>
        </div>
    </div>

    {{-- کارت‌های خلاصه --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
        @foreach([
            ['label' => 'تکنسین‌ها', 'value' => $summary['technicians'], 'sub' => $summary['active'].' فعال · '.$summary['ready'].' آمادهٔ دریافت', 'tone' => 'gray'],
            ['label' => 'دارای سفارش', 'value' => $summary['with_orders'], 'sub' => $summary['idle'].' تکنسین بدون سفارش', 'tone' => 'blue'],
            ['label' => 'کل سفارش‌ها', 'value' => $summary['orders'], 'sub' => 'میانگین '.$summary['avg_orders'].' سفارش برای هر تکنسین', 'tone' => 'gray'],
            ['label' => 'در حال انجام', 'value' => $summary['in_progress'], 'sub' => 'هماهنگ‌شده و شروع تعمیر', 'tone' => 'purple'],
            ['label' => 'در انتظار', 'value' => $summary['waiting'], 'sub' => 'نیازمند پیگیری', 'tone' => 'amber'],
            ['label' => 'انجام کار', 'value' => $summary['completed'], 'sub' => 'از '.$summary['finished'].' سفارش پایان‌یافته', 'tone' => 'emerald'],
        ] as $card)
            <div @class([
                'rounded-xl border p-4',
                'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700' => $card['tone'] === 'gray',
                'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800' => $card['tone'] === 'blue',
                'bg-purple-50 dark:bg-purple-900/20 border-purple-200 dark:border-purple-800' => $card['tone'] === 'purple',
                'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800' => $card['tone'] === 'amber',
                'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800' => $card['tone'] === 'emerald',
            ])>
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $card['label'] }}</div>
                <div class="text-2xl font-black text-gray-800 dark:text-gray-100 mt-1">{{ number_format($card['value']) }}</div>
                <div class="text-[10px] text-gray-500 dark:text-gray-400 mt-1 leading-4">{{ $card['sub'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- هشدارهای عملیاتی --}}
    @if($unassignedOrders > 0 || $summary['incomplete_profiles'] > 0)
        <div class="flex flex-wrap gap-2">
            @if($unassignedOrders > 0)
                <a href="{{ route('crm.orders.index') }}"
                   class="flex-1 min-w-[240px] bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-300 rounded-lg p-3 text-sm">
                    ⚠️ <b>{{ number_format($unassignedOrders) }}</b> سفارش در این بازه هیچ تکنسینی ندارد — صف تخصیص را بررسی کنید.
                </a>
            @endif
            @if($summary['incomplete_profiles'] > 0)
                <a href="{{ route('technician.admin.dashboard', array_merge(request()->query(), ['sort' => 'profile'])) }}"
                   class="flex-1 min-w-[240px] bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300 rounded-lg p-3 text-sm">
                    📋 پروفایل <b>{{ number_format($summary['incomplete_profiles']) }}</b> تکنسین کامل نیست — برای دیدن موارد ناقص روی همین کادر بزنید.
                </a>
            @endif
        </div>
    @endif

    {{-- نوار تفکیک وضعیت کل سفارش‌ها --}}
    @if($statusTotals)
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">وضعیت همهٔ سفارش‌های تخصیص‌یافته</div>
            <div class="flex flex-wrap gap-2">
                @foreach($statusTotals as $s)
                    <a href="{{ route('crm.orders.index', ['status' => $s['value']]) }}"
                       class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs {{ $s['badge'] }} hover:ring-2 ring-offset-1 ring-brand-300 transition">
                        <span>{{ $s['label'] }}</span>
                        <span class="font-black">{{ number_format($s['count']) }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- فیلترها --}}
    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-3 flex flex-wrap items-end gap-2 text-sm">
        <label class="block text-xs text-gray-500">جستجو
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="نام یا موبایل…"
                   class="mt-1 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm w-44">
        </label>
        <label class="block text-xs text-gray-500">بازهٔ زمانی
            <select name="range" class="mt-1 px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                @foreach($ranges as $key => $label)
                    <option value="{{ $key }}" @selected($filters['range'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-xs text-gray-500">مرتب‌سازی
            <select name="sort" class="mt-1 px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                @foreach($sorts as $key => $label)
                    <option value="{{ $key }}" @selected($filters['sort'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-xs text-gray-500">وضعیت تکنسین
            <select name="status" class="mt-1 px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                <option value="">همه</option>
                <option value="active" @selected($filters['status'] === 'active')>فعال</option>
                <option value="ready" @selected($filters['status'] === 'ready')>آمادهٔ دریافت سفارش</option>
                <option value="inactive" @selected($filters['status'] === 'inactive')>غیرفعال</option>
            </select>
        </label>
        <label class="block text-xs text-gray-500">استان
            <select name="province" class="mt-1 px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                <option value="">همهٔ استان‌ها</option>
                @foreach($provinces as $p)
                    <option value="{{ $p }}" @selected($filters['province'] === $p)>{{ $p }}</option>
                @endforeach
            </select>
        </label>
        <label class="inline-flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300 pb-2">
            <input type="checkbox" name="only_with_orders" value="1" @checked($filters['only_with_orders'])
                   class="rounded border-gray-300 text-brand-600">
            فقط دارای سفارش
        </label>
        <button class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">اعمال</button>
        <a href="{{ route('technician.admin.dashboard') }}" class="px-3 py-2 text-gray-500 text-sm">پاک‌کردن</a>
    </form>

    {{-- جدول رتبه‌بندی --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50 text-[11px] text-gray-500">
                    <tr>
                        <th class="p-3 w-10">#</th>
                        <th class="p-3 text-right">تکنسین</th>
                        <th class="p-3">کل سفارش</th>
                        <th class="p-3">در جریان</th>
                        <th class="p-3">در انتظار</th>
                        <th class="p-3">انجام کار</th>
                        <th class="p-3">نرخ تکمیل</th>
                        <th class="p-3">لغو/رد</th>
                        <th class="p-3">گردش مالی</th>
                        <th class="p-3">آخرین سفارش</th>
                        <th class="p-3">پروفایل</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                @forelse($rows as $i => $row)
                    @php($tech = $row['technician'])
                    {{-- هر تکنسین یک tbody مستقل: ردیف اصلی و ردیف جزئیات باید
                         اسکوپ Alpine مشترک داشته باشند (دو <tr> خواهر نمی‌توانند). --}}
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700 border-t border-gray-100 dark:border-gray-700" x-data="{ open: false }">
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40 align-top">
                            <td class="p-3 text-center">
                                <span @class([
                                    'inline-flex items-center justify-center w-7 h-7 rounded-full text-[11px] font-black',
                                    'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => $i < 3 && $row['total'] > 0,
                                    'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' => ! ($i < 3 && $row['total'] > 0),
                                ])>{{ $i + 1 }}</span>
                            </td>
                            <td class="p-3">
                                <div class="font-medium text-gray-800 dark:text-gray-100">{{ $tech->display_name }}</div>
                                <div class="text-[11px] text-gray-400 flex items-center gap-1.5 flex-wrap" dir="ltr">
                                    <span>{{ $tech->mobile }}</span>
                                    @if($tech->province)<span class="text-gray-300">·</span><span dir="rtl">{{ $tech->province }}</span>@endif
                                </div>
                                <div class="flex items-center gap-1 mt-1">
                                    @if($tech->status === 'active')
                                        <span class="px-1.5 py-0.5 rounded text-[10px] bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">فعال</span>
                                    @else
                                        <span class="px-1.5 py-0.5 rounded text-[10px] bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">غیرفعال</span>
                                    @endif
                                    @if($tech->ready_for_delivery)
                                        <span class="px-1.5 py-0.5 rounded text-[10px] bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">آمادهٔ دریافت</span>
                                    @endif
                                    @if($tech->percent)
                                        <span class="px-1.5 py-0.5 rounded text-[10px] bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300" dir="ltr">{{ $tech->percent }}%</span>
                                    @endif
                                </div>
                            </td>
                            <td class="p-3 text-center">
                                <span class="text-lg font-black text-gray-800 dark:text-gray-100">{{ number_format($row['total']) }}</span>
                            </td>
                            <td class="p-3 text-center">
                                <span @class(['font-bold', 'text-purple-600' => $row['groups']['in_progress'] > 0, 'text-gray-300 dark:text-gray-600' => $row['groups']['in_progress'] === 0])>
                                    {{ number_format($row['groups']['in_progress']) }}
                                </span>
                            </td>
                            <td class="p-3 text-center">
                                <span @class(['font-bold', 'text-amber-600' => $row['groups']['waiting'] > 0, 'text-gray-300 dark:text-gray-600' => $row['groups']['waiting'] === 0])>
                                    {{ number_format($row['groups']['waiting']) }}
                                </span>
                            </td>
                            <td class="p-3 text-center">
                                <span @class(['font-bold', 'text-emerald-600' => $row['completed'] > 0, 'text-gray-300 dark:text-gray-600' => $row['completed'] === 0])>
                                    {{ number_format($row['completed']) }}
                                </span>
                            </td>
                            <td class="p-3 text-center">
                                @if($row['completion_rate'] === null)
                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                @else
                                    <div class="flex items-center gap-1.5 justify-center">
                                        <div class="w-12 h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                            <div @class([
                                                'h-full rounded-full',
                                                'bg-emerald-500' => $row['completion_rate'] >= 70,
                                                'bg-amber-500' => $row['completion_rate'] >= 40 && $row['completion_rate'] < 70,
                                                'bg-red-500' => $row['completion_rate'] < 40,
                                            ]) style="width: {{ $row['completion_rate'] }}%"></div>
                                        </div>
                                        <span class="text-[11px] text-gray-500" dir="ltr">{{ $row['completion_rate'] }}%</span>
                                    </div>
                                @endif
                            </td>
                            <td class="p-3 text-center">
                                <span @class(['text-xs', 'text-red-600 font-bold' => $row['cancelled'] > 0, 'text-gray-300 dark:text-gray-600' => $row['cancelled'] === 0])>
                                    {{ number_format($row['cancelled']) }}
                                </span>
                            </td>
                            <td class="p-3 text-center text-xs text-gray-600 dark:text-gray-300" dir="ltr">
                                {{ $row['revenue'] > 0 ? number_format($row['revenue']) : '—' }}
                            </td>
                            <td class="p-3 text-center text-[11px] text-gray-500" dir="ltr">
                                {{ $row['last_at'] ? Jalalian::fromDateTime($row['last_at'])->format('Y/m/d') : '—' }}
                            </td>
                            <td class="p-3 text-center">
                                <button @click="open = !open" class="inline-flex items-center gap-1">
                                    <span @class([
                                        'text-[11px] font-bold',
                                        'text-emerald-600' => $row['profile']['percent'] === 100,
                                        'text-amber-600' => $row['profile']['percent'] < 100 && $row['profile']['percent'] >= 70,
                                        'text-red-600' => $row['profile']['percent'] < 70,
                                    ]) dir="ltr">{{ $row['profile']['percent'] }}%</span>
                                </button>
                            </td>
                            <td class="p-3 text-center whitespace-nowrap">
                                <button @click="open = !open" class="text-[11px] text-brand-600 hover:underline">
                                    <span x-text="open ? 'بستن' : 'جزئیات'"></span>
                                </button>
                            </td>
                        </tr>
                        {{-- ردیف جزئیات: چه سفارش‌هایی و در چه وضعیتی --}}
                        <tr x-show="open" x-cloak class="bg-gray-50/70 dark:bg-gray-700/20">
                            <td></td>
                            <td colspan="11" class="px-3 pb-4">
                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                                    <div class="lg:col-span-2">
                                        <div class="text-[11px] font-bold text-gray-600 dark:text-gray-300 mb-2">سفارش‌های این تکنسین به تفکیک وضعیت</div>
                                        @if($row['statuses'])
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach($row['statuses'] as $s)
                                                    <a href="{{ route('crm.orders.index', ['technician_id' => $tech->id, 'status' => $s['value']]) }}"
                                                       class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] {{ $s['badge'] }} hover:ring-2 ring-offset-1 ring-brand-300 transition">
                                                        <span>{{ $s['label'] }}</span>
                                                        <span class="font-black">{{ number_format($s['count']) }}</span>
                                                    </a>
                                                @endforeach
                                            </div>
                                            <a href="{{ route('crm.orders.index', ['technician_id' => $tech->id]) }}"
                                               class="inline-block mt-2 text-[11px] text-brand-600 hover:underline">مشاهدهٔ همهٔ سفارش‌های این تکنسین ←</a>
                                        @else
                                            <p class="text-[11px] text-gray-400">در این بازه هیچ سفارشی به این تکنسین تخصیص نیافته است.</p>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="text-[11px] font-bold text-gray-600 dark:text-gray-300 mb-2">وضعیت پروفایل</div>
                                        @if($row['profile']['missing'])
                                            <div class="text-[11px] text-gray-500 mb-1">موارد تکمیل‌نشده:</div>
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($row['profile']['missing'] as $missing)
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] bg-red-50 text-red-700 border border-red-100 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800">{{ $missing }}</span>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="text-[11px] text-emerald-600">پروفایل کامل است ✅</p>
                                        @endif
                                        <div class="flex gap-2 mt-3">
                                            <a href="{{ route('crm.technicians.show', $tech) }}" class="px-2.5 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg text-[11px]">پروفایل کامل</a>
                                            @can('edit-crm-technician')
                                                <a href="{{ route('crm.technicians.edit', $tech) }}" class="px-2.5 py-1.5 bg-brand-600 text-white rounded-lg text-[11px]">ویرایش</a>
                                            @endcan
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <tbody>
                        <tr>
                            <td colspan="12" class="text-center py-12 text-gray-400">
                                <div class="text-3xl mb-2">🧰</div>
                                <p class="text-sm">تکنسینی با این فیلترها پیدا نشد.</p>
                            </td>
                        </tr>
                    </tbody>
                @endforelse
            </table>
        </div>
    </div>

    <p class="text-[11px] text-gray-400">
        «در جریان» = هماهنگ‌شده و شروع تعمیر · «در انتظار» = جدید، در انتظار هماهنگی، معلق، انتظار قطعه و مشابه ·
        گردش مالی بر اساس همان اولویت محاسبات کمیسیون (مبلغ مشتری ← فاکتور ← مبلغ نهایی) محاسبه می‌شود.
    </p>
</div>
@endsection
