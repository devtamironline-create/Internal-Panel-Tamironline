@extends('layouts.admin')
@section('page-title', 'مدیریت مشتریان')
@section('main')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">مشتریان</h2>
            <p class="text-gray-600 mt-1">مدیریت مشتریان و اطلاعات آنها</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.customer-categories.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                دسته‌بندی‌ها
            </a>
            <a href="{{ route('admin.customers.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                افزودن مشتری
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4">
        <form action="{{ route('admin.customers.index') }}" method="GET" class="flex flex-col sm:flex-row gap-4">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="جستجو در نام، موبایل، کد ملی..." class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            <select name="category" class="px-4 py-2 border border-gray-300 rounded-lg">
                <option value="">همه دسته‌ها</option>
                @foreach($categories as $cat)
                <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
            <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg">
                <option value="">همه وضعیت‌ها</option>
                <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>فعال</option>
                <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>غیرفعال</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">فیلتر</button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">مشتری</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">موبایل</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">کسب و کار</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">دسته‌بندی</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($customers as $customer)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 font-medium">
                                {{ mb_substr($customer->first_name ?? 'C', 0, 1) }}
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">{{ $customer->full_name }}</p>
                                @if($customer->national_code)
                                <p class="text-sm text-gray-500" dir="ltr">کد ملی: {{ $customer->national_code }}</p>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-gray-900" dir="ltr">{{ $customer->mobile }}</td>
                    <td class="px-6 py-4 text-gray-600">{{ $customer->business_name ?? '—' }}</td>
                    <td class="px-6 py-4">
                        @if($customer->category)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                              style="background-color: {{ $customer->category->color }}20; color: {{ $customer->category->color }};">
                            {{ $customer->category->name }}
                        </span>
                        @else
                        <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        @if($customer->is_active)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">فعال</span>
                        @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">غیرفعال</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.customers.show', $customer) }}" class="p-2 text-gray-600 hover:text-green-600 hover:bg-green-50 rounded-lg" title="مشاهده">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </a>
                            <a href="{{ route('admin.customers.edit', $customer) }}" class="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg" title="ویرایش">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <a href="{{ route('admin.customers.impersonate', $customer) }}" class="p-2 text-gray-600 hover:text-purple-600 hover:bg-purple-50 rounded-lg" title="ورود به پنل مشتری">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                            </a>
                            <form action="{{ route('admin.customers.destroy', $customer) }}" method="POST" onsubmit="return confirm('آیا مطمئن هستید؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-2 text-gray-600 hover:text-red-600 hover:bg-red-50 rounded-lg" title="حذف">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">مشتری یافت نشد</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($customers->hasPages())
    <div class="flex justify-center">{{ $customers->links() }}</div>
    @endif
</div>
@endsection
