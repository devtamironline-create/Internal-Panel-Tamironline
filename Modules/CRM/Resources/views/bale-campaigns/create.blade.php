@extends('layouts.admin')

@section('page-title', 'کمپین جدید بله')

@section('main')
<div class="p-6 max-w-3xl space-y-4">
    <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">💬 کمپینِ جدیدِ بله</h1>

    <form method="POST" action="{{ route('crm.bale-campaigns.store') }}"
          class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 space-y-4"
          x-data="{ audience: '{{ old('audience', 'bale_linked') }}' }">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نامِ کمپین (داخلی) *</label>
            <input type="text" name="title" value="{{ old('title') }}" maxlength="150" required
                   placeholder="مثلاً: تخفیف سرویس کولر — مرداد"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
            @error('title')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">متنِ پیام *</label>
            <textarea name="text" rows="5" maxlength="3500" required
                      placeholder="سلام {customer_name} عزیز! ..."
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg leading-7">{{ old('text') }}</textarea>
            <p class="text-[11px] text-gray-400 mt-1">placeholder: <code dir="ltr">{customer_name}</code> → نامِ مشتری (برای شماره‌های دستی «مشتری گرامی»).</p>
            @error('text')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">مخاطبان *</label>
            <div class="space-y-2">
                @foreach($audiences as $key => $label)
                    <label class="flex items-center gap-2 p-3 border rounded-lg cursor-pointer"
                           :class="audience === '{{ $key }}' ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20' : 'border-gray-200 dark:border-gray-700'">
                        <input type="radio" name="audience" value="{{ $key }}" x-model="audience" class="text-brand-600">
                        <span class="text-sm">{{ $label }}</span>
                        @if(isset($counts[$key]))
                            <span class="text-xs text-gray-400 mr-auto">{{ number_format($counts[$key]) }} نفر</span>
                        @endif
                    </label>
                @endforeach
            </div>
            @error('audience')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div x-show="audience === 'manual'" x-cloak>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شماره‌های دستی</label>
            <textarea name="manual_phones" rows="4" placeholder="0912xxxxxxx — هر خط یا با کاما جدا"
                      dir="ltr"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg font-mono text-sm">{{ old('manual_phones') }}</textarea>
            @error('manual_phones')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-3 text-xs text-amber-800 dark:text-amber-300 leading-6">
            ⚠️ ارسال با «سفیر» (شماره) هزینهٔ پیامِ تجاریِ بله دارد. مخاطبانِ «متصل به بله» از چتِ ربات و رایگان ارسال می‌شوند.
            بعد از ساخت، کمپین <b>پیش‌نویس</b> است — ارسال فقط با دکمهٔ «ارسالِ دستهٔ بعدی» در صفحهٔ کمپین شروع می‌شود.
        </div>

        <div class="flex items-center gap-2">
            <button type="submit" class="px-6 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">ساختِ کمپین</button>
            <a href="{{ route('crm.bale-campaigns.index') }}" class="px-4 py-2.5 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm">انصراف</a>
        </div>
    </form>
</div>
@endsection
