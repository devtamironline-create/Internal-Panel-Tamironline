@extends('layouts.admin')
@section('page-title', 'تنظیمات عمومی سایت')

@section('main')
<div class="p-6 max-w-4xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">تنظیمات عمومی سایت</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">اطلاعات کلی، تماس، شبکه‌های اجتماعی و سئو پیش‌فرض.</p>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    @if($errors->any())
    <div class="mb-4 p-3 rounded bg-red-50 text-red-700 text-sm">
        <ul class="list-disc pr-4">
            @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
        </ul>
    </div>
    @endif

    @php
        $groupLabels = [
            'general' => 'عمومی',
            'contact' => 'اطلاعات تماس',
            'social'  => 'شبکه‌های اجتماعی',
            'seo'     => 'سئو',
        ];
    @endphp

    <form method="POST" action="{{ route('site.admin.settings.update') }}" class="space-y-6">
        @csrf @method('PUT')

        @foreach($groups as $groupKey => $fields)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
            <h2 class="text-lg font-semibold mb-4">{{ $groupLabels[$groupKey] ?? $groupKey }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach($fields as $f)
                <div class="{{ in_array($f['type'], ['string']) && in_array($f['key'], ['contact_address', 'seo_default_description']) ? 'sm:col-span-2' : '' }}">
                    <label class="block text-sm mb-1">{{ $f['label'] }}</label>
                    @if(in_array($f['key'], ['contact_address', 'seo_default_description']))
                        <textarea name="{{ $f['key'] }}" rows="3" class="w-full px-3 py-2 border border-gray-200 rounded text-sm">{{ old($f['key'], $f['value']) }}</textarea>
                    @elseif($f['type'] === 'url')
                        <input type="url" name="{{ $f['key'] }}" value="{{ old($f['key'], $f['value']) }}" dir="ltr"
                               class="w-full px-3 py-2 border border-gray-200 rounded text-sm ltr">
                    @elseif($f['type'] === 'email')
                        <input type="email" name="{{ $f['key'] }}" value="{{ old($f['key'], $f['value']) }}" dir="ltr"
                               class="w-full px-3 py-2 border border-gray-200 rounded text-sm ltr">
                    @else
                        <input type="text" name="{{ $f['key'] }}" value="{{ old($f['key'], $f['value']) }}"
                               class="w-full px-3 py-2 border border-gray-200 rounded text-sm">
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endforeach

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded text-sm">ذخیره تنظیمات</button>
        </div>
    </form>
</div>
@endsection
