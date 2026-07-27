@extends('layouts.admin')

@section('page-title', 'بازوی سئو — تقویم محتوا')

@section('main')
@php use Modules\Seo\Support\Arm\ArmEnums; use Morilog\Jalali\Jalalian; @endphp
<div class="p-6 space-y-4" dir="rtl">
    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-800 dark:text-white">📅 تقویم محتوا</h1>
            <p class="text-sm text-gray-500 mt-1">برنامهٔ تولید و به‌روزرسانی محتوا — تاریخ‌ها شمسی نمایش داده می‌شوند.</p>
        </div>
        {{-- سوییچ نما --}}
        <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-700 rounded-lg p-1 text-xs">
            @foreach(['month' => 'ماهانه', 'week' => 'هفتگی', 'list' => 'لیست', 'kanban' => 'کانبان'] as $v => $label)
                <a href="{{ route('seo.arm.calendar.index', array_merge(request()->except('page'), ['view' => $v])) }}"
                   @class([
                       'px-3 py-1.5 rounded-md',
                       'bg-white dark:bg-gray-800 text-brand-600 font-bold shadow-sm' => $view === $v,
                       'text-gray-500 dark:text-gray-300' => $view !== $v,
                   ])>{{ $label }}</a>
            @endforeach
        </div>
    </div>

    {{-- فیلترها --}}
    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-3 flex flex-wrap items-end gap-2 text-sm">
        <input type="hidden" name="view" value="{{ $view }}">
        <input type="hidden" name="jy" value="{{ $jy }}"><input type="hidden" name="jm" value="{{ $jm }}">
        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="عنوان یا کلمهٔ کلیدی…" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm w-44">
        <select name="status" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <option value="">همهٔ وضعیت‌ها</option>
            @foreach(ArmEnums::CONTENT_STATUSES as $k => $v)<option value="{{ $k }}" @selected($filters['status'] === $k)>{{ $v }}</option>@endforeach
        </select>
        <select name="content_type" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <option value="">همهٔ انواع</option>
            @foreach(ArmEnums::CONTENT_TYPES as $k => $v)<option value="{{ $k }}" @selected($filters['content_type'] === $k)>{{ $v }}</option>@endforeach
        </select>
        <select name="cluster" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <option value="">همهٔ خوشه‌ها</option>
            @foreach($clusters as $c)<option value="{{ $c }}" @selected($filters['cluster'] === $c)>{{ $c }}</option>@endforeach
        </select>
        <select name="priority" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <option value="">همهٔ اولویت‌ها</option>
            @foreach(ArmEnums::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected($filters['priority'] === $k)>{{ $v['label'] }}</option>@endforeach
        </select>
        <select name="owner" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <option value="">همهٔ نویسنده‌ها</option>
            @foreach($owners as $o)<option value="{{ $o->id }}" @selected($filters['owner'] === (string) $o->id)>{{ $o->full_name }}</option>@endforeach
        </select>
        <button class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm">اعمال</button>
        <a href="{{ route('seo.arm.calendar.index', ['view' => $view]) }}" class="px-3 py-2 text-gray-500 text-sm">پاک‌کردن</a>
    </form>

    @if($view === 'month')
        {{-- ناوبری ماه جلالی --}}
        @php
            $prev = $jm === 1 ? ['jy' => $jy - 1, 'jm' => 12] : ['jy' => $jy, 'jm' => $jm - 1];
            $next = $jm === 12 ? ['jy' => $jy + 1, 'jm' => 1] : ['jy' => $jy, 'jm' => $jm + 1];
            $monthName = (new Jalalian($jy, $jm, 1))->format('%B Y');
            $firstDow = (new Jalalian($jy, $jm, 1))->toCarbon()->dayOfWeek; // 0=یکشنبه … 6=شنبه
            $offset = ($firstDow + 1) % 7; // ستون اول = شنبه
            $byDay = collect($items)->groupBy(fn ($i) => $i->planned_at ? Jalalian::fromCarbon($i->planned_at->copy())->getDay() : 0);
        @endphp
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center justify-between mb-3">
                <a href="{{ route('seo.arm.calendar.index', array_merge(request()->except('page'), $prev)) }}" class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm text-gray-600 dark:text-gray-200">→ ماه قبل</a>
                <div class="font-bold text-gray-800 dark:text-gray-100">{{ $monthName }}</div>
                <a href="{{ route('seo.arm.calendar.index', array_merge(request()->except('page'), $next)) }}" class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm text-gray-600 dark:text-gray-200">ماه بعد ←</a>
            </div>
            <div class="grid grid-cols-7 gap-1 text-center text-[11px] text-gray-400 mb-1">
                @foreach(['شنبه','یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه'] as $d)<div>{{ $d }}</div>@endforeach
            </div>
            <div class="grid grid-cols-7 gap-1">
                @for($i = 0; $i < $offset; $i++)<div></div>@endfor
                @for($day = 1; $day <= $monthDays; $day++)
                    @php($isToday = Jalalian::now()->getYear() === $jy && Jalalian::now()->getMonth() === $jm && Jalalian::now()->getDay() === $day)
                    <div @class(['min-h-[84px] rounded-lg border p-1.5', 'border-brand-400 ring-1 ring-brand-300' => $isToday, 'border-gray-100 dark:border-gray-700' => ! $isToday])>
                        <div @class(['text-[10px] mb-1', 'text-brand-600 font-black' => $isToday, 'text-gray-400' => ! $isToday])>{{ $day }}</div>
                        @foreach(($byDay[$day] ?? collect())->take(3) as $item)
                            <div class="mb-1" x-data>
                                <button @click="$dispatch('open-content-{{ $item->id }}')"
                                        class="w-full text-right text-[10px] leading-4 px-1.5 py-1 rounded bg-gray-50 dark:bg-gray-700/60 border border-gray-100 dark:border-gray-600 hover:border-brand-400 truncate block"
                                        title="{{ $item->title }}">
                                    {{ $item->typeLabel() }}: {{ $item->title }}
                                </button>
                            </div>
                        @endforeach
                        @if(($byDay[$day] ?? collect())->count() > 3)
                            <div class="text-[9px] text-gray-400">+{{ ($byDay[$day])->count() - 3 }} مورد دیگر</div>
                        @endif
                    </div>
                @endfor
            </div>
        </div>
        @foreach($items as $item)
            @include('seo::arm.calendar._drawer', ['item' => $item])
        @endforeach

    @elseif($view === 'kanban')
        @php($cols = ['brief' => 'بریف', 'writing' => 'در نگارش', 'expert_review' => 'بازبینی تخصصی', 'seo_review' => 'بازبینی سئو', 'ready' => 'آماده', 'scheduled' => 'زمان‌بندی', 'published' => 'منتشرشده'])
        <div class="overflow-x-auto pb-2">
            <div class="flex gap-3 min-w-[1100px]">
                @foreach($cols as $status => $label)
                    @php($colItems = collect($items)->where('status', $status)->values())
                    <div class="w-56 shrink-0 bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-200 dark:border-gray-700 p-2">
                        <div class="flex justify-between items-center px-1 mb-2">
                            <span class="text-xs font-bold text-gray-600 dark:text-gray-300">{{ $label }}</span>
                            <span class="text-[10px] text-gray-400">{{ $colItems->count() }}</span>
                        </div>
                        <div class="space-y-2">
                            @foreach($colItems as $item)
                                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-2" x-data>
                                    <button @click="$dispatch('open-content-{{ $item->id }}')" class="text-right w-full">
                                        <div class="text-[11px] font-bold text-gray-700 dark:text-gray-200 leading-5">{{ $item->title }}</div>
                                    </button>
                                    <div class="flex items-center justify-between mt-1.5">
                                        @include('seo::arm.partials._priority', ['priority' => $item->priority])
                                        @if($item->due_at)
                                            <span @class(['text-[9px]', 'text-red-600 font-bold' => $item->isDelayed(), 'text-gray-400' => ! $item->isDelayed()])>
                                                {{ Jalalian::fromCarbon($item->due_at->copy())->format('m/d') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                @include('seo::arm.calendar._drawer', ['item' => $item])
                            @endforeach
                            @if($colItems->isEmpty())<div class="text-[10px] text-gray-400 text-center py-3">خالی</div>@endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    @else
        {{-- لیست / هفتگی --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 text-[11px] text-gray-500">
                        <tr>
                            <th class="p-3 text-right">عنوان</th>
                            <th class="p-3">نوع</th>
                            <th class="p-3">کلمهٔ اصلی</th>
                            <th class="p-3">تاریخ برنامه</th>
                            <th class="p-3">مهلت</th>
                            <th class="p-3">نویسنده</th>
                            <th class="p-3">اولویت</th>
                            <th class="p-3">وضعیت</th>
                            <th class="p-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($items as $item)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40" x-data>
                                <td class="p-3">
                                    <div class="font-medium text-gray-800 dark:text-gray-100 max-w-[260px] truncate">{{ $item->title }}</div>
                                    @if($item->isDelayed())<span class="text-[10px] text-red-600 font-bold">⏰ عقب‌افتاده</span>@endif
                                </td>
                                <td class="p-3 text-center text-xs text-gray-500">{{ $item->typeLabel() }}</td>
                                <td class="p-3 text-center text-xs text-gray-600 dark:text-gray-300">{{ $item->primary_keyword ?: '—' }}</td>
                                <td class="p-3 text-center text-xs" dir="ltr">{{ $item->planned_at ? Jalalian::fromCarbon($item->planned_at->copy())->format('Y/m/d') : '—' }}</td>
                                <td class="p-3 text-center text-xs" dir="ltr">{{ $item->due_at ? Jalalian::fromCarbon($item->due_at->copy())->format('Y/m/d') : '—' }}</td>
                                <td class="p-3 text-center text-xs text-gray-500">{{ $item->author?->full_name ?: '—' }}</td>
                                <td class="p-3 text-center">@include('seo::arm.partials._priority', ['priority' => $item->priority])</td>
                                <td class="p-3 text-center">@include('seo::arm.partials._status', ['status' => $item->status, 'label' => $item->statusLabel()])</td>
                                <td class="p-3 text-center">
                                    <button @click="$dispatch('open-content-{{ $item->id }}')" class="text-xs text-brand-600 hover:underline">جزئیات</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9">@include('seo::arm.partials._empty', ['message' => 'محتوایی با این فیلترها پیدا نشد.', 'icon' => '📅'])</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @foreach($items as $item)
            @include('seo::arm.calendar._drawer', ['item' => $item])
        @endforeach
        @if($view === 'list' && method_exists($items, 'links'))<div>{{ $items->links() }}</div>@endif
    @endif
</div>
@endsection
