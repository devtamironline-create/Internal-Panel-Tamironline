@extends('layouts.panel')
@section('page-title', 'پروفایل من')

@section('main')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">پروفایل من</h1>
        <a href="{{ route('panel.profile.edit') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-500 text-white rounded-xl hover:bg-brand-600 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            ویرایش پروفایل
        </a>
    </div>

    <!-- Profile Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- Header -->
        <div class="bg-gradient-to-br from-brand-500 to-brand-600 px-6 py-8">
            <div class="flex items-center gap-4">
                <div class="w-20 h-20 rounded-2xl bg-white/20 flex items-center justify-center text-white text-3xl font-bold">
                    {{ mb_substr($customer->first_name, 0, 1) }}
                </div>
                <div class="text-white">
                    <h2 class="text-2xl font-bold">{{ $customer->full_name }}</h2>
                    <p class="text-brand-100 mt-1">{{ $customer->mobile }}</p>
                </div>
            </div>
        </div>

        <!-- Info -->
        <div class="p-6">
            <div class="grid md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div>
                        <label class="text-sm text-gray-500">نام</label>
                        <p class="text-gray-900 font-medium mt-1">{{ $customer->first_name }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">نام خانوادگی</label>
                        <p class="text-gray-900 font-medium mt-1">{{ $customer->last_name }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">شماره موبایل</label>
                        <p class="text-gray-900 font-medium mt-1 ltr">{{ $customer->mobile }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">ایمیل</label>
                        <p class="text-gray-900 font-medium mt-1">{{ $customer->email ?? '-' }}</p>
                    </div>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="text-sm text-gray-500">کد ملی</label>
                        <p class="text-gray-900 font-medium mt-1 ltr">{{ $customer->national_code ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">نام شرکت/کسب‌وکار</label>
                        <p class="text-gray-900 font-medium mt-1">{{ $customer->business_name ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">تاریخ تولد</label>
                        <p class="text-gray-900 font-medium mt-1">
                            @if($customer->birth_date)
                                {{ \Morilog\Jalali\Jalalian::fromDateTime($customer->birth_date)->format('Y/m/d') }}
                            @else
                                -
                            @endif
                        </p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">کد پستی</label>
                        <p class="text-gray-900 font-medium mt-1 ltr">{{ $customer->postal_code ?? '-' }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-6 pt-6 border-t border-gray-100">
                <label class="text-sm text-gray-500">آدرس</label>
                <p class="text-gray-900 font-medium mt-1">{{ $customer->address ?? '-' }}</p>
            </div>
        </div>
    </div>

    <!-- Account Info -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-bold text-gray-900 mb-4">اطلاعات حساب کاربری</h3>
        <div class="grid md:grid-cols-3 gap-4">
            <div class="p-4 bg-gray-50 rounded-xl">
                <p class="text-sm text-gray-500">تاریخ عضویت</p>
                <p class="font-semibold text-gray-900 mt-1">
                    {{ \Morilog\Jalali\Jalalian::fromDateTime($user->created_at)->format('Y/m/d') }}
                </p>
            </div>
            <div class="p-4 bg-gray-50 rounded-xl">
                <p class="text-sm text-gray-500">آخرین ورود</p>
                <p class="font-semibold text-gray-900 mt-1">
                    @if($user->last_login_at)
                        {{ \Morilog\Jalali\Jalalian::fromDateTime($user->last_login_at)->format('Y/m/d H:i') }}
                    @else
                        -
                    @endif
                </p>
            </div>
            <div class="p-4 bg-gray-50 rounded-xl">
                <p class="text-sm text-gray-500">وضعیت حساب</p>
                <p class="font-semibold mt-1">
                    @if($user->is_active)
                        <span class="text-green-600">فعال</span>
                    @else
                        <span class="text-red-600">غیرفعال</span>
                    @endif
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
