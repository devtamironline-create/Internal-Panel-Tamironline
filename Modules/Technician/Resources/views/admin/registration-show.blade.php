@extends('layouts.admin')
@section('page-title', 'جزئیات درخواست - ' . $registration->first_name . ' ' . $registration->last_name)

@php
    $statusLabels = ['incomplete' => 'ناقص', 'pending' => 'در انتظار بررسی', 'approved' => 'تایید شده', 'rejected' => 'رد شده'];
    $statusColors = ['incomplete' => 'gray', 'pending' => 'amber', 'approved' => 'green', 'rejected' => 'red'];
    $activityLabels = ['install' => 'نصب', 'repair' => 'تعمیر', 'install_repair' => 'نصب و تعمیر'];
    $transportLabels = ['motorcycle' => 'موتور', 'car' => 'خودرو', 'none' => 'وسیله نقلیه ندارم'];
    $educationLabels = ['diploma' => 'دیپلم', 'associate' => 'کاردانی', 'bachelor' => 'کارشناسی', 'master' => 'کارشناسی ارشد', 'doctorate' => 'دکتری'];
    $genderLabels = ['male' => 'مرد', 'female' => 'زن'];
    $maritalLabels = ['single' => 'مجرد', 'married' => 'متاهل'];
    $color = $statusColors[$registration->status] ?? 'gray';
@endphp

@section('main')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('technician.admin.registrations') }}"
               class="flex items-center gap-1 text-gray-500 hover:text-gray-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900">{{ $registration->first_name }} {{ $registration->last_name }}</h1>
                <p class="text-sm text-gray-500 mt-0.5">شماره درخواست: {{ $registration->id }} | ثبت‌شده: {{ $registration->created_at->format('Y/m/d H:i') }}</p>
            </div>
        </div>
        <span class="px-3 py-1 text-sm font-bold rounded-full bg-{{ $color }}-100 text-{{ $color }}-700">
            {{ $statusLabels[$registration->status] ?? $registration->status }}
        </span>
    </div>

    {{-- پیام موفقیت --}}
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
        {{ session('success') }}
    </div>
    @endif

    {{-- تغییر وضعیت --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-bold text-gray-800 mb-3">تغییر وضعیت</h2>
        <div class="flex gap-2">
            @foreach(['pending' => 'در انتظار بررسی', 'approved' => 'تایید', 'rejected' => 'رد'] as $status => $label)
                @if($registration->status !== $status)
                <form method="POST" action="{{ route('technician.admin.registrations.update-status', $registration->id) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="status" value="{{ $status }}">
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors
                        {{ $status === 'approved' ? 'bg-green-100 text-green-700 hover:bg-green-200' : '' }}
                        {{ $status === 'rejected' ? 'bg-red-100 text-red-700 hover:bg-red-200' : '' }}
                        {{ $status === 'pending' ? 'bg-amber-100 text-amber-700 hover:bg-amber-200' : '' }}">
                        {{ $label }}
                    </button>
                </form>
                @endif
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- اطلاعات هویتی --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-bold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                اطلاعات هویتی
            </h2>
            <dl class="space-y-3">
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">نام</dt>
                    <dd class="text-sm font-medium text-gray-800">{{ $registration->first_name }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">نام خانوادگی</dt>
                    <dd class="text-sm font-medium text-gray-800">{{ $registration->last_name }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">نام پدر</dt>
                    <dd class="text-sm font-medium text-gray-800">{{ $registration->father_name }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">کد ملی</dt>
                    <dd class="text-sm font-medium text-gray-800" dir="ltr">{{ $registration->national_code }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">شماره شناسنامه</dt>
                    <dd class="text-sm font-medium text-gray-800" dir="ltr">{{ $registration->shenasname_number ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">تاریخ تولد</dt>
                    <dd class="text-sm font-medium text-gray-800">{{ $registration->birth_date ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">شماره موبایل</dt>
                    <dd class="text-sm font-medium text-gray-800" dir="ltr">{{ $registration->mobile }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">جنسیت</dt>
                    <dd class="text-sm font-medium text-gray-800">{{ $genderLabels[$registration->gender] ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">وضعیت تاهل</dt>
                    <dd class="text-sm font-medium text-gray-800">{{ $maritalLabels[$registration->marital_status] ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">استان / شهر</dt>
                    <dd class="text-sm font-medium text-gray-800">{{ $registration->province ?? '—' }} / {{ $registration->city ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        {{-- اطلاعات تکمیلی --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-bold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                اطلاعات تکمیلی
            </h2>
            <dl class="space-y-3">
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">مقطع تحصیلی</dt>
                    <dd class="text-sm font-medium text-gray-800">{{ $educationLabels[$registration->education_level] ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">رشته تحصیلی</dt>
                    <dd class="text-sm font-medium text-gray-800">{{ $registration->field_of_study ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">پروانه کسب</dt>
                    <dd class="text-sm font-medium text-gray-800">{{ $registration->has_business_license ? 'بله' : 'خیر' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">مغازه/دفتر</dt>
                    <dd class="text-sm font-medium text-gray-800">{{ $registration->has_shop ? 'بله' : 'خیر' }}</dd>
                </div>
                @if($registration->has_shop)
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">آدرس مغازه</dt>
                    <dd class="text-sm font-medium text-gray-800 text-left max-w-[200px]">{{ $registration->shop_address }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">تلفن مغازه</dt>
                    <dd class="text-sm font-medium text-gray-800" dir="ltr">{{ $registration->shop_phone }}</dd>
                </div>
                @endif
            </dl>

            {{-- سوابق شغلی --}}
            @if(!empty($registration->work_experiences))
            <div class="mt-4 pt-4 border-t border-gray-100">
                <h3 class="text-xs font-bold text-gray-600 mb-2">سوابق شغلی</h3>
                <div class="space-y-2">
                    @foreach($registration->work_experiences as $exp)
                    <div class="bg-gray-50 rounded-lg p-2.5 text-xs">
                        <span class="font-semibold text-gray-700">{{ $exp['title'] ?? '' }}</span>
                        <span class="text-gray-400 mx-1">|</span>
                        <span class="text-gray-600">{{ $exp['company'] ?? '' }}</span>
                        <span class="text-gray-400 mx-1">|</span>
                        <span class="text-gray-500">{{ $exp['duration'] ?? '' }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- مدارک --}}
            @if(!empty($registration->certificates))
            <div class="mt-4 pt-4 border-t border-gray-100">
                <h3 class="text-xs font-bold text-gray-600 mb-2">مدارک و دوره‌ها</h3>
                <div class="space-y-2">
                    @foreach($registration->certificates as $cert)
                    <div class="bg-gray-50 rounded-lg p-2.5 text-xs">
                        <span class="font-semibold text-gray-700">{{ $cert['title'] ?? '' }}</span>
                        <span class="text-gray-400 mx-1">|</span>
                        <span class="text-gray-600">{{ $cert['institution'] ?? '' }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- مناطق تحت پوشش --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-bold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                مناطق تحت پوشش
            </h2>
            <dl class="space-y-3">
                <div>
                    <dt class="text-xs text-gray-500 mb-1">مناطق تهران</dt>
                    <dd class="flex flex-wrap gap-1">
                        @forelse($registration->tehran_districts ?? [] as $district)
                        <span class="px-2 py-0.5 text-xs bg-blue-50 text-blue-700 rounded-full">منطقه {{ $district }}</span>
                        @empty
                        <span class="text-xs text-gray-400">—</span>
                        @endforelse
                    </dd>
                </div>
                @if(!empty($registration->tehran_province_cities))
                <div>
                    <dt class="text-xs text-gray-500 mb-1">شهرهای استان تهران</dt>
                    <dd class="flex flex-wrap gap-1">
                        @foreach($registration->tehran_province_cities as $city)
                        <span class="px-2 py-0.5 text-xs bg-blue-50 text-blue-700 rounded-full">{{ $city }}</span>
                        @endforeach
                    </dd>
                </div>
                @endif
                @if(!empty($registration->alborz_cities))
                <div>
                    <dt class="text-xs text-gray-500 mb-1">شهرهای استان البرز</dt>
                    <dd class="flex flex-wrap gap-1">
                        @foreach($registration->alborz_cities as $city)
                        <span class="px-2 py-0.5 text-xs bg-blue-50 text-blue-700 rounded-full">{{ $city }}</span>
                        @endforeach
                    </dd>
                </div>
                @endif
                @if($registration->other_provinces_cities)
                <div>
                    <dt class="text-xs text-gray-500 mb-1">سایر استان‌ها</dt>
                    <dd class="text-sm text-gray-800">{{ $registration->other_provinces_cities }}</dd>
                </div>
                @endif
            </dl>
        </div>

        {{-- زمینه فعالیت --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-bold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                زمینه فعالیت
            </h2>
            <dl class="space-y-3">
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">نوع فعالیت</dt>
                    <dd class="text-sm font-medium text-gray-800">{{ $activityLabels[$registration->activity_type] ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">نحوه ارائه خدمات</dt>
                    <dd class="text-sm font-medium text-gray-800">{{ $transportLabels[$registration->transportation_method] ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 mb-1">دستگاه‌ها</dt>
                    <dd class="flex flex-wrap gap-1">
                        @forelse($applianceNames as $name)
                        <span class="px-2 py-0.5 text-xs bg-amber-50 text-amber-700 rounded-full">{{ $name }}</span>
                        @empty
                        <span class="text-xs text-gray-400">—</span>
                        @endforelse
                    </dd>
                </div>
            </dl>
        </div>

    </div>

</div>
@endsection
