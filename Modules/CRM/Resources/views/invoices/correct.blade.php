@extends('layouts.admin')

@section('page-title', 'اصلاح مبلغ فاکتور')

@section('main')
<div class="p-6 max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">
            اصلاح مبلغ فاکتور <span dir="ltr">{{ $invoice->invoice_code }}</span>
        </h1>
        <a href="{{ route('crm.invoices.show', $invoice) }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm">بازگشت</a>
    </div>

    @if(session('error'))
        <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm space-y-1">
            @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
        </div>
    @endif

    {{-- توضیح فرآیند — تا ادمین دقیقاً بداند چه اتفاقی می‌افتد --}}
    <div class="p-4 bg-amber-50 border border-amber-200 text-amber-900 rounded-lg text-sm leading-6">
        <div class="font-bold mb-1">با ثبت اصلاح، این اتفاق‌ها به‌صورت خودکار و یک‌جا می‌افتد:</div>
        <ul class="list-disc pr-5 space-y-1">
            <li>این فاکتور باطل (بایگانی) می‌شود — مشتری دیگر آن را نمی‌بیند و لینک قبلی‌اش به فاکتور جدید هدایت می‌شود.</li>
            <li>سهم شرکتِ کسرشده از کیف‌پول تکنسین با تراکنش معکوس برمی‌گردد — <b>تعدیل دستی نزنید</b>.</li>
            <li>فاکتور جدید با مبلغ جدید و محاسبه‌گر استاندارد (درصد لحظهٔ تکمیل سفارش) صادر و سهم شرکتِ جدید از کیف‌پول کسر می‌شود.</li>
            <li>مبلغ سفارش هم‌زمان به‌روز می‌شود و دلیل اصلاح در تاریخچهٔ سفارش ثبت می‌شود (برای تکنسین/مشتری نمایش داده نمی‌شود).</li>
            <li>پیامکی خودکار ارسال نمی‌شود؛ در صورت نیاز از صفحهٔ فاکتور جدید «ارسال پیامک» بزنید.</li>
        </ul>
    </div>

    {{-- قبل / بعد --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-5">
        <h2 class="font-bold text-gray-900 dark:text-gray-100 mb-4">وضعیت فعلی فاکتور</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div>
                <div class="text-gray-500 dark:text-gray-400">مبلغ کل</div>
                <div class="font-bold text-gray-900 dark:text-gray-100">{{ number_format((int) $invoice->total_amount) }} تومان</div>
            </div>
            <div>
                <div class="text-gray-500 dark:text-gray-400">سهم تکنسین</div>
                <div class="font-bold text-gray-900 dark:text-gray-100">{{ number_format((int) $invoice->tech_share) }} تومان</div>
            </div>
            <div>
                <div class="text-gray-500 dark:text-gray-400">سهم شرکت</div>
                <div class="font-bold text-gray-900 dark:text-gray-100">{{ number_format((int) $invoice->company_share) }} تومان</div>
            </div>
            <div>
                <div class="text-gray-500 dark:text-gray-400">درصد شرکت</div>
                <div class="font-bold text-gray-900 dark:text-gray-100">{{ (int) $invoice->commission_percent }}٪</div>
            </div>
        </div>
        <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            سفارش: <span dir="ltr">{{ $invoice->order?->order_code }}</span>
            — تکنسین: {{ $invoice->technician?->firstname_tech ?: trim(($invoice->technician?->first_name ?? '').' '.($invoice->technician?->last_name ?? '')) ?: '—' }}
            — مشتری: {{ $invoice->customer?->display_name ?? '—' }}
        </div>
    </div>

    <form method="POST" action="{{ route('crm.invoices.correct.store', $invoice) }}"
          class="bg-white dark:bg-gray-800 rounded-xl shadow p-5 space-y-5"
          onsubmit="return confirm('فاکتور فعلی باطل و فاکتور جدید با مبلغ واردشده صادر می‌شود. ادامه می‌دهید؟');">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">مبلغ جدید فاکتور (تومان) <span class="text-red-500">*</span></label>
            <input type="number" name="total_amount" id="correct-total" min="0" step="1" required
                   value="{{ old('total_amount') }}"
                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-left"
                   dir="ltr" placeholder="{{ (int) $invoice->total_amount }}">
        </div>

        {{-- پیش‌نمایش زندهٔ سهم‌ها — همان فرمول سرور: company = floor(total×percent/100) --}}
        <div id="share-preview" class="hidden p-4 bg-sky-50 border border-sky-200 rounded-lg text-sm">
            <div class="font-bold text-sky-900 mb-2">پیش‌نمایش فاکتور جدید:</div>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <div class="text-sky-700">مبلغ کل</div>
                    <div class="font-bold text-sky-900" id="pv-total">—</div>
                </div>
                <div>
                    <div class="text-sky-700">سهم تکنسین</div>
                    <div class="font-bold text-sky-900" id="pv-tech">—</div>
                </div>
                <div>
                    <div class="text-sky-700">سهم شرکت ({{ $preview['transit'] ? '۰' : $preview['percent'] }}٪)</div>
                    <div class="font-bold text-sky-900" id="pv-company">—</div>
                </div>
            </div>
            <div class="mt-2 text-xs text-sky-700">محاسبهٔ نهایی هنگام ثبت با محاسبه‌گر استاندارد سرور انجام می‌شود.</div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">دلیل اصلاح <span class="text-red-500">*</span></label>
            <textarea name="reason" rows="3" required minlength="5" maxlength="1000"
                      class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                      placeholder="مثال: مبلغ اشتباه وارد شده بود — قطعه ۲٬۵۰۰٬۰۰۰ تومان محاسبه نشده بود.">{{ old('reason') }}</textarea>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">این دلیل در تاریخچهٔ سفارش ثبت می‌شود و فقط برای ادمین قابل مشاهده است.</p>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="px-5 py-2.5 bg-orange-600 hover:bg-orange-700 text-white rounded-lg text-sm font-medium">
                ثبت اصلاح و صدور فاکتور جدید
            </button>
            <a href="{{ route('crm.invoices.show', $invoice) }}" class="px-5 py-2.5 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm">انصراف</a>
        </div>
    </form>
</div>

<script>
(function () {
    var input = document.getElementById('correct-total');
    var box = document.getElementById('share-preview');
    var percent = {{ (int) $preview['percent'] }};
    var transit = {{ $preview['transit'] ? 'true' : 'false' }};
    var hasTech = {{ $preview['has_technician'] ? 'true' : 'false' }};
    var fmt = function (n) { return n.toLocaleString('fa-IR') + ' تومان'; };

    function update() {
        var total = parseInt(input.value, 10);
        if (isNaN(total) || total < 0) { box.classList.add('hidden'); return; }
        var company, tech;
        if (!hasTech) { company = total; tech = 0; }
        else if (transit) { company = 0; tech = total; }
        else { company = Math.floor(total * percent / 100); tech = Math.max(0, total - company); }
        document.getElementById('pv-total').textContent = fmt(total);
        document.getElementById('pv-tech').textContent = fmt(tech);
        document.getElementById('pv-company').textContent = fmt(company);
        box.classList.remove('hidden');
    }

    input.addEventListener('input', update);
    update();
})();
</script>
@endsection
