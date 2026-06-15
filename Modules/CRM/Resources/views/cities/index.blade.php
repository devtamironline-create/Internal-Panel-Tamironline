@extends('layouts.admin')

@section('page-title', 'شهرها')

@section('main')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">شهرها</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">مدیریت شهرها (هر شهر متعلق به یک استان)</p>
        </div>
        @can('manage-crm-cities')
        <a href="{{ route('crm.cities.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            افزودن شهر
        </a>
        @endcan
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    {{-- فیلتر استان --}}
    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 flex items-end gap-3">
        <div class="flex-1 max-w-xs">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">فیلتر بر اساس استان</label>
            <select name="province_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— همه استان‌ها —</option>
                @foreach($provinces as $p)
                <option value="{{ $p->id }}" @selected($provinceId === $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800">اعمال فیلتر</button>
        @if($provinceId)
        <a href="{{ route('crm.cities.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">پاک کردن</a>
        @endif
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">نام</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">استان</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Slug</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">ترتیب</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">نمایش در اپ</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($cities as $city)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100">{{ $city->name }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $city->province?->name }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $city->slug }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $city->sort_order }}</td>
                    <td class="px-6 py-4">
                        @can('manage-crm-cities')
                        <form action="{{ route('crm.cities.toggle-active', $city) }}" method="POST" class="inline">
                            @csrf @method('PUT')
                            @if($city->is_active)
                                <button type="submit" title="غیرفعال کردن نمایش در اپلیکیشن"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-100 hover:bg-emerald-200 text-emerald-700 text-xs font-medium">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> فعال
                                </button>
                            @else
                                <button type="submit" title="فعال کردن نمایش در اپلیکیشن"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-500 text-xs font-medium">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> غیرفعال
                                </button>
                            @endif
                        </form>
                        @else
                            <span class="text-xs {{ $city->is_active ? 'text-emerald-600' : 'text-gray-400' }}">{{ $city->is_active ? 'فعال' : 'غیرفعال' }}</span>
                        @endcan
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2 flex-wrap">
                            @unless($city->parent_city_id)
                            @can('manage-crm-settings')
                            <a href="{{ route('crm.regions.index', ['province_id' => $city->province_id, 'city_id' => $city->id]) }}" class="text-indigo-600 hover:text-indigo-800 text-sm" title="مدیریت مناطق این شهر">مناطق</a>
                            @endcan
                            @endunless
                            @can('manage-crm-cities')
                            <a href="{{ route('crm.cities.edit', $city) }}" class="text-blue-600 hover:text-blue-800 text-sm">ویرایش</a>
                            <a href="{{ route('crm.cities.convert.form', $city) }}" class="text-emerald-600 hover:text-emerald-800 text-sm" title="تبدیل این شهر به منطقهٔ ذیل شهر دیگر">
                                تبدیل به منطقه
                            </a>
                            <form action="{{ route('crm.cities.destroy', $city) }}" method="POST" class="inline" onsubmit="return confirm('حذف این شهر، همهٔ مناطق آن را هم حذف می‌کند و از سفارش‌های ثبت‌شده جدا می‌شود. مطمئنید؟');">
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
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">شهری ثبت نشده.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $cities->links() }}</div>
</div>
@endsection
