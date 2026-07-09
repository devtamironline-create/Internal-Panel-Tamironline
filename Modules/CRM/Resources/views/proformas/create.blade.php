@extends('layouts.admin')

@section('page-title', 'پیش‌فاکتور جدید')

@section('main')
<div class="p-6 max-w-3xl mx-auto space-y-6" x-data="proformaForm()">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">پیش‌فاکتور جدید</h1>
        <a href="{{ route('crm.proformas.index') }}" class="px-3 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm">بازگشت</a>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">
            <ul class="list-disc pr-4 space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('crm.proformas.store') }}" class="space-y-5">
        @csrf
        @if($order)<input type="hidden" name="order_id" value="{{ $order->id }}">@endif

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm mb-1">نام مشتری</label>
                <input type="text" name="customer_name" value="{{ old('customer_name', $order?->customer_name ?: $order?->customer?->display_name) }}"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm mb-1">موبایل مشتری</label>
                <input type="text" name="customer_mobile" value="{{ old('customer_mobile', $order?->customer_mobile ?: $order?->customer?->mobile) }}"
                       dir="ltr" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm mb-1">دستگاه</label>
                <input type="text" name="device_name" value="{{ old('device_name', $order?->device?->name) }}"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm mb-1">برند</label>
                <input type="text" name="brand_name" value="{{ old('brand_name', $order?->brand?->name) }}"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
        </div>

        {{-- اقلام --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold">اقلام پیش‌فاکتور</h3>
                <button type="button" @click="addRow()" class="text-xs px-3 py-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg">+ افزودن قلم</button>
            </div>
            <div class="space-y-2">
                <template x-for="(row, i) in rows" :key="i">
                    <div class="grid grid-cols-12 gap-2 items-center">
                        <input type="text" :name="`items[${i}][title]`" x-model="row.title" placeholder="عنوان (مثلاً تعویض برد)"
                               class="col-span-5 px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                        <input type="number" :name="`items[${i}][quantity]`" x-model.number="row.quantity" min="1" placeholder="تعداد"
                               class="col-span-2 px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm" dir="ltr">
                        <input type="number" :name="`items[${i}][unit_price]`" x-model.number="row.unit_price" min="0" placeholder="قیمت واحد"
                               class="col-span-3 px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm" dir="ltr">
                        <div class="col-span-1 text-xs text-gray-500 text-left" dir="ltr" x-text="fmt((row.quantity||1)*(row.unit_price||0))"></div>
                        <button type="button" @click="rows.splice(i,1)" class="col-span-1 text-red-500 text-lg" x-show="rows.length > 1">×</button>
                    </div>
                </template>
            </div>

            <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm mb-1">تخفیف (تومان)</label>
                    <input type="number" name="discount" x-model.number="discount" min="0" value="{{ old('discount', 0) }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm" dir="ltr">
                </div>
                <div class="text-sm space-y-1 sm:text-left">
                    <div class="flex justify-between"><span class="text-gray-500">جمع اقلام:</span><span dir="ltr" x-text="fmt(subtotal())"></span></div>
                    <div class="flex justify-between"><span class="text-gray-500">تخفیف:</span><span dir="ltr" x-text="fmt(discount||0)"></span></div>
                    <div class="flex justify-between font-bold text-base"><span>مبلغ نهایی:</span><span dir="ltr" class="text-brand-600" x-text="fmt(Math.max(0, subtotal()-(discount||0)))"></span></div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-sm mb-1">توضیحات</label>
                <textarea name="description" rows="2" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ old('description') }}</textarea>
            </div>
            <div>
                <label class="block text-sm mb-1">اعتبار تا تاریخ (اختیاری)</label>
                <input type="date" name="valid_until" value="{{ old('valid_until') }}"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm" dir="ltr">
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button type="submit" class="px-6 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">ثبت پیش‌فاکتور</button>
            <a href="{{ route('crm.proformas.index') }}" class="px-4 py-2.5 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm">انصراف</a>
        </div>
    </form>
</div>

<script>
function proformaForm() {
    return {
        rows: [{ title: '', quantity: 1, unit_price: 0 }],
        discount: {{ (int) old('discount', 0) }},
        addRow() { this.rows.push({ title: '', quantity: 1, unit_price: 0 }); },
        subtotal() { return this.rows.reduce((s, r) => s + (r.quantity || 1) * (r.unit_price || 0), 0); },
        fmt(n) { return (n || 0).toLocaleString('en-US'); },
    };
}
</script>
@endsection
