@extends('crm::tech-panel.layout')

@section('title', 'پیش‌فاکتور جدید')

@section('body')
<div class="min-h-screen pb-nav" style="background:#eef0f4;" x-data="techProforma()">
    <div class="px-5 pt-6 pb-4 flex items-center justify-between">
        <a href="{{ route('tech.proformas.index') }}" class="w-9 h-9 rounded-full bg-white flex items-center justify-center shadow-sm" aria-label="بازگشت">
            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/></svg>
        </a>
        <h1 class="font-bold text-gray-800">پیش‌فاکتور جدید</h1>
        <span class="w-9"></span>
    </div>

    @if($errors->any())<div class="mx-4 mb-3 bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 text-xs"><ul class="list-disc pr-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    @if(session('error'))<div class="mx-4 mb-3 bg-red-50 border border-red-200 text-red-800 rounded-xl p-3 text-sm">{{ session('error') }}</div>@endif

    <form method="POST" action="{{ route('tech.proformas.store') }}" class="px-4 space-y-4">
        @csrf
        @if($order)<input type="hidden" name="order_id" value="{{ $order->id }}">@endif

        <div class="bg-white rounded-2xl p-4 shadow-sm space-y-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">نام مشتری</label>
                <input type="text" name="customer_name" value="{{ old('customer_name', $order?->customer_name ?: $order?->customer?->display_name) }}" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">موبایل مشتری</label>
                <input type="tel" name="customer_mobile" value="{{ old('customer_mobile', $order?->customer_mobile ?: $order?->customer?->mobile) }}" dir="ltr" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <input type="text" name="device_name" value="{{ old('device_name', $order?->device?->name) }}" placeholder="دستگاه" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm">
                <input type="text" name="brand_name" value="{{ old('brand_name', $order?->brand?->name) }}" placeholder="برند" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm">
            </div>
        </div>

        <div class="bg-white rounded-2xl p-4 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-bold text-gray-800">اقلام</span>
                <button type="button" @click="addRow()" class="text-xs px-3 py-1.5 bg-emerald-50 text-emerald-700 rounded-lg font-bold">+ قلم</button>
            </div>
            <div class="space-y-3">
                <template x-for="(row, i) in rows" :key="i">
                    <div class="border border-gray-100 rounded-xl p-3 space-y-2 relative">
                        <button type="button" @click="rows.splice(i,1)" x-show="rows.length > 1" class="absolute top-2 left-2 text-red-400 text-lg">×</button>
                        <input type="text" :name="`items[${i}][title]`" x-model="row.title" placeholder="عنوان (مثلاً تعویض برد اصلی)" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" :name="`items[${i}][quantity]`" x-model.number="row.quantity" min="1" placeholder="تعداد" dir="ltr" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                            <input type="number" :name="`items[${i}][unit_price]`" x-model.number="row.unit_price" min="0" placeholder="قیمت واحد" dir="ltr" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                        </div>
                        <div class="text-left text-xs text-gray-500" dir="ltr">جمع: <span x-text="fmt((row.quantity||1)*(row.unit_price||0))"></span></div>
                    </div>
                </template>
            </div>
            <div class="mt-3 pt-3 border-t border-gray-100 space-y-2 text-sm">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">تخفیف (تومان)</label>
                    <input type="number" name="discount" x-model.number="discount" min="0" value="{{ old('discount', 0) }}" dir="ltr" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                </div>
                <div class="flex justify-between"><span class="text-gray-500">جمع اقلام</span><span dir="ltr" x-text="fmt(subtotal())"></span></div>
                <div class="flex justify-between font-bold text-base"><span>مبلغ نهایی</span><span dir="ltr" class="text-brand-700" x-text="fmt(Math.max(0, subtotal()-(discount||0)))"></span></div>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-4 shadow-sm">
            <label class="block text-xs text-gray-500 mb-1">توضیحات (اختیاری)</label>
            <textarea name="description" rows="2" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm">{{ old('description') }}</textarea>
        </div>

        <button type="submit" class="w-full py-3.5 bg-brand-600 text-white rounded-2xl font-bold active:scale-95 transition">ثبت پیش‌فاکتور</button>
    </form>
</div>

<script>
function techProforma() {
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
