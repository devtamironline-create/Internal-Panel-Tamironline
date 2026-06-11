{{-- فرم مشترک ثبت/ویرایش هزینه.
     انتظار: $categories, $accounts, $paymentMethods, $allTags
     و در حالت ویرایش: $expense --}}
@php
    $isEdit = isset($expense);
    $paidDate = old('paid_date', $isEdit ? \Morilog\Jalali\Jalalian::fromDateTime($expense->paid_at)->format('Y/m/d') : \Morilog\Jalali\Jalalian::now()->format('Y/m/d'));
    $paidTime = old('paid_time', $isEdit ? $expense->paid_at->format('H:i') : now()->format('H:i'));
    $selectedParent = old('category_id', $isEdit ? $expense->category?->parent_id : '');
    $selectedChild  = old('child_id', $isEdit ? $expense->category_id : '');
    $tagsValue = old('tags', $isEdit ? $expense->tags->pluck('name')->implode('، ') : '');
@endphp

{{-- اطلاعات زمانی و مالی --}}
<div class="mb-6">
    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">زمان و مبلغ</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">تاریخ (شمسی) *</label>
            <input type="text" name="paid_date" value="{{ $paidDate }}" dir="ltr" required readonly
                   class="jalali-datepicker w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg cursor-pointer bg-white">
            @error('paid_date')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">ساعت ثبت/پرداخت *</label>
            <input type="time" name="paid_time" value="{{ $paidTime }}" required dir="ltr"
                   class="keep-latin w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
            @error('paid_time')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">مبلغ (تومان) *</label>
            <input type="text" name="amount" id="expAmount" dir="ltr" required
                   value="{{ old('amount', $isEdit ? number_format($expense->amount) : '') }}"
                   placeholder="مثلاً 5,000,000"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg font-bold">
            @error('amount')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>
    </div>
</div>

{{-- دسته‌بندی --}}
<div class="mb-6">
    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">دسته‌بندی</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">دسته اصلی *</label>
            <select name="category_id" id="expFormParent" required
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— انتخاب کنید —</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected($selectedParent == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
            @error('category_id')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">زیر دسته *</label>
            <select name="child_id" id="expFormChild" required
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— ابتدا دسته اصلی —</option>
                @foreach($categories as $cat)
                    @foreach($cat->children as $child)
                        <option value="{{ $child->id }}" data-parent="{{ $cat->id }}"
                                @selected($selectedChild == $child->id)>{{ $child->name }}</option>
                    @endforeach
                @endforeach
            </select>
            @error('child_id')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
            <p class="text-xs text-gray-400 mt-1">اگر زیر دسته‌ای ندارید، مدیر باید از «دسته‌بندی هزینه‌ها» بسازد.</p>
        </div>
    </div>
</div>

{{-- اطلاعات هزینه --}}
<div class="mb-6">
    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">اطلاعات هزینه</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شرح هزینه *</label>
            <input type="text" name="description" maxlength="500" required
                   value="{{ old('description', $isEdit ? $expense->description : '') }}"
                   placeholder="مثلاً: شارژ گوگل ادز خرداد"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
            @error('description')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">دریافت‌کننده وجه</label>
            <input type="text" name="payee" maxlength="190"
                   value="{{ old('payee', $isEdit ? $expense->payee : '') }}"
                   placeholder="مثلاً: گوگل، مخابرات، مالک ساختمان"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">پرداخت‌کننده</label>
            <input type="text" name="payer" maxlength="190"
                   value="{{ old('payer', $isEdit ? $expense->payer : '') }}"
                   placeholder="مثلاً: امید اسماعیلی"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
    </div>
</div>

{{-- اطلاعات پرداخت --}}
<div class="mb-6">
    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">اطلاعات پرداخت</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">حساب پرداخت *</label>
            <select name="payment_account_id" required
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— انتخاب کنید —</option>
                @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}" @selected(old('payment_account_id', $isEdit ? $expense->payment_account_id : '') == $acc->id)>
                        {{ $acc->title }} ({{ $acc->type_label }})
                    </option>
                @endforeach
            </select>
            @error('payment_account_id')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
            @if($accounts->isEmpty())
                <p class="text-xs text-amber-600 mt-1">هنوز حسابی تعریف نشده — مدیر باید از «حساب‌های پرداخت» بسازد.</p>
            @endif
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">روش پرداخت</label>
            <select name="payment_method" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— انتخاب نشده —</option>
                @foreach($paymentMethods as $key => $label)
                    <option value="{{ $key }}" @selected(old('payment_method', $isEdit ? $expense->payment_method : '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شماره پیگیری</label>
            <input type="text" name="tracking_number" maxlength="100" dir="ltr"
                   value="{{ old('tracking_number', $isEdit ? $expense->tracking_number : '') }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
    </div>
</div>

{{-- مستندات و تگ --}}
<div class="mb-6">
    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">مستندات و تگ</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">فایل ضمیمه (فیش/فاکتور)</label>
            <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf"
                   class="w-full text-sm text-gray-600 dark:text-gray-300">
            <p class="text-xs text-gray-400 mt-1">اختیاری — jpg/png/webp/pdf تا ۸ مگابایت.</p>
            @if($isEdit && $expense->attachment_path)
                <a href="{{ route('crm.costs.attachment', $expense) }}" target="_blank"
                   class="inline-block mt-1 text-xs text-brand-700 hover:underline">📎 مشاهده ضمیمه فعلی</a>
                <span class="text-[10px] text-gray-400">(آپلود فایل جدید، فایل قبلی را جایگزین می‌کند)</span>
            @endif
            @error('attachment')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">تگ‌ها</label>
            <input type="text" name="tags" value="{{ $tagsValue }}" list="expTagsList"
                   placeholder="با کاما جدا کنید: تبلیغات تابستان، دفتر مرکزی، فوری"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
            <datalist id="expTagsList">
                @foreach($allTags as $tagName)
                    <option value="{{ $tagName }}"></option>
                @endforeach
            </datalist>
            <p class="text-xs text-gray-400 mt-1">تگ برای گزارش‌گیری است و جایگزین دسته‌بندی نیست.</p>
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">یادداشت</label>
            <textarea name="note" rows="2" maxlength="2000"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">{{ old('note', $isEdit ? $expense->note : '') }}</textarea>
        </div>
    </div>
</div>

<script>
(function () {
    // cascade دسته اصلی → زیر دسته
    var parentSel = document.getElementById('expFormParent');
    var childSel  = document.getElementById('expFormChild');
    function filterChildren(clearIfMismatch) {
        var pid = parentSel.value;
        Array.from(childSel.options).forEach(function (opt) {
            if (! opt.value) return;
            opt.hidden = pid === '' || opt.dataset.parent !== pid;
        });
        var cur = childSel.options[childSel.selectedIndex];
        if (clearIfMismatch && cur && cur.value && cur.dataset.parent !== pid) {
            childSel.value = '';
        }
    }
    parentSel.addEventListener('change', function () { filterChildren(true); });
    filterChildren(false);

    // جداکنندهٔ هزارگان زنده روی مبلغ
    var amount = document.getElementById('expAmount');
    amount.addEventListener('input', function () {
        var digits = amount.value.replace(/[۰-۹]/g, function (d) {
            return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d);
        }).replace(/[^\d]/g, '');
        amount.value = digits ? Number(digits).toLocaleString('en-US') : '';
    });
})();
</script>
