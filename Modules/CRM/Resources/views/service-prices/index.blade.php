@extends('layouts.admin')

@section('page-title', 'تعرفهٔ خدمات')

@section('main')
<div class="p-6 space-y-5">

    {{-- Header --}}
    <div class="p-5 bg-gradient-to-l from-amber-50 to-orange-50 dark:from-amber-900/30 dark:to-orange-900/30 rounded-xl border border-amber-200 dark:border-amber-800">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">تعرفهٔ خدمات</h1>
        <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">قیمتِ خدماتِ هر دستگاه را اینجا مدیریت کنید. در سایت روی صفحهٔ دستگاه و صفحهٔ <code dir="ltr">/pricing</code> نمایش داده می‌شوند.</p>
        <p class="text-xs text-gray-500 mt-2">ایمپورتِ گروهی از CSV: <code dir="ltr">php artisan crm:import-service-prices &lt;file.csv&gt; --apply</code></p>
    </div>

    @if(session('success'))
        <div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-sm">
            <ul class="list-disc pr-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Device picker --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">انتخاب دستگاه</label>
        <div class="flex flex-wrap gap-2">
            @foreach($devices as $d)
                <a href="{{ route('crm.service-prices.index', ['device' => $d->id]) }}"
                   class="px-3 py-1.5 rounded-lg text-sm border {{ $device && $device->id === $d->id ? 'bg-amber-600 text-white border-amber-600' : 'bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-gray-600 hover:bg-gray-100' }}">
                    {{ $d->name }}
                    <span class="ms-1 px-1.5 py-0.5 rounded-full text-[10px] {{ $device && $device->id === $d->id ? 'bg-white/25' : 'bg-gray-200 text-gray-600' }}">{{ $d->service_prices_count }}</span>
                </a>
            @endforeach
        </div>
    </div>

    @if($device)
        {{-- Add new --}}
        <form method="POST" action="{{ route('crm.service-prices.store', $device) }}"
              class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 grid grid-cols-1 md:grid-cols-12 gap-3 items-end"
              x-data="{ free: false }">
            @csrf
            <div class="md:col-span-5">
                <label class="block text-xs text-gray-500 mb-1">عنوان خدمت <span class="text-rose-500">*</span></label>
                <input type="text" name="title" required maxlength="300" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm" placeholder="مثلاً: باز و بست کامل">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-500 mb-1">قیمت (تومان)</label>
                <input type="number" name="price" min="0" :disabled="free" x-bind:class="free ? 'opacity-50' : ''" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm" dir="ltr">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-500 mb-1">قیمت قبلی</label>
                <input type="number" name="compare_at_price" min="0" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm" dir="ltr">
            </div>
            <div class="md:col-span-2 flex items-center gap-2 pb-2">
                <label class="inline-flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-300">
                    <input type="checkbox" name="is_free" value="1" x-model="free" class="w-4 h-4 rounded"> رایگان
                </label>
            </div>
            <div class="md:col-span-1">
                <button class="w-full px-3 py-2 bg-amber-600 text-white text-sm rounded-lg hover:bg-amber-700">افزودن</button>
            </div>
        </form>

        {{-- List --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-700">
            <div class="px-4 py-2 text-xs font-medium text-gray-500 grid grid-cols-12 gap-2">
                <span class="col-span-5">عنوان</span><span class="col-span-2">قیمت</span><span class="col-span-2">قبلی</span><span class="col-span-1">ترتیب</span><span class="col-span-2">عملیات</span>
            </div>
            @forelse($prices as $p)
                @php($fid = 'sp'.$p->id)
                <div class="px-4 py-2 grid grid-cols-12 gap-2 items-center" x-data="{ free: {{ $p->is_free ? 'true' : 'false' }} }">
                    <input form="{{ $fid }}" type="text" name="title" value="{{ $p->title }}" required class="col-span-5 px-2 py-1.5 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                    <input form="{{ $fid }}" type="number" name="price" value="{{ $p->price }}" min="0" :disabled="free" dir="ltr" class="col-span-2 px-2 py-1.5 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm" :class="free ? 'opacity-40' : ''">
                    <input form="{{ $fid }}" type="number" name="compare_at_price" value="{{ $p->compare_at_price }}" min="0" dir="ltr" class="col-span-2 px-2 py-1.5 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                    <input form="{{ $fid }}" type="number" name="sort_order" value="{{ $p->sort_order }}" min="0" dir="ltr" class="col-span-1 px-2 py-1.5 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                    <div class="col-span-2 flex items-center gap-2">
                        <label class="inline-flex items-center gap-1 text-[11px] text-gray-500"><input form="{{ $fid }}" type="checkbox" name="is_free" value="1" x-model="free" class="w-3.5 h-3.5 rounded">رایگان</label>
                        <button form="{{ $fid }}" class="text-blue-600 text-xs hover:underline">ذخیره</button>
                        <button form="{{ $fid }}d" class="text-rose-600 text-xs hover:underline">حذف</button>
                    </div>
                </div>
                <form id="{{ $fid }}" method="POST" action="{{ route('crm.service-prices.update', $p) }}" class="hidden">@csrf @method('PUT')</form>
                <form id="{{ $fid }}d" method="POST" action="{{ route('crm.service-prices.destroy', $p) }}" class="hidden" onsubmit="return confirm('حذف شود؟');">@csrf @method('DELETE')</form>
            @empty
                <div class="px-4 py-10 text-center text-gray-400 text-sm">برای این دستگاه قیمتی ثبت نشده. از فرمِ بالا اضافه کنید یا با کامندِ ایمپورت وارد کنید.</div>
            @endforelse
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-dashed border-gray-300 dark:border-gray-700 p-10 text-center text-gray-500">
            یک دستگاه را از بالا انتخاب کنید.
        </div>
    @endif
</div>
@endsection
