@extends('layouts.admin')
@section('page-title', 'لیست درخواست‌های ثبت‌نام تکنسین')

@section('main')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">درخواست‌های ثبت‌نام تکنسین</h1>
            <p class="text-sm text-gray-500 mt-1">لیست تمام درخواست‌های ثبت‌نام تکنسین‌ها</p>
        </div>
    </div>

    {{-- فیلتر و جستجو --}}
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <form method="GET" action="{{ route('technician.admin.registrations') }}" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">جستجو</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="نام، موبایل یا کد ملی..."
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
            </div>
            <div class="w-40">
                <label class="block text-xs font-medium text-gray-600 mb-1">وضعیت</label>
                <select name="status" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-blue-500 outline-none">
                    <option value="">همه</option>
                    <option value="incomplete" {{ request('status') === 'incomplete' ? 'selected' : '' }}>ناقص</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>در انتظار بررسی</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>تایید شده</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>رد شده</option>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                فیلتر
            </button>
            @if(request('search') || request('status'))
            <a href="{{ route('technician.admin.registrations') }}" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
                پاک کردن
            </a>
            @endif
        </form>
    </div>

    {{-- جدول --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        @if($registrations->isEmpty())
        <div class="p-8 text-center text-gray-400 text-sm">
            درخواستی یافت نشد.
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">#</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">نام و نام خانوادگی</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">موبایل</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">کد ملی</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">مرحله</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">وضعیت</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">تاریخ ثبت</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($registrations as $reg)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 text-gray-500">{{ $reg->id }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800">
                            {{ $reg->first_name }} {{ $reg->last_name }}
                        </td>
                        <td class="px-4 py-3 text-gray-600 dir-ltr text-left" dir="ltr">{{ $reg->mobile }}</td>
                        <td class="px-4 py-3 text-gray-600 dir-ltr text-left" dir="ltr">{{ $reg->national_code }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $reg->current_step >= 6 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                {{ $reg->current_step >= 6 ? 'تکمیل' : 'مرحله ' . $reg->current_step . ' از 5' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @switch($reg->status)
                                @case('incomplete')
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">ناقص</span>
                                    @break
                                @case('pending')
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">در انتظار بررسی</span>
                                    @break
                                @case('approved')
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">تایید شده</span>
                                    @break
                                @case('rejected')
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700">رد شده</span>
                                    @break
                            @endswitch
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ $reg->created_at->format('Y/m/d H:i') }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('technician.admin.registrations.show', $reg->id) }}"
                               class="text-blue-600 hover:text-blue-800 text-xs font-medium hover:underline">
                                مشاهده جزئیات
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- پیجینیشن --}}
        @if($registrations->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $registrations->links() }}
        </div>
        @endif
        @endif
    </div>

</div>
@endsection
