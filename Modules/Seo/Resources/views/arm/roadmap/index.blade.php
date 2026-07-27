@extends('layouts.admin')

@section('page-title', 'بازوی سئو — برنامه ۹۰ روزه')

@section('main')
@php use Modules\Seo\Support\Arm\ArmEnums; @endphp
<div class="p-6 space-y-4" dir="rtl">
    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-800 dark:text-white">🗺️ برنامه ۹۰ روزه</h1>
            <p class="text-sm text-gray-500 mt-1">
                روز {{ $progress['day'] }} از ۹۰ · هفته {{ $progress['week'] }} · {{ $progress['phase_info']['title'] }}
                · انجام به‌موقع: <b dir="ltr">{{ $progress['completion_percent'] }}٪</b>
                @if($progress['delayed'] > 0)
                    · <span class="text-red-600 font-bold">{{ $progress['delayed'] }} کار عقب‌افتاده</span>
                @endif
            </p>
        </div>
    </div>

    {{-- نوار فازها --}}
    <div class="grid grid-cols-2 md:grid-cols-6 gap-2">
        @foreach(ArmEnums::PHASES as $num => $phase)
            <a href="{{ route('seo.arm.roadmap.index', array_merge(request()->query(), ['phase' => $num])) }}"
               @class([
                   'rounded-lg p-2 text-center border text-[11px] leading-5',
                   'border-brand-500 bg-brand-50 dark:bg-brand-900/20 text-brand-700 dark:text-brand-300 font-bold' => (int) ($filters['phase'] ?: 0) === $num || ($filters['phase'] === '' && $progress['phase'] === $num),
                   'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400' => ! ((int) ($filters['phase'] ?: 0) === $num || ($filters['phase'] === '' && $progress['phase'] === $num)),
               ])>
                <div class="truncate">{{ $phase['title'] }}</div>
                <div class="text-gray-400">روزهای {{ $phase['days'] }}</div>
            </a>
        @endforeach
    </div>

    {{-- فیلترها --}}
    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-3 flex flex-wrap items-end gap-2 text-sm">
        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="جستجو در عنوان…" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm w-44">
        <select name="status" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <option value="">همهٔ وضعیت‌ها</option>
            @foreach(ArmEnums::ROADMAP_STATUSES as $k => $v)
                <option value="{{ $k }}" @selected($filters['status'] === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <select name="task_type" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <option value="">همهٔ انواع کار</option>
            @foreach(ArmEnums::TASK_TYPES as $k => $v)
                <option value="{{ $k }}" @selected($filters['task_type'] === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <select name="priority" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <option value="">همهٔ اولویت‌ها</option>
            @foreach(ArmEnums::PRIORITIES as $k => $v)
                <option value="{{ $k }}" @selected($filters['priority'] === $k)>{{ $v['label'] }}</option>
            @endforeach
        </select>
        <select name="week" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <option value="">همهٔ هفته‌ها</option>
            @for($w = 1; $w <= 13; $w++)
                <option value="{{ $w }}" @selected($filters['week'] === (string) $w)>هفته {{ $w }}</option>
            @endfor
        </select>
        @if($filters['phase'])<input type="hidden" name="phase" value="{{ $filters['phase'] }}">@endif
        <button class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm">اعمال فیلتر</button>
        <a href="{{ route('seo.arm.roadmap.index') }}" class="px-3 py-2 text-gray-500 text-sm">پاک‌کردن</a>
    </form>

    {{-- لیست کارها --}}
    <div class="space-y-2">
        @forelse($items as $item)
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4" x-data="{ open: false }">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div class="min-w-0 flex-1 cursor-pointer" @click="open = !open">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-300 font-mono">روز {{ $item->day_number }}</span>
                            @include('seo::arm.partials._priority', ['priority' => $item->priority])
                            <span class="text-[10px] text-gray-400">{{ ArmEnums::label(ArmEnums::TASK_TYPES, $item->task_type) }}</span>
                            @if($item->isOverdue())<span class="text-[10px] text-red-600 font-bold">⏰ عقب‌افتاده</span>@endif
                        </div>
                        <div class="font-bold text-sm text-gray-800 dark:text-gray-100 mt-1">{{ $item->title }}</div>
                        <div class="text-[11px] text-gray-400 mt-0.5">
                            @if($item->planned_date) موعد: {{ \Morilog\Jalali\Jalalian::fromCarbon($item->planned_date)->format('Y/m/d') }} @endif
                            @if($item->relatedPage) · صفحهٔ مرتبط: {{ $item->relatedPage->title }} @endif
                            @if($item->owner) · مسئول: {{ $item->owner->full_name }} @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        @include('seo::arm.partials._status', ['status' => $item->status, 'label' => $item->statusLabel()])
                        @can('seo-arm-manage')
                            @if($item->allowedNextStatuses())
                                <form method="POST" action="{{ route('seo.arm.roadmap.status', $item) }}">
                                    @csrf @method('PUT')
                                    <select name="status" onchange="this.form.submit()" class="text-[11px] px-2 py-1 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                                        <option value="">تغییر وضعیت…</option>
                                        @foreach($item->allowedNextStatuses() as $next)
                                            <option value="{{ $next }}">{{ ArmEnums::ROADMAP_STATUSES[$next] }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            @endif
                        @endcan
                    </div>
                </div>
                <div x-show="open" x-collapse class="mt-3 border-t border-gray-100 dark:border-gray-700 pt-3 text-xs text-gray-600 dark:text-gray-300 space-y-2">
                    @if($item->description)<p class="leading-6">{{ $item->description }}</p>@endif
                    @if($item->deliverables)
                        <div><b class="text-gray-700 dark:text-gray-200">خروجی‌ها:</b>
                            <ul class="list-disc pr-5 mt-1 space-y-0.5">@foreach($item->deliverables as $d)<li>{{ $d }}</li>@endforeach</ul>
                        </div>
                    @endif
                    @if($item->acceptance_criteria)
                        <div><b class="text-gray-700 dark:text-gray-200">معیار پذیرش:</b>
                            <ul class="list-disc pr-5 mt-1 space-y-0.5">@foreach($item->acceptance_criteria as $a)<li>{{ $a }}</li>@endforeach</ul>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                @include('seo::arm.partials._empty', ['message' => 'کاری با این فیلترها پیدا نشد.', 'icon' => '🗺️'])
            </div>
        @endforelse
    </div>

    <div>{{ $items->links() }}</div>
</div>
@endsection
