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

    {{-- کارگاهِ اصلاح --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-5 mb-6 bg-gray-50/50 dark:bg-gray-800/30">
        <h2 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-4">🛠️ کارگاهِ اصلاح</h2>

        @if($target->ignored_at)
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    این لینک «نادیده/حل‌شده» است.
                    @if($target->ignore_reason)<span class="text-gray-400">— {{ $target->ignore_reason }}</span>@endif
                </p>
                <form method="POST" action="{{ route('seo.admin.broken-links.unignore', $target) }}">
                    @csrf
                    <button class="px-3 py-1.5 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-medium hover:bg-gray-300">بازگردانی (Undo)</button>
                </form>
            </div>
        @else
            @if(! empty($suggestion['best']))
                @php $best = $suggestion['best']; @endphp
                <div class="mb-4 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs text-gray-500 dark:text-gray-400">نزدیک‌ترین URLِ معتبر (پیشنهاد)</span>
                        <span class="text-xs font-bold {{ $best['confidence'] >= 70 ? 'text-green-600' : 'text-amber-600' }}">اطمینان: {{ $best['confidence'] }}٪</span>
                    </div>
                    <div class="w-full h-1.5 rounded-full bg-gray-100 dark:bg-gray-700 mb-2 overflow-hidden">
                        <div class="h-full {{ $best['confidence'] >= 70 ? 'bg-green-500' : 'bg-amber-500' }}" style="width: {{ $best['confidence'] }}%"></div>
                    </div>
                    <div dir="ltr" class="font-mono text-sm text-gray-800 dark:text-gray-200 break-all">{{ $best['path'] }}</div>
                    <p class="text-[11px] text-gray-400 mt-1">پیشنهادِ خودکار بر پایهٔ شباهتِ مسیر/slug است — تأیید با شماست.</p>
                </div>
            @else
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">پیشنهادِ خودکارِ مطمئنی یافت نشد؛ مقصد را دستی وارد کنید.</p>
            @endif

            @if($target->is_internal && $target->path)
                <form method="POST" action="{{ route('seo.admin.broken-links.redirect', $target) }}" class="mb-4">
                    @csrf
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">ایجاد ریدایرکت از <code dir="ltr">{{ $target->path }}</code> به:</label>
                    <div class="flex flex-wrap gap-2">
                        <input type="text" name="target" dir="ltr" required
                               value="{{ $suggestion['best']['path'] ?? '' }}"
                               placeholder="/مقصد-معتبر"
                               class="flex-1 min-w-[240px] px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm">
                        <select name="status_code" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm">
                            <option value="301">301 دائمی</option>
                            <option value="302">302 موقت</option>
                            <option value="308">308</option>
                            <option value="410">410 حذف‌شده</option>
                        </select>
                        <button class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-medium hover:bg-brand-700">ایجاد ریدایرکت</button>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">ریدایرکت در جدولِ «ریدایرکت‌ها» ثبت و در فرانت از <code dir="ltr">/v1/seo/redirects</code> اعمال می‌شود. بازگردانی از همان صفحه.</p>
                </form>
            @endif

            <form method="POST" action="{{ route('seo.admin.broken-links.ignore', $target) }}" class="flex flex-wrap gap-2 items-center border-t border-gray-200 dark:border-gray-700 pt-4">
                @csrf
                <input type="text" name="reason" placeholder="دلیلِ نادیده‌گرفتن (اختیاری)" class="flex-1 min-w-[220px] px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm">
                <button class="px-4 py-2 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm font-medium hover:bg-gray-300">نادیده بگیر</button>
            </form>
        @endif
    </div>

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
