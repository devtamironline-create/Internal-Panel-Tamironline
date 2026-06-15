@extends('layouts.admin')

@section('page-title', 'صفحات ترکیبی (device × brand)')

@section('main')
<div class="p-6">
    <div class="mb-6 flex items-center justify-between flex-wrap gap-2">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">صفحات ترکیبی دستگاه × برند</h1>
            <p class="text-sm text-gray-500 mt-1">
                این صفحات روی URL <code class="font-mono ltr">/devices/{deviceSlug}/{brandSlug}</code> رندر می‌شوند و overrideهای per-pair را نگه می‌دارند.
            </p>
        </div>
        @can('manage-crm-devices')
        <a href="{{ route('crm.device-brand-pages.create') }}" class="px-4 py-2 bg-brand-600 text-white rounded text-sm">+ افزودن صفحه‌ی ترکیبی</a>
        @endcan
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    <form method="GET" class="mb-4 flex gap-2 flex-wrap">
        <select name="device_id" class="px-3 py-2 border border-gray-200 rounded text-sm">
            <option value="">همه دستگاه‌ها</option>
            @foreach($devices as $d)
                <option value="{{ $d->id }}" @selected((int) request('device_id') === (int) $d->id)>{{ $d->name }}</option>
            @endforeach
        </select>
        <select name="brand_id" class="px-3 py-2 border border-gray-200 rounded text-sm">
            <option value="">همه برندها</option>
            @foreach($brands as $b)
                <option value="{{ $b->id }}" @selected((int) request('brand_id') === (int) $b->id)>{{ $b->name }}</option>
            @endforeach
        </select>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="جستجو در نام/slug..."
               class="flex-1 px-3 py-2 border border-gray-200 rounded text-sm">
        <button class="px-4 py-2 bg-blue-600 text-white rounded text-sm">فیلتر</button>
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600">
                <tr>
                    <th class="px-4 py-2 text-right">دستگاه</th>
                    <th class="px-4 py-2 text-right">برند</th>
                    <th class="px-4 py-2 text-right">URL</th>
                    <th class="px-4 py-2 text-right">وضعیت</th>
                    <th class="px-4 py-2 text-right">به‌روزرسانی</th>
                    <th class="px-4 py-2 text-right">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pages as $p)
                <tr class="border-t border-gray-100 dark:border-gray-700">
                    <td class="px-4 py-3">
                        <div class="font-medium">{{ $p->device->name ?? '—' }}</div>
                        <div class="text-xs text-gray-500 font-mono ltr" dir="ltr">{{ $p->device->slug ?? '' }}</div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="font-medium">{{ $p->brand->name ?? '—' }}</div>
                        <div class="text-xs text-gray-500 font-mono ltr" dir="ltr">{{ $p->brand->slug ?? '' }}</div>
                    </td>
                    <td class="px-4 py-3">
                        <code class="text-xs ltr" dir="ltr">/devices/{{ $p->device->slug ?? '?' }}/{{ $p->brand->slug ?? '?' }}</code>
                    </td>
                    <td class="px-4 py-3">
                        @can('manage-crm-devices')
                        <form method="POST" action="{{ route('crm.device-brand-pages.toggle', $p->id) }}"
                              @if($p->is_active) onsubmit="return confirm('غیرفعال‌سازی این صفحه‌ی ترکیبی باعث حذف آن از API سایت می‌شود. مطمئنید؟');" @endif>
                            @csrf @method('PUT')
                            <button type="submit" class="inline-flex">
                                @if($p->is_active)
                                    <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700 hover:bg-emerald-200">فعال</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-xs bg-gray-200 text-gray-600 hover:bg-gray-300">غیرفعال</span>
                                @endif
                            </button>
                        </form>
                        @else
                            @if($p->is_active)<span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700">فعال</span>@else<span class="px-2 py-0.5 rounded-full text-xs bg-gray-200 text-gray-600">غیرفعال</span>@endif
                        @endcan
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">
                        {{ \Morilog\Jalali\Jalalian::fromDateTime($p->updated_at)->format('Y/m/d H:i') }}
                    </td>
                    <td class="px-4 py-3 flex gap-2">
                        @can('manage-crm-devices')
                        <a href="{{ route('crm.device-brand-pages.edit', $p->id) }}" class="text-blue-600 hover:underline">ویرایش</a>
                        <form method="POST" action="{{ route('crm.device-brand-pages.destroy', $p->id) }}"
                              onsubmit="return confirm('حذف این صفحه‌ی ترکیبی؟');">
                            @csrf @method('DELETE')
                            <button class="text-red-600 hover:underline">حذف</button>
                        </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">صفحه‌ای ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $pages->links() }}</div>
</div>
@endsection
