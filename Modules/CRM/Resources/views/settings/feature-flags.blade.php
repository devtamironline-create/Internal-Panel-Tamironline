@extends('layouts.admin')
@section('page-title', 'تنظیمات عمومی')

@section('main')
<div class="p-6 max-w-2xl">
    <div class="mb-5">
        <h1 class="text-xl font-bold">تنظیمات عمومی</h1>
        <p class="text-sm text-gray-500 mt-1">فلگ‌های مهمِ سامانه (crm_settings). فقط همین موارد از این‌جا قابلِ تغییرند.</p>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('crm.feature-flags.update') }}" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm divide-y divide-gray-100 dark:divide-gray-700">
        @csrf

        @foreach($flags as $flag)
            <div class="p-5">
                @if(($flag['type'] ?? 'text') === 'toggle')
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="hidden" name="{{ $flag['key'] }}" value="0">
                        <input type="checkbox" name="{{ $flag['key'] }}" value="1"
                               @checked((string) $flag['value'] === '1')
                               class="mt-1 w-5 h-5 rounded text-brand-600 border-gray-300 focus:ring-brand-500">
                        <span>
                            <span class="block text-sm font-bold text-gray-900 dark:text-gray-100">{{ $flag['label'] }}</span>
                            <span class="block text-xs text-gray-500 mt-0.5 leading-6">{{ $flag['help'] }}</span>
                            <span class="block text-[10px] text-gray-400 font-mono ltr mt-1" dir="ltr">{{ $flag['key'] }}</span>
                        </span>
                    </label>
                @else
                    <label class="block">
                        <span class="block text-sm font-bold text-gray-900 dark:text-gray-100">{{ $flag['label'] }}</span>
                        <span class="block text-xs text-gray-500 mt-0.5 mb-2 leading-6">{{ $flag['help'] }}</span>
                        <input type="text" name="{{ $flag['key'] }}" value="{{ $flag['value'] }}" dir="ltr"
                               placeholder="{{ $flag['placeholder'] ?? '' }}" maxlength="500"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm font-mono ltr focus:ring-2 focus:ring-brand-500">
                        <span class="block text-[10px] text-gray-400 font-mono ltr mt-1" dir="ltr">{{ $flag['key'] }}</span>
                    </label>
                @endif
            </div>
        @endforeach

        <div class="p-5">
            <button type="submit" class="px-5 py-2.5 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700">ذخیره تنظیمات</button>
        </div>
    </form>
</div>
@endsection
