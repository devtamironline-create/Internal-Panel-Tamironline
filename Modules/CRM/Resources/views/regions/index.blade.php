@extends('layouts.admin')

@section('page-title', 'مدیریت مناطق')

@section('main')
<div class="p-4 md:p-6 max-w-5xl mx-auto">
    <div class="mb-4">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">مدیریت مناطق شهری</h1>
        <p class="text-xs text-gray-500 mt-1">مناطق ذیل هر شهر که در اپلیکیشن موبایل به مشتری نمایش داده می‌شوند — اختیاری. با غیرفعال کردن هر منطقه، از لیست انتخاب آدرس در اپ حذف می‌شود (بدون حذف داده).</p>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm mb-4">{{ session('error') }}</div>
    @endif

    {{-- انتخابگر استان/شهر --}}
    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">استان</label>
                <select name="province_id" onchange="this.form.submit()"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                    <option value="">— انتخاب استان —</option>
                    @foreach($provinces as $p)
                        <option value="{{ $p->id }}" @selected($provinceId == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">شهر</label>
                <select name="city_id" onchange="this.form.submit()" @disabled(! $provinceId)
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                    <option value="">{{ $provinceId ? '— انتخاب شهر —' : '— ابتدا استان را انتخاب کنید —' }}</option>
                    @foreach($cities as $c)
                        <option value="{{ $c->id }}" @selected($cityId == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>

    @if($selectedCity)
        {{-- فرم افزودن منطقه --}}
        <form action="{{ route('crm.regions.store') }}" method="POST" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-4">
            @csrf
            <input type="hidden" name="city_id" value="{{ $selectedCity->id }}">
            <div class="text-sm text-gray-600 dark:text-gray-300 mb-3">
                افزودن منطقهٔ جدید به شهر <span class="font-bold text-brand-700">{{ $selectedCity->name }}</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                <div class="md:col-span-8">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">نام منطقه</label>
                    <input type="text" name="name" required maxlength="120" placeholder="مثلاً: ۱، ۲، ۳ یا شمیران، ولنجک، ..."
                           value="{{ old('name') }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">ترتیب</label>
                    <input type="number" name="sort_order" min="0" max="9999" value="{{ old('sort_order', 0) }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="w-full px-3 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">
                        + افزودن
                    </button>
                </div>
            </div>
            @error('name')<p class="text-xs text-rose-600 mt-2">{{ $message }}</p>@enderror
        </form>

        {{-- جدول مناطق --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-600 dark:text-gray-300">
                    <tr>
                        <th class="px-4 py-2 text-start w-16">ترتیب</th>
                        <th class="px-4 py-2 text-start">نام منطقه</th>
                        <th class="px-4 py-2 text-start w-28">وضعیت</th>
                        <th class="px-4 py-2 w-40"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($regions as $r)
                        <tr>
                            <form action="{{ route('crm.regions.update', $r) }}" method="POST" id="upd-{{ $r->id }}">
                                @csrf @method('PUT')
                            </form>
                            <td class="px-4 py-2">
                                <input type="number" name="sort_order" form="upd-{{ $r->id }}" value="{{ $r->sort_order }}" min="0" max="9999"
                                       class="w-16 px-2 py-1 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm">
                            </td>
                            <td class="px-4 py-2">
                                <input type="text" name="name" form="upd-{{ $r->id }}" value="{{ $r->name }}" maxlength="120"
                                       class="w-full px-2 py-1 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm">
                            </td>
                            <td class="px-4 py-2">
                                <form action="{{ route('crm.regions.toggle-active', $r) }}" method="POST" class="inline">
                                    @csrf @method('PUT')
                                    @if($r->is_active)
                                        <button type="submit" title="غیرفعال کردن"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-100 hover:bg-emerald-200 text-emerald-700 text-xs font-medium">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> فعال
                                        </button>
                                    @else
                                        <button type="submit" title="فعال کردن"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-500 text-xs font-medium">
                                            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> غیرفعال
                                        </button>
                                    @endif
                                </form>
                            </td>
                            <td class="px-4 py-2 text-end">
                                <button type="submit" form="upd-{{ $r->id }}"
                                        class="px-3 py-1 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-xs">ذخیره</button>
                                <form action="{{ route('crm.regions.destroy', $r) }}" method="POST" class="inline"
                                      onsubmit="return confirm('حذف «{{ $r->name }}»؟');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="px-3 py-1 rounded bg-rose-100 hover:bg-rose-200 text-rose-700 text-xs">حذف</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-sm text-gray-500">
                                این شهر هنوز منطقه ندارد. می‌توانید با فرم بالا اضافه کنید.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-10 text-center text-sm text-gray-500">
            استان و شهر مورد نظر را انتخاب کنید تا مناطقش را مدیریت کنید.
        </div>
    @endif
</div>
@endsection
