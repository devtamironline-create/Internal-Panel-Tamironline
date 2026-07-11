@extends('layouts.admin')

@section('page-title', 'جزئیات لینک خراب')

@section('main')
<div class="p-6 max-w-5xl mx-auto" dir="rtl">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">جزئیات لینک</h1>
        <a href="{{ route('seo.admin.broken-links.index') }}" class="text-sm text-blue-600 hover:underline">بازگشت به فهرست &rarr;</a>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200 px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-5 mb-5">
        <div class="flex items-start gap-3 mb-3">
            @include('seo::broken-links._status', ['t' => $target])
            <div class="min-w-0">
                <div dir="ltr" class="font-mono text-sm text-gray-800 dark:text-gray-200 break-all">{{ $target->url }}</div>
                @if($target->final_url && $target->final_url !== $target->url)
                    <div dir="ltr" class="font-mono text-xs text-gray-400 break-all mt-1">↳ مقصد نهایی: {{ $target->final_url }}</div>
                @endif
            </div>
        </div>
        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs text-gray-600 dark:text-gray-300">
            <div><dt class="text-gray-400">کد وضعیت</dt><dd class="font-bold" dir="ltr">{{ $target->status_code }}</dd></div>
            <div><dt class="text-gray-400">پرش‌های ریدایرکت</dt><dd class="font-bold" dir="ltr">{{ $target->redirect_count }}</dd></div>
            <div><dt class="text-gray-400">نوع</dt><dd class="font-bold">{{ $target->is_internal ? 'داخلی' : 'خارجی' }}</dd></div>
            <div><dt class="text-gray-400">صفحاتِ ارجاع‌دهنده</dt><dd class="font-bold">{{ $referrers->count() }}</dd></div>
        </dl>
        @if($target->error)
            <p class="mt-3 text-xs text-red-600 dark:text-red-400" dir="ltr">{{ $target->error }}</p>
        @endif
        @if($sourceHint)
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                منبعِ احتمالی: <span class="font-medium">{{ $sourceHint['type'] }}</span>
                @if($sourceHint['slug'])<span dir="ltr">— {{ $sourceHint['slug'] }}</span>@endif
                <span class="text-gray-400">(راهنما؛ اتصال به فایلِ کامپوننت بدونِ دسترسی به کدِ فرانت ممکن نیست)</span>
            </p>
        @endif
    </div>

    {{-- کارگاهِ اصلاح این‌جا اضافه می‌شود --}}

    <h2 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">صفحاتی که به این لینک ارجاع داده‌اند ({{ $referrers->count() }})</h2>
    <div class="space-y-3">
        @foreach($referrers as $r)
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center justify-between gap-3 mb-2">
                    <a href="{{ $r->source_url }}" target="_blank" rel="noopener" dir="ltr" class="text-sm text-brand-600 hover:underline truncate">{{ $r->source_url }}</a>
                    <span class="text-xs text-gray-400 whitespace-nowrap">لینک #{{ $r->position }}</span>
                </div>
                <div class="grid sm:grid-cols-2 gap-2 text-xs text-gray-600 dark:text-gray-300">
                    <div><span class="text-gray-400">متن لنگر:</span> {{ $r->anchor ?: '—' }}</div>
                    <div><span class="text-gray-400">rel:</span> <span dir="ltr">{{ $r->rel ?: '—' }}</span></div>
                    <div class="sm:col-span-2"><span class="text-gray-400">CSS:</span> <code dir="ltr" class="text-[11px]">{{ $r->selector ?: '—' }}</code></div>
                    <div class="sm:col-span-2"><span class="text-gray-400">XPath:</span> <code dir="ltr" class="text-[11px]">{{ $r->xpath ?: '—' }}</code></div>
                    @if($r->context_html)
                        <div class="sm:col-span-2"><span class="text-gray-400">زمینه:</span> <code dir="ltr" class="text-[11px] break-all">{{ $r->context_html }}</code></div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
