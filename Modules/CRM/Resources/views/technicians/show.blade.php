@extends('layouts.admin')

@section('page-title', 'جزئیات تکنسین')

@section('main')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-4">
            @if($technician->img_personal)
            <img src="{{ $technician->img_personal }}" alt="{{ $technician->full_name }}" class="w-16 h-16 rounded-full object-cover bg-gray-100">
            @endif
            <div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $technician->full_name }}</h1>
                <p class="mt-1">@tel($technician->mobile)</p>
                @if($technician->technician_id)
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5" dir="ltr">کد: {{ $technician->technician_id }}</p>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-2">
            @can('edit-crm-technician')
            <a href="{{ route('crm.technicians.edit', $technician) }}" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">ویرایش</a>
            @endcan
            <a href="{{ route('crm.technicians.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">بازگشت</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">مشخصات و آدرس</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <dt class="text-gray-500 dark:text-gray-400">نام نمایشی</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $technician->firstname_tech ?: '—' }}</dd>

                <dt class="text-gray-500 dark:text-gray-400">تلفن ثابت</dt>
                <dd class="text-gray-900 dark:text-gray-100">@tel($technician->phone)</dd>

                <dt class="text-gray-500 dark:text-gray-400">تلفن اضطراری</dt>
                <dd class="text-gray-900 dark:text-gray-100">@tel($technician->phone_force)</dd>

                <dt class="text-gray-500 dark:text-gray-400">کد ملی</dt>
                <dd class="text-gray-900 dark:text-gray-100" dir="ltr">{{ $technician->national_code ?: '—' }}</dd>

                <dt class="text-gray-500 dark:text-gray-400">استان</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $technician->province ?: '—' }}</dd>

                <dt class="text-gray-500 dark:text-gray-400 col-span-2">آدرس</dt>
                <dd class="text-gray-900 dark:text-gray-100 col-span-2 whitespace-pre-wrap">{{ $technician->address ?: '—' }}</dd>
            </dl>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">تخصص و قوانین کاری</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <dt class="text-gray-500 dark:text-gray-400">تخصص</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $technician->specialty ?: '—' }}</dd>

                <dt class="text-gray-500 dark:text-gray-400">سطح/نوع</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $technician->type_tech ?: '—' }}</dd>

                <dt class="text-gray-500 dark:text-gray-400">درصد کمیسیون</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $technician->percent ?? 0 }}%</dd>

                <dt class="text-gray-500 dark:text-gray-400">درصد دوم</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $technician->tech_per_of_all !== null ? $technician->tech_per_of_all . '%' : '—' }}</dd>

                <dt class="text-gray-500 dark:text-gray-400">روش محاسبه</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $technician->type_of_calc_tech ?: '—' }}</dd>

                <dt class="text-gray-500 dark:text-gray-400">سقف سفارش همزمان</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $technician->max_order ?? 'نامحدود' }}</dd>

                <dt class="text-gray-500 dark:text-gray-400">سقف مبلغ سفارش</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $technician->max_price ? number_format($technician->max_price) . ' تومان' : 'نامحدود' }}</dd>

                <dt class="text-gray-500 dark:text-gray-400 col-span-2">توضیحات</dt>
                <dd class="text-gray-900 dark:text-gray-100 col-span-2 whitespace-pre-wrap">{{ $technician->description ?: '—' }}</dd>
            </dl>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">وضعیت</h2>
            <div class="flex flex-wrap items-center gap-2">
                @if($technician->status === 'active')
                <span class="px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">فعال</span>
                @elseif($technician->status === 'inactive')
                <span class="px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">غیرفعال</span>
                @else
                <span class="px-2.5 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">{{ $technician->status ?: '—' }}</span>
                @endif
                @if($technician->ready_for_delivery)
                <span class="px-2.5 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">آماده دریافت سفارش</span>
                @endif
            </div>
        </div>

        @if($technician->cart_img)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">تصویر کارت ملی</h2>
            <img src="{{ $technician->cart_img }}" alt="کارت ملی" class="max-w-full max-h-64 rounded-lg">
        </div>
        @endif
    </div>

    {{-- حساب کاربری تکنسین (ورود به پنل) --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-3">حساب کاربری (دسترسی به پنل)</h2>
        @if($technician->user_id)
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <div class="text-sm text-gray-900 dark:text-gray-100">✓ حساب فعال است (User ID: {{ $technician->user_id }})</div>
                <div class="text-xs text-gray-500 mt-1">تکنسین می‌تواند با موبایل/رمز خود وارد پنل شود و به «داشبورد تکنسین» دسترسی دارد.</div>
            </div>
            @can('edit-crm-technician')
            <form action="{{ route('crm.technicians.unlink-user', $technician) }}" method="POST" onsubmit="return confirm('جدا شود؟');">
                @csrf
                <button class="px-3 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 text-sm">جدا کردن حساب</button>
            </form>
            @endcan
        </div>
        @else
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <p class="text-sm text-gray-500 dark:text-gray-400">برای این تکنسین حساب کاربری ساخته نشده — نمی‌تواند وارد پنل شود.</p>
            @can('edit-crm-technician')
            <form action="{{ route('crm.technicians.provision-user', $technician) }}" method="POST">
                @csrf
                <button class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm">ساخت حساب کاربری</button>
            </form>
            @endcan
        </div>
        @endif
    </div>
</div>
@endsection
