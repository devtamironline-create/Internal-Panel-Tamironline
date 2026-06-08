@extends('layouts.admin')

@section('page-title', 'دسته‌بندی دستگاه‌ها')

@section('main')
<div class="p-6">
    <div class="mb-6 flex items-start justify-between flex-wrap gap-3 p-5 bg-gradient-to-l from-indigo-50 to-violet-50 dark:from-indigo-900/30 dark:to-violet-900/30 rounded-xl border border-indigo-200 dark:border-indigo-800">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">دسته‌بندی دستگاه‌ها</h1>
                <p class="text-sm text-gray-600 dark:text-gray-300 mt-0.5">گروه‌بندی والد مثل «لوازم آشپزخانه»، «لوازم سرمایشی»، «لوازم گرمایشی».</p>
            </div>
        </div>
        @can('manage-crm-devices')
        <a href="{{ route('crm.device-categories.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 inline-flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4v16m8-8H4"/></svg>
            افزودن دسته‌بندی
        </a>
        @endcan
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    <form method="GET" class="mb-5 flex gap-2 items-center p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="جستجو در نام یا slug..."
               class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
        <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">فیلتر</button>
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="px-4 py-2 text-right">دسته‌بندی</th>
                    <th class="px-4 py-2 text-right">slug</th>
                    <th class="px-4 py-2 text-right">تعداد دستگاه</th>
                    <th class="px-4 py-2 text-right">ترتیب</th>
                    <th class="px-4 py-2 text-right">وضعیت</th>
                    <th class="px-4 py-2 text-right">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $c)
                <tr class="border-t border-gray-100 dark:border-gray-700">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            @if($c->icon)
                                <span class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs font-mono ltr" dir="ltr">{{ \Illuminate\Support\Str::limit($c->icon, 4, '') }}</span>
                            @endif
                            <span class="font-semibold">{{ $c->name }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs ltr" dir="ltr">{{ $c->slug }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs bg-blue-50 text-blue-700">{{ $c->devices_count }}</span>
                    </td>
                    <td class="px-4 py-3">{{ $c->sort_order }}</td>
                    <td class="px-4 py-3">
                        @if($c->is_active)
                            <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700">فعال</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-xs bg-gray-200 text-gray-600">غیرفعال</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 flex gap-2">
                        @can('manage-crm-devices')
                        <a href="{{ route('crm.device-categories.edit', $c->id) }}" class="text-blue-600 hover:underline">ویرایش</a>
                        <form method="POST" action="{{ route('crm.device-categories.toggle', $c->id) }}">
                            @csrf @method('PUT')
                            <button class="text-amber-600 hover:underline">{{ $c->is_active ? 'غیرفعال' : 'فعال' }}</button>
                        </form>
                        <form method="POST" action="{{ route('crm.device-categories.destroy', $c->id) }}" onsubmit="return confirm('حذف شود؟ دستگاه‌های این دسته بدون دسته می‌شوند.');">
                            @csrf @method('DELETE')
                            <button class="text-red-600 hover:underline">حذف</button>
                        </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">دسته‌بندی‌ای ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $categories->links() }}</div>
</div>
@endsection
