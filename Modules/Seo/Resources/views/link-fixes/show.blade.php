@extends('layouts.admin')

@section('page-title', 'پیش‌نمایش اصلاح لینک')

@section('main')
<div class="p-6 max-w-5xl mx-auto" dir="rtl">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">پیش‌نمایش قاعده #{{ $rule->id }}</h1>
        <a href="{{ route('seo.admin.link-fixes.index') }}" class="text-sm text-blue-600 hover:underline">بازگشت به فهرست &rarr;</a>
    </div>

    @if(session('success'))<div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

    {{-- خودِ قاعده --}}
    <div class="rounded-xl border {{ $rule->needs_review ? 'border-amber-300 bg-amber-50/50 dark:bg-amber-900/10' : 'border-gray-200 dark:border-gray-700' }} p-5 mb-5">
        <dl class="text-sm space-y-2">
            <div><dt class="text-xs text-gray-400 mb-0.5">لینکِ خراب</dt><dd dir="ltr" class="font-mono text-xs break-all text-gray-800 dark:text-gray-200">{{ $rule->old_url }}</dd></div>
            @if($rule->action==='replace')
            <div><dt class="text-xs text-gray-400 mb-0.5">مقصدِ درست</dt><dd dir="ltr" class="font-mono text-xs break-all text-emerald-600">{{ $rule->new_url }}</dd></div>
            @else
            <div><dd class="text-orange-600 font-bold text-xs">حذفِ لینک — متن/محتوای داخلِ لینک حفظ می‌شود.</dd></div>
            @endif
            @if($rule->note)<div><dt class="text-xs text-gray-400 mb-0.5">یادداشت</dt><dd class="text-xs text-gray-600 dark:text-gray-300">{{ $rule->note }}</dd></div>@endif
        </dl>

        @if($rule->needs_review)
        <div class="mt-4 border-t border-amber-200 pt-3">
            <p class="text-xs text-amber-700 dark:text-amber-300 font-bold mb-2">⚠ این لینک در صفحاتِ مختلف مقصدهای متفاوتی داشته — مقصدِ نهایی را خودتان تعیین کنید تا قاعده قابلِ اعمال شود:</p>
            <form method="POST" action="{{ route('seo.admin.link-fixes.update', $rule) }}" class="flex flex-wrap gap-2">
                @csrf @method('PUT')
                <select name="action" class="px-3 py-2 border border-amber-300 dark:border-amber-700 dark:bg-gray-700 rounded-lg text-sm">
                    <option value="replace" @selected($rule->action==='replace')>جایگزینی</option>
                    <option value="unlink" @selected($rule->action==='unlink')>حذف لینک (متن بماند)</option>
                </select>
                <input type="text" name="new_url" dir="ltr" value="{{ $rule->new_url }}" placeholder="مقصدِ درست"
                       class="flex-1 min-w-[260px] px-3 py-2 border border-amber-300 dark:border-amber-700 dark:bg-gray-700 rounded-lg text-sm">
                <button class="px-4 py-2 bg-amber-600 text-white rounded-lg text-sm font-bold">ذخیره و خروج از «نیازمند بررسی»</button>
            </form>
        </div>
        @endif
    </div>

    {{-- موارد پیداشده --}}
    <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-bold text-gray-700 dark:text-gray-200">
            مواردِ پیداشده: {{ $rule->matches_count }}
            @if($skippedSelf > 0)<span class="text-[11px] text-amber-600 font-normal">({{ $skippedSelf }} رکورد به‌دلیلِ self-link رد می‌شود)</span>@endif
        </h2>
        @if($rule->status !== 'applied' && ! $rule->needs_review && $rule->matches_count > 0)
        <form method="POST" action="{{ route('seo.admin.link-fixes.apply', $rule) }}"
              onsubmit="return confirm('این قاعده روی {{ $rule->matches_count }} مورد اعمال شود؟ نسخهٔ قبلیِ هر فیلد ذخیره می‌شود و قابلِ بازگردانی است.');">
            @csrf
            <button class="px-5 py-2 bg-emerald-600 text-white rounded-lg text-sm font-bold hover:bg-emerald-700">✓ اعمالِ این قاعده</button>
        </form>
        @elseif($rule->status === 'applied')
        <span class="text-xs text-emerald-600 font-bold">اعمال شده ({{ $rule->applied_count }} مورد) — {{ optional($rule->applied_at)->format('Y/m/d H:i') }}</span>
        @endif
    </div>

    @if(empty($matches))
        <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-600 p-8 text-center text-gray-500 dark:text-gray-400 text-sm">
            موردی در محتوا پیدا نشد{{ $rule->status==='applied' ? ' (قبلاً اصلاح شده)' : '' }}.
        </div>
    @else
        <div class="space-y-2 mb-6">
            @foreach($matches as $m)
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                <div class="flex items-center justify-between gap-2 mb-1 text-xs">
                    <div>
                        <span class="px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 font-bold">{{ $typeLabels[$m['type']] ?? $m['type'] }} #{{ $m['id'] }}</span>
                        <span class="text-gray-700 dark:text-gray-200 font-medium ms-1">{{ $m['label'] }}</span>
                    </div>
                    <span class="text-gray-400 whitespace-nowrap">فیلد: <code>{{ $m['field'] }}</code> × {{ $m['count'] }}</span>
                </div>
                <code dir="ltr" class="block text-[11px] text-gray-500 dark:text-gray-400 break-all leading-5">{{ $m['excerpt'] }}</code>
            </div>
            @endforeach
        </div>
    @endif

    {{-- تغییراتِ این قاعده --}}
    @if($changes->isNotEmpty())
    <h2 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">تغییراتِ اعمال‌شدهٔ این قاعده</h2>
    <div class="space-y-2">
        @foreach($changes as $chg)
        <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-xs">
            <div>
                <span class="font-bold text-gray-700 dark:text-gray-200">{{ $typeLabels[$chg->entity_type] ?? $chg->entity_type }} #{{ $chg->entity_id }}</span>
                <span class="text-gray-500 ms-1">{{ $chg->entity_label }}</span>
                <span class="text-gray-400 ms-1">({{ $chg->field }} × {{ $chg->occurrences }})</span>
                @if($chg->reverted_at)<span class="text-amber-600 font-bold ms-1">بازگردانی‌شده</span>@endif
            </div>
            @if(! $chg->reverted_at)
            <form method="POST" action="{{ route('seo.admin.link-fixes.revert', $chg) }}"
                  onsubmit="return confirm('این فیلد به نسخهٔ قبل از اصلاح برگردد؟ اگر بعد از اصلاح ویرایشِ دستی شده باشد، آن ویرایش هم بازنویسی می‌شود.');">
                @csrf
                <button class="px-3 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded text-[11px] font-bold hover:bg-gray-300">بازگردانی</button>
            </form>
            @endif
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
