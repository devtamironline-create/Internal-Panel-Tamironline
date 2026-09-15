{{-- فرم مشترک ثبت/ویرایش برداشت سرمایه.
     انتظار: $accounts, $paymentMethods و در حالت ویرایش: $withdrawal --}}
@php
    $isEdit = isset($withdrawal);
    $paidDate = old('paid_date', $isEdit ? \Morilog\Jalali\Jalalian::fromDateTime($withdrawal->withdrawn_at)->format('Y/m/d') : \Morilog\Jalali\Jalalian::now()->format('Y/m/d'));
    $paidTime = old('paid_time', $isEdit ? $withdrawal->withdrawn_at->format('H:i') : now()->format('H:i'));
@endphp

<div class="mb-4 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 p-3 text-xs text-indigo-800 dark:text-indigo-300 leading-6">
    توجه: برداشت سرمایه <b>هزینهٔ شرکت محسوب نمی‌شود</b>؛ در «هزینهٔ ماه»، «سود» و گزارش‌های هزینه لحاظ نمی‌شود و فقط در گزارشِ برداشت سرمایه دیده می‌شود.
</div>

{{-- زمان و مبلغ --}}
<div class="mb-6">
    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">زمان و مبلغ</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">تاریخ برداشت (شمسی) *</label>
            <input type="text" name="paid_date" value="{{ $paidDate }}" dir="ltr" required readonly
                   class="jalali-datepicker w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg cursor-pointer bg-white">
            @error('paid_date')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">ساعت *</label>
            <input type="time" name="paid_time" value="{{ $paidTime }}" required dir="ltr"
                   class="keep-latin w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
            @error('paid_time')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">مبلغ (تومان) *</label>
            <input type="text" name="amount" id="wdAmount" dir="ltr" required
                   value="{{ old('amount', $isEdit ? number_format($withdrawal->amount) : '') }}"
                   placeholder="مثلاً 200,000,000"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg font-bold">
            @error('amount')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>
    </div>
</div>

{{-- اطلاعات برداشت --}}
<div class="mb-6">
    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">اطلاعات برداشت</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">حساب پرداخت *</label>
            <select name="payment_account_id" required
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— انتخاب کنید —</option>
                @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}" @selected(old('payment_account_id', $isEdit ? $withdrawal->payment_account_id : '') == $acc->id)>
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
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">دریافت‌کننده (مالک/شریک)</label>
            <input type="text" name="payee" maxlength="190"
                   value="{{ old('payee', $isEdit ? $withdrawal->payee : '') }}"
                   placeholder="مثلاً: علی اسماعیلی"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">روش پرداخت</label>
            <select name="payment_method" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— انتخاب نشده —</option>
                @foreach($paymentMethods as $key => $label)
                    <option value="{{ $key }}" @selected(old('payment_method', $isEdit ? $withdrawal->payment_method : '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شرح</label>
            <input type="text" name="description" maxlength="500"
                   value="{{ old('description', $isEdit ? $withdrawal->description : '') }}"
                   placeholder="مثلاً: برداشت سود شهریور"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
            @error('description')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شماره پیگیری</label>
            <input type="text" name="tracking_number" maxlength="100" dir="ltr"
                   value="{{ old('tracking_number', $isEdit ? $withdrawal->tracking_number : '') }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
    </div>
</div>

{{-- مستندات --}}
<div class="mb-6">
    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">مستندات</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">فایل ضمیمه (فیش)</label>
            <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf"
                   class="w-full text-sm text-gray-600 dark:text-gray-300">
            <p class="text-xs text-gray-400 mt-1">اختیاری — jpg/png/webp/pdf تا ۸ مگابایت.</p>
            @if($isEdit && $withdrawal->attachment_path)
                <a href="{{ route('crm.withdrawals.attachment', $withdrawal) }}" target="_blank"
                   class="inline-block mt-1 text-xs text-brand-700 hover:underline">📎 مشاهده ضمیمه فعلی</a>
                <span class="text-[10px] text-gray-400">(آپلود فایل جدید، فایل قبلی را جایگزین می‌کند)</span>
            @endif
            @error('attachment')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">یادداشت</label>
            <textarea name="note" rows="2" maxlength="2000"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">{{ old('note', $isEdit ? $withdrawal->note : '') }}</textarea>
        </div>
    </div>
</div>

<script>
(function () {
    var amount = document.getElementById('wdAmount');
    if (! amount) return;
    amount.addEventListener('input', function () {
        var digits = amount.value.replace(/[۰-۹]/g, function (d) {
            return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d);
        }).replace(/[^\d]/g, '');
        amount.value = digits ? Number(digits).toLocaleString('en-US') : '';
    });
})();
</script>
