@extends('layouts.admin')

@section('page-title', 'دستگاه‌ها')

@section('main')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">دستگاه‌ها</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">مدیریت انواع دستگاه‌ها — مرتب با ویژه‌ها در ابتدا</p>
        </div>
        @can('manage-crm-devices')
        <a href="{{ route('crm.devices.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            افزودن دستگاه
        </a>
        @endcan
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    {{-- فیلتر و جستجو --}}
    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs text-gray-500 mb-1">جستجو</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="نام یا اسلاگ..."
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">وضعیت</label>
            <select name="active" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                <option value="">همه</option>
                <option value="1" @selected(request('active') === '1')>فعال</option>
                <option value="0" @selected(request('active') === '0')>غیرفعال</option>
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">ویژه</label>
            <select name="featured" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                <option value="">همه</option>
                <option value="1" @selected(request('featured') === '1')>فقط ویژه</option>
                <option value="0" @selected(request('featured') === '0')>غیر ویژه</option>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-brand-600 text-white text-sm rounded-lg hover:bg-brand-700">اعمال فیلتر</button>
        @if(request()->hasAny(['q', 'active', 'featured']))
            <a href="{{ route('crm.devices.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 text-sm rounded-lg">پاک‌سازی</a>
        @endif
    </form>

    @can('manage-permissions')
    {{-- ─── فرمِ مستقلِ عملیات گروهی (الگوی form="..."؛ بدون nested-form) ─── --}}
    <form id="devices-bulk" method="POST" action="{{ route('crm.devices.bulk-toggle') }}">
        @csrf
        <input type="hidden" name="target" id="devices-bulk-target" value="">
        <input type="hidden" name="action" id="devices-bulk-action" value="">
    </form>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-3 flex flex-wrap items-center gap-2">
        <span class="text-sm text-gray-600 dark:text-gray-300 ml-2">عملیات گروهی روی موارد انتخاب‌شده:</span>
        <button type="submit" form="devices-bulk"
                onclick="document.getElementById('devices-bulk-target').value='is_active'; document.getElementById('devices-bulk-action').value='activate'; return confirm('دستگاه‌های انتخاب‌شده در سایت فعال شوند؟');"
                class="px-3 py-1.5 text-sm rounded-lg bg-green-600 text-white hover:bg-green-700">
            فعال‌سازی در سایت
        </button>
        <button type="submit" form="devices-bulk"
                onclick="document.getElementById('devices-bulk-target').value='is_active'; document.getElementById('devices-bulk-action').value='deactivate'; return confirm('دستگاه‌های انتخاب‌شده در سایت غیرفعال شوند؟ این کار آن‌ها را از API سایت حذف می‌کند.');"
                class="px-3 py-1.5 text-sm rounded-lg bg-red-600 text-white hover:bg-red-700">
            غیرفعال‌سازی در سایت
        </button>
        <button type="submit" form="devices-bulk"
                onclick="document.getElementById('devices-bulk-target').value='is_active_app'; document.getElementById('devices-bulk-action').value='activate'; return confirm('دستگاه‌های انتخاب‌شده در اپ فعال شوند؟');"
                class="px-3 py-1.5 text-sm rounded-lg bg-sky-600 text-white hover:bg-sky-700">
            فعال‌سازی در اپ
        </button>
        <button type="submit" form="devices-bulk"
                onclick="document.getElementById('devices-bulk-target').value='is_active_app'; document.getElementById('devices-bulk-action').value='deactivate'; return confirm('دستگاه‌های انتخاب‌شده در اپ غیرفعال شوند؟ این کار آن‌ها را از اپلیکیشن مشتری حذف می‌کند.');"
                class="px-3 py-1.5 text-sm rounded-lg bg-gray-600 text-white hover:bg-gray-700">
            غیرفعال‌سازی در اپ
        </button>
    </div>
    @endcan

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    @can('manage-permissions')
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                        <input type="checkbox" id="devices-select-all" class="rounded border-gray-300">
                    </th>
                    @endcan
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">دستگاه</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">آیکن / تم</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">ترتیب</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">فعال (سایت)</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">نمایش در اپ</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">ویژه</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($devices as $device)
                @php
                    $thumbSrc = $device->thumbnail
                        ? (\Illuminate\Support\Str::startsWith($device->thumbnail, ['http://','https://','/']) ? $device->thumbnail : asset('storage/' . ltrim($device->thumbnail, '/')))
                        : null;
                @endphp
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    @can('manage-permissions')
                    <td class="px-6 py-4">
                        <input type="checkbox" name="ids[]" value="{{ $device->id }}" form="devices-bulk"
                               class="devices-row-check rounded border-gray-300">
                    </td>
                    @endcan
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg border border-gray-200 dark:border-gray-600 flex items-center justify-center overflow-hidden bg-gray-50 dark:bg-gray-900">
                                @if($thumbSrc)
                                    <img src="{{ $thumbSrc }}" alt="{{ $device->name }}" class="w-full h-full object-contain">
                                @else
                                    <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                @endif
                            </div>
                            <div>
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $device->name }}</div>
                                <div class="text-xs text-gray-500 font-mono">/{{ $device->slug }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex flex-col gap-1 text-xs">
                            @if($device->icon)
                                <span class="font-mono bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded w-fit">{{ $device->icon }}</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                            @if($device->tone)
                                <span class="font-mono text-gray-500">{{ $device->tone }}</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $device->sort_order }}</td>
                    <td class="px-6 py-4">
                        @can('manage-crm-devices')
                        <form method="POST" action="{{ route('crm.devices.toggle', [$device, 'is_active']) }}"
                              @if($device->is_active) onsubmit="return confirm('غیرفعال‌سازی این دستگاه باعث حذف آن از API سایت و صفحات مرتبط می‌شود. مطمئنید؟');" @endif>
                            @csrf @method('PUT')
                            <button type="submit" class="inline-flex">
                                @if($device->is_active)
                                    <span class="px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full hover:bg-green-200">فعال</span>
                                @else
                                    <span class="px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full hover:bg-gray-200">غیرفعال</span>
                                @endif
                            </button>
                        </form>
                        @else
                            @if($device->is_active)<span class="px-2.5 py-1 text-xs bg-green-100 text-green-800 rounded-full">فعال</span>@else<span class="px-2.5 py-1 text-xs bg-gray-100 text-gray-800 rounded-full">غیرفعال</span>@endif
                        @endcan
                    </td>
                    <td class="px-6 py-4">
                        @can('manage-crm-devices')
                        <form method="POST" action="{{ route('crm.devices.toggle', [$device, 'is_active_app']) }}"
                              @if($device->is_active_app) onsubmit="return confirm('غیرفعال‌سازی نمایش در اپ باعث حذف این دستگاه از اپلیکیشن مشتری می‌شود. مطمئنید؟');" @endif>
                            @csrf @method('PUT')
                            <button type="submit" class="inline-flex">
                                @if($device->is_active_app)
                                    <span class="px-2.5 py-1 text-xs font-medium bg-sky-100 text-sky-800 rounded-full hover:bg-sky-200">در اپ</span>
                                @else
                                    <span class="px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full hover:bg-gray-200">حذف از اپ</span>
                                @endif
                            </button>
                        </form>
                        @else
                            @if($device->is_active_app)<span class="px-2.5 py-1 text-xs bg-sky-100 text-sky-800 rounded-full">در اپ</span>@else<span class="px-2.5 py-1 text-xs bg-gray-100 text-gray-800 rounded-full">حذف از اپ</span>@endif
                        @endcan
                    </td>
                    <td class="px-6 py-4">
                        @can('manage-crm-devices')
                        <form method="POST" action="{{ route('crm.devices.toggle', [$device, 'is_featured']) }}">
                            @csrf @method('PUT')
                            <button type="submit" class="inline-flex">
                                @if($device->is_featured)
                                    <span class="px-2.5 py-1 text-xs font-medium bg-amber-100 text-amber-800 rounded-full hover:bg-amber-200">★ ویژه</span>
                                @else
                                    <span class="px-2.5 py-1 text-xs font-medium bg-gray-50 text-gray-500 rounded-full hover:bg-gray-100">☆ معمولی</span>
                                @endif
                            </button>
                        </form>
                        @else
                            @if($device->is_featured)<span class="px-2.5 py-1 text-xs bg-amber-100 text-amber-800 rounded-full">★ ویژه</span>@else<span class="px-2.5 py-1 text-xs bg-gray-50 text-gray-500 rounded-full">معمولی</span>@endif
                        @endcan
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            @can('manage-crm-devices')
                            <a href="{{ route('crm.devices.edit', $device) }}" class="text-blue-600 hover:text-blue-800 text-sm">ویرایش</a>
                            <form action="{{ route('crm.devices.destroy', $device) }}" method="POST" class="inline" onsubmit="return confirm('حذف این دستگاه انجام شود؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm">حذف</button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="@can('manage-permissions')8@else 7 @endcan" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">دستگاهی ثبت نشده.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $devices->links() }}</div>
</div>

@can('manage-permissions')
<script>
    (function () {
        var selectAll = document.getElementById('devices-select-all');
        if (! selectAll) return;
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.devices-row-check').forEach(function (cb) {
                cb.checked = selectAll.checked;
            });
        });
    })();
</script>
@endcan
@endsection
