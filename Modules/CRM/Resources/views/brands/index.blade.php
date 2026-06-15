@extends('layouts.admin')

@section('page-title', 'برندها')

@section('main')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">برندها</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">مدیریت برندهای دستگاه‌ها</p>
        </div>
        @can('manage-crm-brands')
        <a href="{{ route('crm.brands.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            افزودن برند
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
            <a href="{{ route('crm.brands.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 text-sm rounded-lg">پاک‌سازی</a>
        @endif
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">برند</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Slug</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">ترتیب</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">فعال</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">ویژه</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($brands as $brand)
                @php
                    $logoSrc = $brand->logo
                        ? (\Illuminate\Support\Str::startsWith($brand->logo, ['http://','https://','/']) ? $brand->logo : asset('storage/' . ltrim($brand->logo, '/')))
                        : null;
                @endphp
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg border border-gray-200 dark:border-gray-600 flex items-center justify-center overflow-hidden bg-gray-50 dark:bg-gray-900">
                                @if($logoSrc)
                                    <img src="{{ $logoSrc }}" alt="{{ $brand->name }}" class="w-full h-full object-contain">
                                @else
                                    <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                @endif
                            </div>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $brand->name }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400 font-mono">{{ $brand->slug }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $brand->sort_order }}</td>
                    <td class="px-6 py-4">
                        @can('manage-crm-brands')
                        <form method="POST" action="{{ route('crm.brands.toggle', [$brand, 'is_active']) }}"
                              @if($brand->is_active) onsubmit="return confirm('غیرفعال‌سازی این برند باعث حذف آن از API سایت و صفحات مرتبط می‌شود. مطمئنید؟');" @endif>
                            @csrf @method('PUT')
                            <button type="submit" class="inline-flex">
                                @if($brand->is_active)
                                    <span class="px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full hover:bg-green-200">فعال</span>
                                @else
                                    <span class="px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full hover:bg-gray-200">غیرفعال</span>
                                @endif
                            </button>
                        </form>
                        @else
                            @if($brand->is_active)<span class="px-2.5 py-1 text-xs bg-green-100 text-green-800 rounded-full">فعال</span>@else<span class="px-2.5 py-1 text-xs bg-gray-100 text-gray-800 rounded-full">غیرفعال</span>@endif
                        @endcan
                    </td>
                    <td class="px-6 py-4">
                        @can('manage-crm-brands')
                        <form method="POST" action="{{ route('crm.brands.toggle', [$brand, 'is_featured']) }}">
                            @csrf @method('PUT')
                            <button type="submit" class="inline-flex">
                                @if($brand->is_featured)
                                    <span class="px-2.5 py-1 text-xs font-medium bg-amber-100 text-amber-800 rounded-full hover:bg-amber-200">★ ویژه</span>
                                @else
                                    <span class="px-2.5 py-1 text-xs font-medium bg-gray-50 text-gray-500 rounded-full hover:bg-gray-100">☆ معمولی</span>
                                @endif
                            </button>
                        </form>
                        @else
                            @if($brand->is_featured)<span class="px-2.5 py-1 text-xs bg-amber-100 text-amber-800 rounded-full">★ ویژه</span>@else<span class="px-2.5 py-1 text-xs bg-gray-50 text-gray-500 rounded-full">معمولی</span>@endif
                        @endcan
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            @can('manage-crm-brands')
                            <a href="{{ route('crm.brands.edit', $brand) }}" class="text-blue-600 hover:text-blue-800 text-sm">ویرایش</a>
                            <form action="{{ route('crm.brands.destroy', $brand) }}" method="POST" class="inline" onsubmit="return confirm('حذف این برند انجام شود؟');">
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
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">برندی ثبت نشده.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $brands->links() }}</div>
</div>
@endsection
