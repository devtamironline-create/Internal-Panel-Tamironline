@extends('layouts.admin')

@section('page-title', 'گزارش‌های انجمن')

@section('main')
<div class="min-h-screen bg-gray-50 dark:bg-gray-900">

    <div class="sticky top-0 z-30 bg-white/95 dark:bg-gray-800/95 backdrop-blur border-b border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="p-4 flex items-center justify-between gap-4">
            <div>
                <div class="text-xs text-gray-500 mb-1">
                    <a href="{{ route('site.admin.forum.dashboard') }}" class="hover:text-blue-600">داشبورد انجمن</a> ›
                </div>
                <h1 class="text-lg font-bold flex items-center gap-2">
                    <span>🚩</span>
                    <span>گزارش‌های کاربران</span>
                </h1>
            </div>
        </div>
    </div>

    <div class="p-4 lg:p-6">
        @if(session('success'))
            <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ session('success') }}</div>
        @endif

        {{-- Status tabs --}}
        <div class="flex items-center gap-2 mb-4 flex-wrap">
            @foreach([
                'pending' => ['در انتظار', 'amber'],
                'reviewed' => ['بررسی‌شده', 'blue'],
                'actioned' => ['اقدام‌شده', 'emerald'],
                'dismissed' => ['رد‌شده', 'gray'],
            ] as $st => [$lbl, $color])
                @php $current = (request('status') ?? 'pending') === $st; @endphp
                <a href="?status={{ $st }}"
                   class="px-3 py-2 text-sm rounded-lg {{ $current ? "bg-{$color}-600 text-white" : "bg-{$color}-50 dark:bg-{$color}-900/20 text-{$color}-700 dark:text-{$color}-300" }}">
                    {{ $lbl }} <span class="opacity-75 font-mono text-xs">({{ $counts[$st] ?? 0 }})</span>
                </a>
            @endforeach
        </div>

        @if($reports->isEmpty())
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-10 text-center text-gray-400">
                ✓ گزارشی در این وضعیت وجود ندارد.
            </div>
        @else
            <div class="space-y-3">
                @foreach($reports as $report)
                    @php
                        $target = null;
                        $targetUrl = null;
                        $targetTitle = null;
                        if ($report->reportable_type === 'question' && isset($questions[$report->reportable_id])) {
                            $target = $questions[$report->reportable_id];
                            $targetTitle = $target->title;
                            $targetUrl = route('site.admin.forum.questions.show', $target->id);
                        } elseif ($report->reportable_type === 'answer' && isset($answers[$report->reportable_id])) {
                            $target = $answers[$report->reportable_id];
                            $targetTitle = '↳ پاسخ به: '.($target->question?->title ?? 'سوال حذف‌شده');
                            $targetUrl = $target->question ? route('site.admin.forum.questions.show', $target->question->id) : null;
                        } elseif ($report->reportable_type === 'comment' && isset($comments[$report->reportable_id])) {
                            $target = $comments[$report->reportable_id];
                            $targetTitle = '💬 کامنت: '.\Illuminate\Support\Str::limit(strip_tags((string) $target->content), 60);
                            $targetUrl = \Illuminate\Support\Facades\Route::has('site.admin.comments.index') ? route('site.admin.comments.index') : null;
                        }
                        $reasonLabel = \Modules\Site\Models\Forum\Report::REASONS[$report->reason] ?? $report->reason;
                    @endphp

                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1 flex-wrap">
                                    <span class="text-[10px] px-2 py-0.5 rounded bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300 font-bold">{{ $reasonLabel }}</span>
                                    <span class="text-[10px] px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 font-mono">{{ $report->reportable_type }}#{{ $report->reportable_id }}</span>
                                    <span class="text-[10px] text-gray-400 ltr font-mono" dir="ltr">{{ \Morilog\Jalali\Jalalian::fromCarbon($report->created_at)->ago() }}</span>
                                </div>

                                @if($targetUrl && $targetTitle)
                                    <a href="{{ $targetUrl }}" class="text-sm font-semibold text-blue-700 hover:underline block">{{ $targetTitle }}</a>
                                @else
                                    <p class="text-sm text-gray-400">[target حذف‌شده — {{ $report->reportable_type }}#{{ $report->reportable_id }}]</p>
                                @endif

                                @if($report->notes)
                                    <div class="mt-2 p-2 rounded bg-rose-50 dark:bg-rose-900/10 border-r-2 border-rose-300 text-xs text-gray-700 dark:text-gray-300">
                                        «{{ $report->notes }}»
                                    </div>
                                @endif

                                <div class="mt-2 text-[11px] text-gray-500 flex items-center gap-3 flex-wrap">
                                    @if($report->reporter_name)<span>👤 {{ $report->reporter_name }}</span>@endif
                                    @if($report->reporter_email)<span class="ltr font-mono" dir="ltr">{{ $report->reporter_email }}</span>@endif
                                    @if($report->reporter_ip)<span class="ltr font-mono" dir="ltr">{{ $report->reporter_ip }}</span>@endif
                                </div>

                                @if($report->admin_notes)
                                    <div class="mt-2 p-2 rounded bg-blue-50 dark:bg-blue-900/10 text-xs">
                                        <span class="font-bold">یادداشت ادمین:</span> {{ $report->admin_notes }}
                                        @if($report->reviewer)<span class="text-gray-500"> — {{ $report->reviewer->full_name }}</span>@endif
                                    </div>
                                @endif
                            </div>
                        </div>

                        @canany(['moderate-forum-questions', 'manage-forum-questions', 'manage-site'])
                        <form method="POST" action="{{ route('site.admin.forum.reports.update', $report->id) }}" class="flex items-center gap-2 flex-wrap pt-3 border-t border-gray-100 dark:border-gray-700">
                            @csrf @method('PUT')
                            <input type="text" name="admin_notes" placeholder="یادداشت اختیاری ادمین..." maxlength="1000"
                                   value="{{ $report->admin_notes }}"
                                   class="flex-1 min-w-[200px] px-2 py-1.5 border border-gray-200 dark:bg-gray-700 dark:border-gray-600 rounded text-xs">

                            @foreach([
                                'actioned' => ['اقدام شد', 'emerald'],
                                'dismissed' => ['رد', 'gray'],
                                'reviewed' => ['بررسی‌شد', 'blue'],
                            ] as $st => [$lbl, $color])
                                @continue($report->status === $st)
                                <button type="submit" name="status" value="{{ $st }}"
                                        class="px-3 py-1.5 rounded text-xs bg-{{ $color }}-100 text-{{ $color }}-700 hover:bg-{{ $color }}-200">
                                    {{ $lbl }}
                                </button>
                            @endforeach
                        </form>
                        @endcanany
                    </div>
                @endforeach
            </div>

            <div class="mt-4">{{ $reports->links() }}</div>
        @endif
    </div>
</div>
@endsection
