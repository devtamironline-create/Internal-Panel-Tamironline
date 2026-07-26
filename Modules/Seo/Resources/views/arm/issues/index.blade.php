@extends('layouts.admin')

@section('page-title', 'بازوی سئو — مشکلات فنی')

@section('main')
@php use Modules\Seo\Support\Arm\ArmEnums; @endphp
<div class="p-6 space-y-4" dir="rtl">
    <div>
        <h1 class="text-xl font-bold text-gray-800 dark:text-white">🛠️ مشکلات فنی سئو</h1>
        <p class="text-sm text-gray-500 mt-1">مواردِ «نیازمند تأیید» از تحلیل اولیه آمده‌اند و تا کرال واقعی تأیید نشوند، عیبِ قطعی محسوب نمی‌شوند.</p>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>@endif

    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-3 flex flex-wrap items-end gap-2 text-sm">
        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="جستجو…" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm w-44">
        <select name="category" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <option value="">همهٔ دسته‌ها</option>
            @foreach(ArmEnums::ISSUE_CATEGORIES as $k => $v)<option value="{{ $k }}" @selected($filters['category'] === $k)>{{ $v }}</option>@endforeach
        </select>
        <select name="severity" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <option value="">همهٔ شدت‌ها</option>
            @foreach(ArmEnums::ISSUE_SEVERITIES as $k => $v)<option value="{{ $k }}" @selected($filters['severity'] === $k)>{{ $v['label'] }}</option>@endforeach
        </select>
        <select name="status" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <option value="">همهٔ وضعیت‌ها</option>
            @foreach(ArmEnums::ISSUE_STATUSES as $k => $v)<option value="{{ $k }}" @selected($filters['status'] === $k)>{{ $v }}</option>@endforeach
        </select>
        <button class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm">اعمال</button>
        <a href="{{ route('seo.arm.issues.index') }}" class="px-3 py-2 text-gray-500 text-sm">پاک‌کردن</a>
    </form>

    <div class="space-y-2">
        @forelse($issues as $issue)
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4" x-data="{ open: false }">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div class="min-w-0 flex-1 cursor-pointer" @click="open = !open">
                        <div class="flex items-center gap-2 flex-wrap">
                            @php($sev = ArmEnums::ISSUE_SEVERITIES[$issue->severity] ?? ['label' => $issue->severity, 'color' => 'gray'])
                            <span @class([
                                'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold',
                                'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => $sev['color'] === 'red',
                                'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => $sev['color'] === 'amber',
                                'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' => $sev['color'] === 'blue',
                                'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => $sev['color'] === 'gray',
                            ])>{{ $sev['label'] }}</span>
                            <span class="text-[11px] text-gray-400">{{ $issue->categoryLabel() }}</span>
                            @include('seo::arm.partials._status', ['status' => $issue->status, 'label' => $issue->statusLabel()])
                        </div>
                        <div class="font-bold text-sm text-gray-800 dark:text-gray-100 mt-1">{{ $issue->title }}</div>
                        <div class="text-[10px] text-gray-400 mt-0.5">
                            @if($issue->page) صفحه: {{ $issue->page->title }} @endif
                            @if($issue->assignee) · مسئول: {{ $issue->assignee->name }} @endif
                            @if($issue->detected_at) · شناسایی: {{ \Morilog\Jalali\Jalalian::fromCarbon($issue->detected_at)->format('Y/m/d') }} @endif
                        </div>
                    </div>
                    @can('seo-arm-manage')
                        <form method="POST" action="{{ route('seo.arm.issues.update', $issue) }}">
                            @csrf @method('PUT')
                            <select name="status" onchange="this.form.submit()" class="text-[11px] px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                                @foreach(ArmEnums::ISSUE_STATUSES as $k => $v)<option value="{{ $k }}" @selected($issue->status === $k)>{{ $v }}</option>@endforeach
                            </select>
                        </form>
                    @endcan
                </div>
                <div x-show="open" x-collapse class="mt-3 border-t border-gray-100 dark:border-gray-700 pt-3 text-xs text-gray-600 dark:text-gray-300 space-y-2">
                    @if($issue->description)<p class="leading-6">{{ $issue->description }}</p>@endif
                    @if($issue->recommended_fix)
                        <p class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-800 rounded-lg p-2 text-emerald-800 dark:text-emerald-300">🔧 راه‌حل پیشنهادی: {{ $issue->recommended_fix }}</p>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                @include('seo::arm.partials._empty', ['message' => 'مشکلی با این فیلترها پیدا نشد.', 'icon' => '🛠️'])
            </div>
        @endforelse
    </div>
    <div>{{ $issues->links() }}</div>
</div>
@endsection
