@extends('layouts.admin')

@section('page-title', 'اصلاح لینک‌های داخلی')

@section('main')
<div class="p-6 max-w-6xl mx-auto" dir="rtl">
    <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">اصلاح لینک‌های داخلی</h1>
        <a href="{{ route('seo.admin.link-fixes.changes') }}" class="text-sm text-blue-600 hover:underline">تاریخچهٔ تغییرات و بازگردانی &larr;</a>
    </div>

    @if(session('success'))<div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4 leading-6">
        قواعدِ «لینکِ خراب → مقصدِ درست» (یا حذفِ لینک با حفظِ متن) روی محتوای <b>مقالات، دستگاه‌ها، برندها و صفحاتِ ترکیبی</b> در سطحِ دیتابیس اعمال می‌شوند.
        هیچ چیزی بدونِ اسکن و تأییدِ شما تغییر نمی‌کند و هر تغییر با نسخهٔ قبلی ذخیره می‌شود (قابلِ بازگردانی).
    </p>

    {{-- آمار --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-5">
        @foreach([['total','کل قواعد',''],['pending','در انتظار','pending'],['matched','دارای مورد','matched'],['applied','اعمال‌شده','applied'],['review','نیازمند بررسی','review']] as [$k,$label,$f])
        <a href="{{ route('seo.admin.link-fixes.index', $f ? ['filter'=>$f] : []) }}"
           class="rounded-xl border p-3 text-center transition {{ $filter===$f ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300' }}">
            <div class="text-xl font-bold text-gray-800 dark:text-white">{{ number_format($stats[$k]) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">{{ $label }}</div>
        </a>
        @endforeach
    </div>

    {{-- اقدام‌های اصلی --}}
    <div class="flex flex-wrap gap-2 mb-6">
        @if($stats['seed_available'])
        <form method="POST" action="{{ route('seo.admin.link-fixes.import') }}">
            @csrf
            <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-bold hover:bg-indigo-700">۱) واردکردنِ قواعدِ فایلِ برنامه‌نویس</button>
        </form>
        @endif
        <form method="POST" action="{{ route('seo.admin.link-fixes.scan-all') }}">
            @csrf
            <button class="px-4 py-2 bg-gray-700 text-white rounded-lg text-sm font-bold hover:bg-gray-800">۲) اسکنِ همه (بدونِ تغییر)</button>
        </form>
        <form method="POST" action="{{ route('seo.admin.link-fixes.apply-all') }}"
              onsubmit="return confirm('همهٔ قواعدِ آماده ({{ $stats['matched'] }} قاعدهٔ دارای مورد) اعمال شوند؟ هر تغییر با نسخهٔ قبلی ذخیره می‌شود و قابلِ بازگردانی است.');">
            @csrf
            <button class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-bold hover:bg-emerald-700" {{ $stats['matched'] === 0 ? 'disabled' : '' }}>۳) اعمالِ همهٔ قواعدِ آماده</button>
        </form>
    </div>

    {{-- افزودنِ قاعدهٔ دستی --}}
    <details class="mb-6 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
        <summary class="text-sm font-bold text-gray-700 dark:text-gray-200 cursor-pointer">➕ افزودنِ قاعدهٔ دستی (پیدا/حذف/جایگزینی)</summary>
        <form method="POST" action="{{ route('seo.admin.link-fixes.store') }}" class="mt-3 grid sm:grid-cols-2 gap-3">
            @csrf
            <input type="text" name="old_url" dir="ltr" required placeholder="URL خراب (مثلاً http://tamironline.com/old-page/)"
                   class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm sm:col-span-2">
            <select name="action" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                <option value="replace">جایگزینی با مقصدِ درست</option>
                <option value="unlink">حذفِ لینک (متن حفظ شود)</option>
            </select>
            <input type="text" name="new_url" dir="ltr" placeholder="مقصدِ درست (برای جایگزینی)"
                   class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            <button class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm font-bold w-fit">افزودن</button>
        </form>
        @error('old_url')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        @error('new_url')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </details>

    {{-- جدول قواعد --}}
    @if($rules->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-600 p-10 text-center text-gray-500 dark:text-gray-400">
            قاعده‌ای نیست. با «واردکردنِ قواعدِ فایلِ برنامه‌نویس» شروع کنید.
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400 text-xs">
                    <tr>
                        <th class="px-3 py-3 text-right font-medium">لینکِ خراب → مقصد</th>
                        <th class="px-3 py-3 text-center font-medium">اقدام</th>
                        <th class="px-3 py-3 text-center font-medium">موارد</th>
                        <th class="px-3 py-3 text-center font-medium">وضعیت</th>
                        <th class="px-3 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($rules as $rule)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $rule->needs_review ? 'bg-amber-50/60 dark:bg-amber-900/10' : '' }}">
                        <td class="px-3 py-3 max-w-md">
                            <div dir="ltr" class="truncate text-gray-800 dark:text-gray-200 text-xs" title="{{ $rule->old_url }}">{{ $rule->old_url }}</div>
                            @if($rule->action === 'replace' && $rule->new_url)
                                <div dir="ltr" class="truncate text-emerald-600 text-xs" title="{{ $rule->new_url }}">↳ {{ $rule->new_url }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-center">
                            <span class="px-2 py-0.5 rounded text-[11px] font-bold {{ $rule->action==='replace' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700' }}">
                                {{ $rule->action==='replace' ? 'جایگزینی' : 'حذف لینک' }}
                            </span>
                        </td>
                        <td class="px-3 py-3 text-center">
                            <span class="inline-flex min-w-[1.8rem] justify-center px-2 py-0.5 rounded-full text-xs font-bold {{ $rule->matches_count>0 ? 'bg-rose-100 text-rose-700' : 'bg-gray-100 text-gray-500' }}">{{ $rule->matches_count }}</span>
                        </td>
                        <td class="px-3 py-3 text-center text-[11px]">
                            @if($rule->needs_review)<span class="text-amber-600 font-bold">نیازمند بررسی</span>
                            @elseif($rule->status==='applied')<span class="text-emerald-600 font-bold">اعمال‌شده ({{ $rule->applied_count }})</span>
                            @else<span class="text-gray-500">در انتظار</span>@endif
                        </td>
                        <td class="px-3 py-3 text-left whitespace-nowrap">
                            <a href="{{ route('seo.admin.link-fixes.show', $rule) }}" class="text-brand-600 hover:underline text-xs">پیش‌نمایش/اعمال &larr;</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $rules->links() }}</div>
    @endif
</div>
@endsection
