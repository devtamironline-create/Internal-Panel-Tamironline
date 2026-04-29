@extends('layouts.admin')

@section('page-title', 'تکنسین‌های فعال')

@section('main')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">تکنسین‌های فعال</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">مدیریت تکنسین‌های در حال فعالیت (جدا از پیش‌ثبت‌نام)</p>
        </div>
        @can('create-crm-technician')
        <a href="{{ route('crm.technicians.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            افزودن تکنسین
        </a>
        @endcan
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">جستجو</label>
            <input type="text" name="q" value="{{ $search }}" placeholder="موبایل، نام، کد تکنسین، کد ملی، تخصص..."
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">استان</label>
            <select name="province" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— همه —</option>
                @foreach($provinces as $p)
                <option value="{{ $p }}" @selected($province === $p)>{{ $p }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">سطح/نوع</label>
            <input type="text" name="type_tech" value="{{ $type }}" placeholder="مثلاً regular"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">وضعیت</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— همه —</option>
                <option value="active" @selected($status === 'active')>فعال</option>
                <option value="inactive" @selected($status === 'inactive')>غیرفعال</option>
                <option value="ready" @selected($status === 'ready')>آماده سفارش</option>
            </select>
        </div>
        <div class="md:col-span-4 flex items-center gap-2">
            <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800">اعمال فیلتر</button>
            @if($search || $province || $type || $status)
            <a href="{{ route('crm.technicians.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">پاک کردن</a>
            @endif
        </div>
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">نام</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">کد</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">موبایل</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">سطح</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">استان</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">کمیسیون</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($technicians as $tech)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-4">
                        <a href="{{ route('crm.technicians.show', $tech) }}" class="font-medium text-gray-900 dark:text-gray-100 hover:text-brand-600">
                            {{ $tech->full_name }}
                        </a>
                        @if($tech->specialty)
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $tech->specialty }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400" dir="ltr">{{ $tech->technician_id ?: '—' }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400" dir="ltr">{{ $tech->mobile }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $tech->type_tech ?: '—' }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $tech->province ?: '—' }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $tech->percent ?? 0 }}%</td>
                    <td class="px-6 py-4">
                        @if($tech->status === 'active')
                            @if($tech->ready_for_delivery)
                            <span class="px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">آماده</span>
                            @else
                            <span class="px-2.5 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">فعال</span>
                            @endif
                        @elseif($tech->status === 'inactive')
                        <span class="px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">غیرفعال</span>
                        @else
                        <span class="px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">{{ $tech->status ?: '—' }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('crm.technicians.show', $tech) }}" class="text-gray-600 hover:text-gray-900 text-sm">جزئیات</a>
                            @can('edit-crm-technician')
                            <a href="{{ route('crm.technicians.edit', $tech) }}" class="text-blue-600 hover:text-blue-800 text-sm">ویرایش</a>
                            <form action="{{ route('crm.technicians.impersonate', $tech) }}" method="POST" class="inline" onsubmit="return confirm('وارد پنل «{{ $tech->full_name }}» می‌شوید. ادامه دهید؟');">
                                @csrf
                                <button type="submit" class="text-purple-600 hover:text-purple-800 text-sm">ورود به پنل</button>
                            </form>
                            @endcan
                            @can('delete-crm-technician')
                            <form action="{{ route('crm.technicians.destroy', $tech) }}" method="POST" class="inline" onsubmit="return confirm('حذف این تکنسین انجام شود؟');">
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
                    <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">تکنسین‌ای یافت نشد.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $technicians->links() }}</div>
</div>
@endsection
