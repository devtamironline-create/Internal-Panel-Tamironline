@extends('layouts.admin')

@section('page-title', 'گزارش فعالیت')

@section('main')
@php use App\Support\ActivityLog\ActivityRegistry; use Morilog\Jalali\Jalalian; @endphp
<div class="p-6 space-y-4" dir="rtl">

    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-800 dark:text-white">🗂️ گزارش فعالیت</h1>
            <p class="text-sm text-gray-500 mt-1 leading-6">
                همهٔ اکشن‌های مهم (ایجاد، ویرایش، حذف، ورود و خروج) در فایل‌های روزانه ثبت می‌شوند.
                سوابق <b>{{ $retentionDays }} روز</b> نگهداری و سپس خودکار پاک می‌شوند.
                @if($totalSize > 0)
                    <span class="text-gray-400">· حجم فعلی: {{ number_format($totalSize / 1048576, 2) }} مگابایت</span>
                @endif
            </p>
        </div>
        <a href="{{ route('admin.activity-log.export', request()->query()) }}"
           class="px-3 py-2 bg-gray-700 hover:bg-gray-800 text-white rounded-lg text-sm">⬇️ خروجی CSV</a>
    </div>

    {{-- آمار سریع بر اساس فیلتر فعلی --}}
    @if($stats)
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-2">
            @foreach($stats as $action => $count)
                @php($color = ActivityRegistry::ACTION_COLORS[$action] ?? 'gray')
                <a href="{{ route('admin.activity-log.index', array_merge(request()->except('page'), ['action' => $action])) }}"
                   @class([
                       'rounded-xl border p-3 text-center hover:shadow-sm transition',
                       'border-emerald-200 bg-emerald-50 dark:bg-emerald-900/20 dark:border-emerald-800' => $color === 'emerald',
                       'border-blue-200 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800' => $color === 'blue',
                       'border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800' => $color === 'red',
                       'border-amber-200 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-800' => $color === 'amber',
                       'border-gray-200 bg-gray-50 dark:bg-gray-800 dark:border-gray-700' => $color === 'gray',
                   ])>
                    <div class="text-lg font-black text-gray-800 dark:text-gray-100">{{ number_format($count) }}</div>
                    <div class="text-[11px] text-gray-500 dark:text-gray-400">{{ $actions[$action] ?? $action }}</div>
                </a>
            @endforeach
        </div>
    @endif

    {{-- فیلترها --}}
    <form method="GET" action="{{ route('admin.activity-log.index') }}"
          class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-3 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-2 text-sm">
        <label class="block text-xs text-gray-500">از تاریخ
            <input type="date" name="from" value="{{ $filters['from'] }}" dir="ltr"
                   class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
        </label>
        <label class="block text-xs text-gray-500">تا تاریخ
            <input type="date" name="to" value="{{ $filters['to'] }}" dir="ltr"
                   class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
        </label>
        <label class="block text-xs text-gray-500">اقدام
            <select name="action" class="mt-1 w-full px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                <option value="">همهٔ اقدام‌ها</option>
                @foreach($actions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['action'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-xs text-gray-500">کاربر
            <select name="user_id" class="mt-1 w-full px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                <option value="">همهٔ کاربران</option>
                @foreach($users as $u)
                    <option value="{{ $u['id'] }}" @selected($filters['user_id'] === (string) $u['id'])>{{ $u['name'] }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-xs text-gray-500 lg:col-span-2">نوع رکورد
            <select name="entity" class="mt-1 w-full px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                <option value="">همهٔ رکوردها</option>
                @foreach($entities as $class => $label)
                    <option value="{{ $class }}" @selected($filters['entity'] === $class)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-xs text-gray-500">IP
            <input type="text" name="ip" value="{{ $filters['ip'] }}" dir="ltr" placeholder="مثلاً 62.220"
                   class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
        </label>
        <label class="block text-xs text-gray-500">جستجو
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="عنوان، مقدار تغییر، مسیر…"
                   class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
        </label>
        <div class="md:col-span-3 lg:col-span-4 flex items-center gap-2 pt-1">
            <button class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">اعمال فیلتر</button>
            <a href="{{ route('admin.activity-log.index') }}" class="px-3 py-2 text-gray-500 text-sm">پاک‌کردن</a>
            <span class="text-xs text-gray-400">{{ number_format($scanned) }} رکورد منطبق</span>
        </div>
    </form>

    @if($truncated)
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300 rounded-lg p-3 text-sm">
            ⚠️ تعداد نتایج از سقف اسکن بیشتر است و فقط بخشی نمایش داده می‌شود. برای دیدن دقیق، بازهٔ تاریخ را کوتاه‌تر یا فیلتر را دقیق‌تر کنید.
        </div>
    @endif

    {{-- جدول رویدادها --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50 text-[11px] text-gray-500">
                    <tr>
                        <th class="p-3 text-right">زمان</th>
                        <th class="p-3 text-right">کاربر</th>
                        <th class="p-3">اقدام</th>
                        <th class="p-3 text-right">رکورد</th>
                        <th class="p-3 text-right">تغییرات</th>
                        <th class="p-3">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($items as $row)
                        @php($color = ActivityRegistry::ACTION_COLORS[$row['action']] ?? 'gray')
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40 align-top">
                            <td class="p-3 whitespace-nowrap">
                                <div class="text-xs text-gray-700 dark:text-gray-200">
                                    {{ $row['at'] ? Jalalian::fromDateTime($row['at']->toDateTime())->format('Y/m/d') : '—' }}
                                </div>
                                <div class="text-[11px] text-gray-400" dir="ltr">{{ $row['at']?->format('H:i:s') }}</div>
                            </td>
                            <td class="p-3">
                                <div class="text-xs font-medium text-gray-800 dark:text-gray-100">{{ $row['user_name'] }}</div>
                                @if($row['path'])
                                    <div class="text-[10px] text-gray-400 truncate max-w-[180px]" dir="ltr">{{ $row['method'] }} /{{ $row['path'] }}</div>
                                @endif
                            </td>
                            <td class="p-3 text-center whitespace-nowrap">
                                <span @class([
                                    'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold',
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $color === 'emerald',
                                    'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' => $color === 'blue',
                                    'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => $color === 'red',
                                    'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => $color === 'amber',
                                    'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => $color === 'gray',
                                ])>{{ $actions[$row['action']] ?? $row['action'] }}</span>
                            </td>
                            <td class="p-3">
                                <div class="text-xs text-gray-800 dark:text-gray-100">
                                    {{ $row['entity_label'] }}
                                    @if($row['entity_id'])<span class="text-gray-400" dir="ltr">#{{ $row['entity_id'] }}</span>@endif
                                </div>
                                @if($row['entity_title'] || $row['description'])
                                    <div class="text-[11px] text-gray-500 max-w-[260px] truncate">{{ $row['entity_title'] ?: $row['description'] }}</div>
                                @endif
                            </td>
                            <td class="p-3">
                                @if($row['changes'])
                                    <div x-data="{ open: false }">
                                        <button @click="open = !open" class="text-[11px] text-brand-600 hover:underline">
                                            {{ count($row['changes']) }} فیلد تغییر کرد
                                            <span x-text="open ? '▲' : '▼'"></span>
                                        </button>
                                        <div x-show="open" x-collapse class="mt-1 space-y-1">
                                            @foreach($row['changes'] as $field => $pair)
                                                <div class="text-[11px] bg-gray-50 dark:bg-gray-700/50 rounded px-2 py-1">
                                                    <span class="text-gray-500" dir="ltr">{{ $field }}</span>:
                                                    <span class="text-red-600 line-through">{{ \Illuminate\Support\Str::limit((string) ($pair['old'] ?? '—'), 60) }}</span>
                                                    <span class="text-gray-400">→</span>
                                                    <span class="text-emerald-700 dark:text-emerald-400">{{ \Illuminate\Support\Str::limit((string) ($pair['new'] ?? '—'), 60) }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <span class="text-[11px] text-gray-300 dark:text-gray-600">—</span>
                                @endif
                            </td>
                            <td class="p-3 text-center text-[11px] text-gray-400" dir="ltr">{{ $row['ip'] ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-12 text-gray-400">
                                <div class="text-3xl mb-2">🗂️</div>
                                @if($availableDates)
                                    <p class="text-sm">با این فیلترها رویدادی پیدا نشد.</p>
                                @else
                                    <p class="text-sm">هنوز فعالیتی ثبت نشده است.</p>
                                    <p class="text-xs mt-1">با اولین ایجاد/ویرایش/حذف در پنل، رویدادها اینجا نمایش داده می‌شوند.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $items->links() }}</div>
</div>
@endsection
