@extends('layouts.admin')

@section('page-title', 'مانیتورینگ سئو')

@section('main')
<div class="p-6 max-w-6xl mx-auto" dir="rtl">
    <div class="flex items-center justify-between flex-wrap gap-2 mb-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">مانیتورینگ و آدیت سئو</h1>
            <p class="text-sm text-gray-500">کرال زمان‌بندی‌شده + آدیت On-page تاریخچه‌دار. کرال دستی: <code class="font-mono ltr">php artisan seo:crawl</code></p>
        </div>
        <form method="POST" action="{{ route('seo.admin.audit.run') }}">
            @csrf
            <button class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">کرال جدید</button>
        </form>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    @if(! $latest)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-10 text-center text-gray-500">
            هنوز کرالی اجرا نشده است. روی «کرال جدید» بزنید یا <code class="font-mono ltr">php artisan seo:crawl</code> را اجرا کنید.
        </div>
    @else
        @include('seo::audit._summary', ['run' => $latest])

        {{-- روند امتیاز سلامت --}}
        @if($trend->count() > 1)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-4">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">روند امتیاز سلامت</p>
            <div class="flex items-end gap-1 h-24">
                @foreach($trend as $t)
                    <div class="flex-1 flex flex-col items-center justify-end">
                        <div class="w-full rounded-t {{ $t['avg_score'] >= 80 ? 'bg-emerald-400' : ($t['avg_score'] >= 50 ? 'bg-amber-400' : 'bg-rose-400') }}"
                             style="height: {{ max(4, $t['avg_score']) }}%" title="run #{{ $t['id'] }}: {{ $t['avg_score'] }}"></div>
                        <span class="text-[10px] text-gray-400 mt-1">{{ $t['date'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- فیلتر شدت + خروجی --}}
        <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
            <div class="flex gap-1 text-xs">
                @foreach(['' => 'همه', 'critical' => 'بحرانی', 'warning' => 'هشدار', 'notice' => 'توجه', 'good' => 'سالم'] as $val => $label)
                    <a href="{{ route('seo.admin.audit.index', array_filter(['severity' => $val])) }}"
                       class="px-3 py-1 rounded-full {{ $severity === $val ? 'bg-brand-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">{{ $label }}</a>
                @endforeach
            </div>
            <div class="flex gap-2 text-xs">
                <a href="{{ route('seo.admin.audit.export', [$latest, 'csv']) }}" class="px-3 py-1 rounded bg-gray-100 dark:bg-gray-700">خروجی CSV</a>
                <a href="{{ route('seo.admin.audit.export', [$latest, 'json']) }}" class="px-3 py-1 rounded bg-gray-100 dark:bg-gray-700">خروجی JSON</a>
            </div>
        </div>

        @include('seo::audit._table', ['audits' => $audits])
    @endif

    {{-- تاریخچهٔ کرال‌ها --}}
    @if($runs->count())
    <div class="mt-6">
        <h2 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">تاریخچهٔ کرال‌ها</h2>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600">
                    <tr><th class="px-3 py-2 text-right">#</th><th class="px-3 py-2 text-right">تاریخ</th><th class="px-3 py-2 text-right">صفحات</th><th class="px-3 py-2 text-right">امتیاز</th><th class="px-3 py-2 text-right">بحرانی/هشدار</th><th class="px-3 py-2 text-right">وضعیت</th></tr>
                </thead>
                <tbody>
                    @foreach($runs as $r)
                    <tr class="border-t border-gray-100 dark:border-gray-700">
                        <td class="px-3 py-2"><a href="{{ route('seo.admin.audit.show', $r) }}" class="text-blue-600 hover:underline">{{ $r->id }}</a></td>
                        <td class="px-3 py-2 text-xs text-gray-500">{{ optional($r->finished_at ?? $r->created_at)->format('Y/m/d H:i') }}</td>
                        <td class="px-3 py-2">{{ $r->total_urls }}</td>
                        <td class="px-3 py-2">{{ $r->avg_score }}</td>
                        <td class="px-3 py-2">{{ $r->critical_count }}/{{ $r->warning_count }}</td>
                        <td class="px-3 py-2 text-xs">{{ $r->status }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
