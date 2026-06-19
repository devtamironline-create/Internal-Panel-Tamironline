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

            @if($groupKey === 'contact')
            {{-- لیست شماره‌های تماس (repeater) --}}
            <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-700">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold">شماره تماس‌ها</h3>
                    <button type="button" id="phones-add"
                            class="px-3 py-1.5 bg-emerald-600 text-white rounded text-xs">افزودن شماره</button>
                </div>
                @php $phoneRows = old('phones', $phones ?? []); @endphp
                @if(empty($phoneRows)) @php $phoneRows = [['label' => '', 'number' => '']]; @endphp @endif
                <div id="phones-list" class="space-y-2">
                    @foreach($phoneRows as $i => $p)
                    <div class="phones-row grid grid-cols-1 sm:grid-cols-12 gap-2 items-center">
                        <input type="text" name="phones[{{ $i }}][label]" value="{{ $p['label'] ?? '' }}"
                               placeholder="برچسب (مثلاً فروش / پشتیبانی / دفتر)"
                               class="sm:col-span-5 w-full px-3 py-2 border border-gray-200 rounded text-sm">
                        <input type="text" name="phones[{{ $i }}][number]" value="{{ $p['number'] ?? '' }}"
                               placeholder="شماره" dir="ltr"
                               class="sm:col-span-6 w-full px-3 py-2 border border-gray-200 rounded text-sm ltr">
                        <button type="button"
                                class="phones-remove sm:col-span-1 px-3 py-2 bg-red-50 text-red-600 rounded text-xs">حذف</button>
                    </div>
                    @endforeach
                </div>
                <p class="text-xs text-gray-400 mt-2">حداکثر ۱۰ شماره. ردیف‌های بدون شماره ذخیره نمی‌شوند. اولین شماره به‌عنوان شماره‌ی اصلی استفاده می‌شود.</p>
            </div>
            @endif
        </div>
        @endforeach

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded text-sm">ذخیره تنظیمات</button>
        </div>
    </form>
</div>

<script>
(function () {
    var list = document.getElementById('phones-list');
    var addBtn = document.getElementById('phones-add');
    if (!list || !addBtn) return;
    var MAX = 10;

    function reindex() {
        var rows = list.querySelectorAll('.phones-row');
        rows.forEach(function (row, i) {
            var label = row.querySelector('input[name$="[label]"]');
            var number = row.querySelector('input[name$="[number]"]');
            if (label) label.name = 'phones[' + i + '][label]';
            if (number) number.name = 'phones[' + i + '][number]';
        });
    }

    function addRow() {
        if (list.querySelectorAll('.phones-row').length >= MAX) return;
        var first = list.querySelector('.phones-row');
        var row = first ? first.cloneNode(true) : null;
        if (!row) return;
        row.querySelectorAll('input').forEach(function (inp) { inp.value = ''; });
        list.appendChild(row);
        reindex();
    }

    addBtn.addEventListener('click', addRow);

    list.addEventListener('click', function (e) {
        if (!e.target.classList.contains('phones-remove')) return;
        var rows = list.querySelectorAll('.phones-row');
        if (rows.length <= 1) {
            // آخرین ردیف پاک می‌شود ولی باقی می‌ماند تا فرم خالی نشود.
            e.target.closest('.phones-row').querySelectorAll('input').forEach(function (inp) { inp.value = ''; });
            return;
        }
        e.target.closest('.phones-row').remove();
        reindex();
    });
})();
</script>
@endsection
