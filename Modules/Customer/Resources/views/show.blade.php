@extends('layouts.admin')
@section('page-title', 'جزئیات مشتری')
@section('main')
<div class="max-w-4xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">{{ $customer->full_name }}</h2>
            <p class="text-gray-600 mt-1">مشاهده اطلاعات کامل مشتری</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.customers.edit', $customer) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                ویرایش
            </a>
            <a href="{{ route('admin.customers.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                بازگشت
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <div class="flex items-center gap-4">
                <div class="w-20 h-20 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 font-bold text-2xl">
                    {{ mb_substr($customer->first_name ?? 'C', 0, 1) }}
                </div>
                <div class="flex-1">
                    <h3 class="text-xl font-semibold text-gray-900">{{ $customer->full_name }}</h3>
                    @if($customer->business_name)
                    <p class="text-gray-600">{{ $customer->business_name }}</p>
                    @endif
                    <div class="flex items-center gap-2 mt-2">
                        @if($customer->is_active)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">فعال</span>
                        @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">غیرفعال</span>
                        @endif
                        @if($customer->category)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                              style="background-color: {{ $customer->category->color }}20; color: {{ $customer->category->color }};">
                            {{ $customer->category->name }}
                        </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="p-6">
            <h4 class="text-lg font-semibold text-gray-900 mb-4">اطلاعات شخصی</h4>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <dt class="text-sm font-medium text-gray-500 mb-1">نام و نام خانوادگی</dt>
                    <dd class="text-base text-gray-900">{{ $customer->full_name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 mb-1">شماره موبایل</dt>
                    <dd class="text-base text-gray-900" dir="ltr">{{ $customer->mobile }}</dd>
                </div>
                @if($customer->national_code)
                <div>
                    <dt class="text-sm font-medium text-gray-500 mb-1">کد ملی</dt>
                    <dd class="text-base text-gray-900" dir="ltr">{{ $customer->national_code }}</dd>
                </div>
                @endif
                @if($customer->birth_date)
                <div>
                    <dt class="text-sm font-medium text-gray-500 mb-1">تاریخ تولد</dt>
                    <dd class="text-base text-gray-900">{{ \Morilog\Jalali\Jalalian::fromDateTime($customer->birth_date)->format('Y/m/d') }}</dd>
                </div>
                @endif
                @if($customer->business_name)
                <div>
                    <dt class="text-sm font-medium text-gray-500 mb-1">نام کسب و کار</dt>
                    <dd class="text-base text-gray-900">{{ $customer->business_name }}</dd>
                </div>
                @endif
                <div>
                    <dt class="text-sm font-medium text-gray-500 mb-1">دسته‌بندی</dt>
                    <dd class="text-base text-gray-900">{{ $customer->category?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 mb-1">تاریخ ثبت</dt>
                    <dd class="text-base text-gray-900">{{ \Morilog\Jalali\Jalalian::fromDateTime($customer->created_at)->format('Y/m/d H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 mb-1">آخرین بروزرسانی</dt>
                    <dd class="text-base text-gray-900">{{ \Morilog\Jalali\Jalalian::fromDateTime($customer->updated_at)->format('Y/m/d H:i') }}</dd>
                </div>
            </dl>
        </div>

        @if($customer->notes)
        <div class="p-6 border-t border-gray-100">
            <h4 class="text-lg font-semibold text-gray-900 mb-2">یادداشت‌های داخلی</h4>
            <p class="text-gray-700 whitespace-pre-wrap">{{ $customer->notes }}</p>
        </div>
        @endif
    </div>

    <!-- سرویس‌های مشتری -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h4 class="text-lg font-semibold text-gray-900">سرویس‌ها</h4>
            <span class="text-sm text-gray-500">{{ $customer->services->count() }} سرویس</span>
        </div>
        @if($customer->services->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">سرویس</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">دامنه</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">مبلغ</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تاریخ تمدید</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($customer->services as $service)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-900">{{ $service->product?->name ?? 'نامشخص' }}</div>
                        </td>
                        <td class="px-6 py-4 text-gray-600" dir="ltr">{{ $service->domain ?? '—' }}</td>
                        <td class="px-6 py-4 text-gray-900">{{ number_format($service->price) }} تومان</td>
                        <td class="px-6 py-4 text-gray-600">
                            @if($service->next_due_date)
                                {{ \Morilog\Jalali\Jalalian::fromDateTime($service->next_due_date)->format('Y/m/d') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($service->status === 'active')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">فعال</span>
                            @elseif($service->status === 'pending')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">در انتظار</span>
                            @elseif($service->status === 'suspended')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">تعلیق</span>
                            @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">{{ $service->status }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.services.show', $service) }}" class="text-blue-600 hover:text-blue-800 text-sm">مشاهده</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="p-6">
            <p class="text-gray-500 text-center py-4">هنوز سرویسی برای این مشتری ثبت نشده است</p>
        </div>
        @endif
    </div>
</div>
@endsection
