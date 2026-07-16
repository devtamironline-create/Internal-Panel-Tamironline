@extends('layouts.admin')

@section('page-title', 'پیشنهادهای لینک‌سازی داخلی')

@section('main')
@php use Modules\Seo\Models\LinkSuggestion; @endphp
<div class="p-6 max-w-6xl mx-auto" dir="rtl">
    <div class="flex items-center justify-between flex-wrap gap-2 mb-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">پیشنهادهای لینک‌سازی داخلی</h1>
            <p class="text-sm text-gray-500 mt-1">بررسی، تأیید/رد و اعمالِ پیشنهادها — هر اعمال با نسخهٔ قبلی ذخیره و قابلِ بازگردانی است.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('seo.admin.internal-links.index') }}" class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-sm font-bold text-gray-700 dark:text-gray-200">← داشبورد</a>
            <form method="POST" action="{{ route('seo.admin.internal-links.generate') }}">
                @csrf
                <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-bold hover:bg-indigo-700">تولید پیشنهادها</button>
            </form>
            <form method="POST" action="{{ route('seo.admin.internal-links.apply-approved') }}"
                  onsubmit="return confirm('همهٔ پیشنهادهای تأییدشده ({{ $counts['approved'] }}) روی محتوا اعمال شوند؟');">
                @csrf
                <button class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-bold hover:bg-emerald-700" {{ $counts['approved'] === 0 ? 'disabled' : '' }}>⚡ اعمال تأییدشده‌ها ({{ $counts['approved'] }})</button>
            </form>
        </div>
    </div>

    @if(session('success'))<div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-sm">{{ session('error') }}</div>@endif

    {{-- راهنمای کوتاه — به‌صورت پیش‌فرض باز تا کاربر گیج نشود --}}
    <details open class="mb-4 rounded-2xl border border-blue-200 dark:border-blue-800 bg-blue-50/60 dark:bg-blue-900/10 p-3">
        <summary class="text-xs font-bold text-blue-800 dark:text-blue-300 cursor-pointer">📘 راهنما — هر پیشنهاد یعنی چه و هر دکمه چه می‌کند؟ (برای بستن کلیک کنید)</summary>
        <div class="mt-2 text-[12px] text-gray-700 dark:text-gray-300 leading-7">
            هر کارت یعنی: «در صفحهٔ نام‌برده، متنِ <b>انکر</b> را به <b>مقصد</b> لینک کن» — با دلیلِ پیشنهاد.
            <b>تأیید</b> فقط علامت‌گذاری است و چیزی را تغییر نمی‌دهد؛ <b>اعمال</b> لینک را واقعاً به محتوا اضافه می‌کند:
            فقط <u>اولین</u> تکرارِ انکر در متنِ آزاد لینک می‌شود (هرگز داخلِ لینک/تگِ موجود). اگر انکر در متن پیدا نشود،
            اعمال ناموفق است — با «✏️ انکر» متن را به عبارتی که واقعاً در مقاله هست تغییر دهید.
            هر اعمال، نسخهٔ کاملِ قبلی را ذخیره می‌کند و با «↩ بازگردانی» برمی‌گردد.
            وضعیت‌ها: <b>در انتظار</b> = هنوز تصمیم نگرفته‌اید · <b>تأییدشده</b> = آمادهٔ اعمال (با دکمهٔ ⚡ دسته‌ای هم اعمال می‌شود) ·
            <b>اعمال‌شده</b> = لینک در محتواست · <b>ردشده</b> = نادیده گرفته شده (قابلِ بازگشت).
            <div class="mt-2 pt-2 border-t border-blue-200/60 dark:border-blue-800/60 grid gap-1">
                <div><span class="inline-block px-2 py-0.5 rounded bg-emerald-600 text-white text-[10px] font-bold">اعمال لینک</span> = لینک را <b>همین الان در متنِ مقاله می‌گذارد</b> (اگر «در انتظار» بود، اول خودکار تأیید می‌شود). قابلِ بازگردانی.</div>
                <div><span class="inline-block px-2 py-0.5 rounded bg-blue-600 text-white text-[10px] font-bold">✓ تأیید</span> = فقط علامت می‌زند «این خوب است»؛ <b>محتوا تغییر نمی‌کند</b>. بعداً با ⚡ دسته‌ای اعمال می‌شود.</div>
                <div><span class="inline-block px-2 py-0.5 rounded bg-rose-50 text-rose-700 border border-rose-200 text-[10px] font-bold">✗ رد</span> = این پیشنهاد را نادیده بگیر (بعداً قابلِ بازگشت).</div>
                <div><span class="inline-block px-2 py-0.5 rounded border border-gray-300 text-gray-600 text-[10px] font-bold">✏️ انکر</span> = عبارتی که قرار است لینک شود را ویرایش کن (باید دقیقاً در متنِ مقاله وجود داشته باشد).</div>
                <div class="text-blue-800 dark:text-blue-300">⚡ <b>اعمال تأییدشده‌ها</b> (بالای صفحه) = همهٔ «تأییدشده‌ها» را یک‌جا در محتوا اعمال می‌کند.</div>
            </div>
        </div>
    </details>

    {{-- فیلترها --}}
    <div class="flex flex-wrap items-center gap-2 mb-4 text-xs">
        @foreach(['pending' => 'در انتظار', 'approved' => 'تأییدشده', 'applied' => 'اعمال‌شده', 'rejected' => 'ردشده', 'all' => 'همه'] as $st => $label)
            <a href="{{ route('seo.admin.internal-links.suggestions', array_filter(['status' => $st, 'priority' => $filters['priority'], 'island' => $filters['island']])) }}"
               class="px-3 py-1.5 rounded-full font-bold {{ $filters['status'] === $st ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300' }}">
                {{ $label }}@if(isset($counts[$st])) ({{ $counts[$st] }})@endif
            </a>
        @endforeach
        <form method="GET" class="flex items-center gap-2 mr-auto">
            <input type="hidden" name="status" value="{{ $filters['status'] }}">
            <select name="priority" onchange="this.form.submit()" class="px-2 py-1.5 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-xs">
                <option value="">همه اولویت‌ها</option>
                @foreach(LinkSuggestion::PRIORITY_LABELS as $k => $v)
                    <option value="{{ $k }}" @selected($filters['priority'] === $k)>{{ $v }}</option>
                @endforeach
            </select>
            <select name="island" onchange="this.form.submit()" class="px-2 py-1.5 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-xs">
                <option value="">همه جزیره‌ها (دستگاه‌ها)</option>
                @foreach($islands as $i)
                    <option value="{{ $i }}" @selected($filters['island'] === $i)>{{ $islandNames[$i] ?? $i }}</option>
                @endforeach
            </select>
        </form>
    </div>

    {{-- لیست --}}
    <div class="space-y-3">
        @forelse($suggestions as $s)
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-4" x-data="{ edit: false }">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold
                                {{ $s->priority === 'high' ? 'bg-rose-100 text-rose-700' : ($s->priority === 'medium' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600') }}">
                                اولویت {{ LinkSuggestion::PRIORITY_LABELS[$s->priority] ?? $s->priority }}
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold
                                {{ ['pending' => 'bg-blue-100 text-blue-700', 'approved' => 'bg-emerald-100 text-emerald-700', 'applied' => 'bg-emerald-600 text-white', 'rejected' => 'bg-gray-200 text-gray-600'][$s->status] ?? '' }}">
                                {{ LinkSuggestion::STATUS_LABELS[$s->status] ?? $s->status }}
                            </span>
                            @if($s->island)<span class="text-[10px] text-gray-400">جزیره: {{ $islandNames[$s->island] ?? $s->island }}</span>@endif
                        </div>
                        <div class="text-sm text-gray-800 dark:text-gray-100 leading-7">
                            در <b>{{ $s->page_label }}</b> <span dir="ltr" class="font-mono text-[10px] text-gray-400">{{ $s->page_path }}</span><br>
                            انکر «<b class="text-blue-700 dark:text-blue-400">{{ $s->anchor }}</b>» → <span dir="ltr" class="font-mono text-[11px] text-emerald-700 dark:text-emerald-400">{{ $s->target_path }}</span>
                        </div>
                        <div class="text-[11px] text-gray-500 mt-1">{{ $s->reason }}</div>

                        <form x-show="edit" x-cloak method="POST" action="{{ route('seo.admin.internal-links.anchor', $s) }}" class="mt-2 flex items-center gap-2">
                            @csrf @method('PUT')
                            <input type="text" name="anchor" value="{{ $s->anchor }}" class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-xs w-72">
                            <button class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-bold">ذخیره انکر</button>
                        </form>
                    </div>

                    <div class="flex items-center gap-1.5 flex-shrink-0 flex-wrap">
                        @if(in_array($s->status, ['pending', 'approved'], true))
                            <button type="button" @click="edit = !edit" class="px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-600 text-xs text-gray-600 dark:text-gray-300">✏️ انکر</button>
                            <form method="POST" action="{{ route('seo.admin.internal-links.apply', $s) }}" onsubmit="return confirm('لینک به محتوای صفحه اضافه شود؟');">
                                @csrf
                                <button class="px-3 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700">اعمال لینک</button>
                            </form>
                        @endif
                        @if($s->status === 'pending')
                            <form method="POST" action="{{ route('seo.admin.internal-links.approve', $s) }}">
                                @csrf
                                <button class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-bold hover:bg-blue-700">✓ تأیید</button>
                            </form>
                        @endif
                        @if(in_array($s->status, ['pending', 'approved'], true))
                            <form method="POST" action="{{ route('seo.admin.internal-links.reject', $s) }}">
                                @csrf
                                <button class="px-3 py-1.5 bg-rose-50 text-rose-700 border border-rose-200 rounded-lg text-xs font-bold hover:bg-rose-100">✗ رد</button>
                            </form>
                        @endif
                        @if($s->status === 'rejected')
                            <form method="POST" action="{{ route('seo.admin.internal-links.approve', $s) }}">
                                @csrf
                                <button class="px-3 py-1.5 border border-gray-200 dark:border-gray-600 rounded-lg text-xs text-gray-600 dark:text-gray-300">بازگشت به تأیید</button>
                            </form>
                        @endif
                        @if($s->status === 'applied')
                            <form method="POST" action="{{ route('seo.admin.internal-links.revert', $s) }}" onsubmit="return confirm('محتوای صفحه به نسخهٔ قبل از این لینک برگردد؟');">
                                @csrf
                                <button class="px-3 py-1.5 bg-amber-50 text-amber-700 border border-amber-200 rounded-lg text-xs font-bold hover:bg-amber-100">↩ بازگردانی</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-10 text-center text-sm text-gray-400">
                موردی با این فیلتر نیست. برای شروع «تولید پیشنهادها» را بزنید.
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $suggestions->links() }}</div>
</div>
@endsection
