@extends('layouts.admin')

@section('page-title', 'داشبورد انجمن')

@section('main')
<div class="min-h-screen bg-gray-50 dark:bg-gray-900">

    <div class="sticky top-0 z-30 bg-white/95 dark:bg-gray-800/95 backdrop-blur border-b border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="p-4 flex items-center justify-between gap-4">
            <div>
                <h1 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <span>📊</span>
                    <span>داشبورد انجمن</span>
                </h1>
                <p class="text-xs text-gray-500 mt-0.5">آمار سوالات، رفتار کاربران و فعالیت‌های اخیر ادمین</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('site.admin.forum.questions.index', ['status' => 'pending']) }}"
                   class="px-3 py-2 text-xs bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 hover:bg-amber-200 rounded-lg">
                    در انتظار تأیید ({{ $stats['pending'] }})
                </a>
                <a href="{{ route('site.admin.forum.activity') }}"
                   class="px-3 py-2 text-xs bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-lg">
                    Activity log
                </a>
                <a href="{{ route('site.admin.forum.questions.index') }}"
                   class="px-3 py-2 text-xs bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                    لیست سوالات
                </a>
            </div>
        </div>
    </div>

    <div class="p-4 lg:p-6 space-y-6">

        {{-- Stats grid --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-5 gap-3">
            @foreach([
                ['کل سوالات', $stats['total'], 'bg-blue-50 text-blue-700 border-blue-200'],
                ['در انتظار', $stats['pending'], 'bg-amber-50 text-amber-700 border-amber-200'],
                ['تأیید شده', $stats['approved'], 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                ['رد/spam', ($stats['rejected'] + $stats['spam']), 'bg-rose-50 text-rose-700 border-rose-200'],
                ['بدون پاسخ', $stats['unanswered'], 'bg-violet-50 text-violet-700 border-violet-200'],
                ['امروز', $stats['today'], 'bg-cyan-50 text-cyan-700 border-cyan-200'],
                ['هفته‌ی اخیر', $stats['this_week'], 'bg-sky-50 text-sky-700 border-sky-200'],
                ['کل پاسخ‌ها', $stats['answers_total'], 'bg-indigo-50 text-indigo-700 border-indigo-200'],
                ['پاسخ‌های pending', $stats['answers_pending'], 'bg-orange-50 text-orange-700 border-orange-200'],
            ] as [$label, $value, $color])
                <div class="border rounded-xl p-4 {{ $color }}">
                    <div class="text-xs font-medium opacity-75">{{ $label }}</div>
                    <div class="text-2xl font-bold mt-1">{{ number_format($value) }}</div>
                </div>
            @endforeach
        </div>

        {{-- Daily chart (7-day) --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5">
            <h2 class="text-sm font-bold mb-3">سوالات ۷ روز اخیر</h2>
            <div class="flex items-end justify-between gap-2 h-32">
                @php $max = max(1, $daily->max('count')); @endphp
                @foreach($daily as $d)
                    <div class="flex-1 flex flex-col items-center gap-1">
                        <div class="text-xs text-gray-500 font-mono">{{ $d['count'] }}</div>
                        <div class="w-full bg-blue-500 hover:bg-blue-600 transition-colors rounded-t" style="height: {{ max(2, (int) (($d['count'] / $max) * 100)) }}%"></div>
                        <div class="text-[10px] text-gray-500 font-mono">{{ $d['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Top devices --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5">
                <h2 class="text-sm font-bold mb-3 flex items-center gap-2">🔧 پربازدیدترین دستگاه‌ها</h2>
                @if($topDevices->isEmpty())
                    <p class="text-xs text-gray-400">داده‌ای موجود نیست.</p>
                @else
                    <div class="space-y-2">
                        @php $maxD = $topDevices->max('cnt'); @endphp
                        @foreach($topDevices as $d)
                            <a href="{{ route('site.admin.forum.questions.index') }}?q={{ urlencode($d->name) }}"
                               class="block hover:bg-gray-50 dark:hover:bg-gray-700 px-2 py-1 rounded">
                                <div class="flex items-center justify-between text-sm mb-1">
                                    <span>{{ $d->name }}</span>
                                    <span class="text-xs text-gray-500 font-mono">{{ $d->cnt }}</span>
                                </div>
                                <div class="h-1.5 bg-gray-100 dark:bg-gray-700 rounded overflow-hidden">
                                    <div class="h-full bg-blue-500" style="width: {{ (int) (($d->cnt / max(1, $maxD)) * 100) }}%"></div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Top brands --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5">
                <h2 class="text-sm font-bold mb-3 flex items-center gap-2">🏢 پربازدیدترین برندها</h2>
                @if($topBrands->isEmpty())
                    <p class="text-xs text-gray-400">داده‌ای موجود نیست.</p>
                @else
                    <div class="space-y-2">
                        @php $maxB = $topBrands->max('cnt'); @endphp
                        @foreach($topBrands as $b)
                            <div class="px-2 py-1 rounded hover:bg-gray-50 dark:hover:bg-gray-700">
                                <div class="flex items-center justify-between text-sm mb-1">
                                    <span>{{ $b->name }}</span>
                                    <span class="text-xs text-gray-500 font-mono">{{ $b->cnt }}</span>
                                </div>
                                <div class="h-1.5 bg-gray-100 dark:bg-gray-700 rounded overflow-hidden">
                                    <div class="h-full bg-emerald-500" style="width: {{ (int) (($b->cnt / max(1, $maxB)) * 100) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Recent pending --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-bold flex items-center gap-2">⏳ صف بررسی</h2>
                    @if($stats['pending'] > 10)
                        <a href="{{ route('site.admin.forum.questions.index', ['status' => 'pending']) }}" class="text-xs text-blue-600 hover:underline">مشاهده‌ی همه</a>
                    @endif
                </div>
                @if($recentPending->isEmpty())
                    <p class="text-xs text-emerald-600">✓ همه سوالات بررسی شدند</p>
                @else
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        @foreach($recentPending as $q)
                            <a href="{{ route('site.admin.forum.questions.show', $q->id) }}"
                               class="block border border-gray-100 dark:border-gray-700 rounded-lg p-3 hover:border-amber-300 hover:bg-amber-50 dark:hover:bg-amber-900/10">
                                <div class="text-sm font-medium line-clamp-1">{{ $q->title }}</div>
                                <div class="text-[11px] text-gray-500 mt-1 flex items-center gap-2 flex-wrap">
                                    @if($q->device){{ $q->device->name }}@endif
                                    @if($q->brand)· {{ $q->brand->name }}@endif
                                    · {{ $q->author_name }}
                                    · {{ \Morilog\Jalali\Jalalian::fromCarbon($q->created_at)->ago() }}
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Recent activity --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-bold flex items-center gap-2">📜 فعالیت‌های اخیر ادمین</h2>
                    <a href="{{ route('site.admin.forum.activity') }}" class="text-xs text-blue-600 hover:underline">مشاهده‌ی کامل</a>
                </div>
                @if($recentActivity->isEmpty())
                    <p class="text-xs text-gray-400">هنوز فعالیتی ثبت نشده.</p>
                @else
                    <div class="space-y-2 max-h-96 overflow-y-auto text-sm">
                        @foreach($recentActivity as $log)
                            <div class="flex items-start gap-2 border-b border-gray-50 dark:border-gray-700 pb-2">
                                <span class="text-[10px] px-2 py-0.5 rounded bg-blue-50 text-blue-700 font-mono shrink-0">{{ $log->action }}</span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-xs">
                                        <span class="font-medium">{{ $log->user?->full_name ?? 'سیستم' }}</span>
                                        @if($log->question)
                                            <span class="text-gray-500">→</span>
                                            <a href="{{ route('site.admin.forum.questions.show', $log->question->id) }}"
                                               class="text-blue-600 hover:underline">{{ \Illuminate\Support\Str::limit($log->question->title, 60) }}</a>
                                        @endif
                                    </div>
                                    <div class="text-[10px] text-gray-400 mt-0.5">{{ \Morilog\Jalali\Jalalian::fromCarbon($log->created_at)->ago() }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
