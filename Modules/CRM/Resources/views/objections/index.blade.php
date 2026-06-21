@extends('layouts.admin')

@section('page-title', 'ایرادات دستگاه‌ها')

@section('main')
<div class="p-4 md:p-6 max-w-6xl mx-auto" x-data="{ showCoverage: {{ $devicesWithout->count() ? 'true' : 'false' }} }">
    <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">ایرادات دستگاه‌ها</h1>
            <p class="text-xs text-gray-500 mt-1">ایرادات/مشکلاتی که مشتری در فرم ثبت سفارش اپ انتخاب می‌کند. هر ایراد به یک یا چند دستگاه متصل می‌شود.</p>
        </div>
        <a href="{{ route('crm.objections.create') }}" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold shrink-0">+ افزودن ایراد</a>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm mb-4">{{ session('success') }}</div>@endif

    {{-- ─── کارت پوشش: کدام دستگاه‌ها هنوز ایراد ندارند ─────────────── --}}
    @php
        $totalDevices = $coverage->count();
        $covered = $coverage->where('objections_count', '>', 0)->count();
        $withoutCount = $devicesWithout->count();
    @endphp
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm mb-4 overflow-hidden">
        <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-gray-100 dark:border-gray-700">
            <div class="flex items-center gap-2 text-sm">
                <span class="font-bold text-gray-800 dark:text-gray-100">پوشش ایرادات</span>
                <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700">{{ $covered }} دارای ایراد</span>
                @if($withoutCount)
                    <span class="px-2 py-0.5 rounded-full text-xs bg-rose-100 text-rose-700">{{ $withoutCount }} بدون ایراد</span>
                @else
                    <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700">همه‌ی {{ $totalDevices }} دستگاه پوشش دارند ✓</span>
                @endif
            </div>
            <button type="button" @click="showCoverage = !showCoverage" class="text-xs text-blue-600 hover:underline shrink-0">
                <span x-show="!showCoverage">نمایش جزئیات</span>
                <span x-show="showCoverage" x-cloak>بستن</span>
            </button>
        </div>

        <div x-show="showCoverage" x-collapse class="p-4 space-y-4">
            {{-- دستگاه‌های بدون ایراد — برجسته --}}
            @if($withoutCount)
                <div>
                    <p class="text-xs font-bold text-rose-700 dark:text-rose-300 mb-2">دستگاه‌هایی که هنوز ایرادی ندارند ({{ $withoutCount }}):</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($devicesWithout as $d)
                            <a href="{{ route('crm.objections.device', $d->id) }}"
                               class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 dark:bg-rose-900/20 dark:text-rose-300 dark:border-rose-800"
                               title="مدیریت ایرادهای {{ $d->name }}">
                                <span>{{ $d->name }}</span>
                                @unless($d->is_active)<span class="text-[9px] text-gray-400">(غیرفعال)</span>@endunless
                                <span class="text-rose-400">+</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- جدول کامل پوشش — تعداد ایراد هر دستگاه (کلیک = مدیریت ایرادهای دستگاه) --}}
            <div>
                <p class="text-xs font-bold text-gray-600 dark:text-gray-300 mb-2">تعداد ایراد هر دستگاه (کلیک = مدیریت):</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                    @foreach($coverage as $d)
                        <a href="{{ route('crm.objections.device', $d->id) }}"
                           class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg border text-xs transition
                                  {{ $d->objections_count > 0
                                        ? 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 hover:border-brand-400'
                                        : 'bg-rose-50 dark:bg-rose-900/20 border-rose-200 dark:border-rose-800' }}">
                            <span class="font-medium text-gray-800 dark:text-gray-100 truncate">
                                {{ $d->name }}@unless($d->is_active)<span class="text-gray-400"> (غیرفعال)</span>@endunless
                            </span>
                            <span class="shrink-0 px-1.5 py-0.5 rounded-full font-bold
                                         {{ $d->objections_count > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-200 text-rose-700' }}">
                                {{ $d->objections_count }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ─── فیلتر + لیست ایرادات ─────────────────────────────────────── --}}
    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-3 mb-4 flex items-end gap-3 flex-wrap">
        <div class="flex-1 min-w-[200px] max-w-xs">
            <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">فیلتر بر اساس دستگاه</label>
            <select name="device_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                <option value="">— همه‌ی دستگاه‌ها —</option>
                @foreach($devices as $d)
                    <option value="{{ $d->id }}" @selected($deviceId === $d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="px-4 py-2 bg-gray-700 hover:bg-gray-800 text-white rounded-lg text-sm">اعمال</button>
        @if($deviceId)
            <a href="{{ route('crm.objections.index') }}" class="px-3 py-2 text-sm text-gray-600 hover:underline">حذف فیلتر</a>
            <a href="{{ route('crm.objections.device', $deviceId) }}"
               class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold ms-auto">مدیریت ایرادهای این دستگاه</a>
        @endif
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="px-4 py-2 text-start w-16">ترتیب</th>
                    <th class="px-4 py-2 text-start">ایراد</th>
                    <th class="px-4 py-2 text-start">دستگاه‌ها</th>
                    <th class="px-4 py-2 text-center w-24">وضعیت</th>
                    <th class="px-4 py-2 text-center w-40">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $it)
                    <tr class="border-t border-gray-100 dark:border-gray-700">
                        <td class="px-4 py-2 text-gray-500">{{ $it->sort_order }}</td>
                        <td class="px-4 py-2">
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ $it->name }}</div>
                            <div class="text-[10px] text-gray-500 font-mono" dir="ltr">{{ $it->slug }}</div>
                        </td>
                        <td class="px-4 py-2 text-xs text-gray-600 dark:text-gray-300">
                            @if($it->devices->isEmpty())
                                <span class="text-rose-600">— به هیچ دستگاهی متصل نیست —</span>
                            @else
                                {{ $it->devices->pluck('name')->implode('، ') }}
                            @endif
                        </td>
                        <td class="px-4 py-2 text-center">
                            <form action="{{ route('crm.objections.toggle-active', $it) }}" method="POST" class="inline">
                                @csrf @method('PUT')
                                <button class="text-[11px] px-2 py-0.5 rounded-full {{ $it->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600' }}">
                                    {{ $it->is_active ? 'فعال' : 'غیرفعال' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-4 py-2 text-center space-x-2 space-x-reverse">
                            <a href="{{ route('crm.objections.edit', $it) }}" class="text-blue-600 hover:underline text-xs">ویرایش</a>
                            <form action="{{ route('crm.objections.destroy', $it) }}" method="POST" class="inline" onsubmit="return confirm('حذف شود؟')">
                                @csrf @method('DELETE')
                                <button class="text-rose-600 hover:underline text-xs">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">موردی یافت نشد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $items->links() }}</div>
</div>
@endsection
