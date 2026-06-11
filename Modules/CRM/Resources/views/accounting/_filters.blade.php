{{-- فیلترهای مشترک لیست/گزارش هزینه‌ها.
     انتظار: $filters, $categories, $accounts, $allTags, $action (URL)
     و $extraHidden (آرایه name=>value اختیاری مثل group در گزارش). --}}
@php
    $qfToday     = \Morilog\Jalali\Jalalian::now()->format('Y/m/d');
    $qfYesterday = \Morilog\Jalali\Jalalian::fromCarbon(now()->subDay())->format('Y/m/d');
    $qfWeekAgo   = \Morilog\Jalali\Jalalian::fromCarbon(now()->subDays(6))->format('Y/m/d');
    $qfMonth     = \Morilog\Jalali\Jalalian::now()->format('Y/m/01');
    $qfBase      = array_merge(request()->except('page', 'from_date', 'to_date'), $extraHidden ?? []);
    $quickRanges = [
        'امروز'      => ['from_date' => $qfToday,     'to_date' => $qfToday],
        'دیروز'      => ['from_date' => $qfYesterday, 'to_date' => $qfYesterday],
        '۷ روز اخیر' => ['from_date' => $qfWeekAgo,   'to_date' => $qfToday],
        'این ماه'    => ['from_date' => $qfMonth,     'to_date' => $qfToday],
    ];
@endphp

{{-- بازه‌های سریع --}}
<div class="flex items-center gap-2 flex-wrap">
    @foreach($quickRanges as $qfLabel => $range)
        @php $qfActive = $filters['from_date'] === $range['from_date'] && $filters['to_date'] === $range['to_date']; @endphp
        <a href="{{ $action }}?{{ http_build_query(array_merge($qfBase, $range)) }}"
           class="px-3 py-1.5 rounded-full text-xs font-bold border transition
                  {{ $qfActive ? 'bg-brand-600 text-white border-brand-600' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-600 hover:border-brand-400' }}">
            {{ $qfLabel }}
        </a>
    @endforeach
    @if($filters['from_date'] !== '' || $filters['to_date'] !== '')
        <a href="{{ $action }}?{{ http_build_query($qfBase) }}" class="px-3 py-1.5 rounded-full text-xs text-gray-400 hover:text-gray-600">× حذف بازه</a>
    @endif
</div>

<form method="GET" action="{{ $action }}" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
    @foreach(($extraHidden ?? []) as $hName => $hValue)
        <input type="hidden" name="{{ $hName }}" value="{{ $hValue }}">
    @endforeach

    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">از تاریخ</label>
            <input type="text" name="from_date" value="{{ $filters['from_date'] }}" dir="ltr" placeholder="مثلاً 1405/03/01" readonly
                   class="jalali-datepicker w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg cursor-pointer bg-white">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">تا تاریخ</label>
            <input type="text" name="to_date" value="{{ $filters['to_date'] }}" dir="ltr" placeholder="مثلاً 1405/03/31" readonly
                   class="jalali-datepicker w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg cursor-pointer bg-white">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">دسته اصلی</label>
            <select name="category_id" id="expFilterParent"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— همه —</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected($filters['category_id'] == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">زیر دسته</label>
            <select name="child_id" id="expFilterChild"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— همه —</option>
                @foreach($categories as $cat)
                    @foreach($cat->children as $child)
                        <option value="{{ $child->id }}" data-parent="{{ $cat->id }}"
                                @selected($filters['child_id'] == $child->id)>{{ $cat->name }} / {{ $child->name }}</option>
                    @endforeach
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">حساب پرداخت</label>
            <select name="account_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— همه —</option>
                @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}" @selected($filters['account_id'] == $acc->id)>{{ $acc->title }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">پرداخت‌کننده</label>
            <input type="text" name="payer" value="{{ $filters['payer'] }}" placeholder="مثلاً امید اسماعیلی"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">تگ</label>
            <select name="tag_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— همه —</option>
                @foreach($allTags as $tag)
                    <option value="{{ $tag->id }}" @selected($filters['tag_id'] == $tag->id)>{{ $tag->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">مبلغ از</label>
                <input type="text" name="amount_min" value="{{ $filters['amount_min'] !== null ? number_format($filters['amount_min']) : '' }}" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">مبلغ تا</label>
                <input type="text" name="amount_max" value="{{ $filters['amount_max'] !== null ? number_format($filters['amount_max']) : '' }}" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
            </div>
        </div>
    </div>

    <div class="flex items-center gap-2 mt-3">
        <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800 text-sm font-medium">اعمال فیلتر</button>
        @if($hasFilter)
            <a href="{{ $action }}@if(!empty($extraHidden['group']))?group={{ $extraHidden['group'] }}@endif"
               class="px-3 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm font-medium">پاک کردن فیلترها</a>
        @endif
    </div>
</form>

<script>
(function () {
    var parentSel = document.getElementById('expFilterParent');
    var childSel  = document.getElementById('expFilterChild');
    if (! parentSel || ! childSel) return;

    function filterChildren() {
        var pid = parentSel.value;
        Array.from(childSel.options).forEach(function (opt) {
            if (! opt.value) return;
            opt.hidden = pid !== '' && opt.dataset.parent !== pid;
        });
        var cur = childSel.options[childSel.selectedIndex];
        if (cur && cur.value && pid !== '' && cur.dataset.parent !== pid) {
            childSel.value = '';
        }
    }
    parentSel.addEventListener('change', filterChildren);
    filterChildren();
})();
</script>
