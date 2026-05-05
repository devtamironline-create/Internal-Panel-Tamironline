@extends('crm::tech-panel.layout')

@section('title', 'پروفایل')

@php
    $displayName = trim($technician->firstname_tech ?: $technician->first_name) ?: ($technician->mobile ?? '—');
    $statusLabels = [
        'active'    => ['فعال', 'bg-emerald-100 text-emerald-800'],
        'inactive'  => ['غیرفعال', 'bg-gray-100 text-gray-700'],
        'suspended' => ['معلق', 'bg-amber-100 text-amber-800'],
    ];
    [$statusLabel, $statusBadge] = $statusLabels[$technician->status] ?? [$technician->status ?? '—', 'bg-gray-100 text-gray-700'];

    $calcLabels = [
        1 => 'بر اساس درصد کل (tech_per_of_all)',
        2 => 'بر اساس درصد ساده (percent)',
    ];
@endphp

@section('body')
<div class="min-h-screen pb-nav" style="background: #eef0f4;">
    @if(session('success'))
        <div class="mx-3 mt-3 px-4 py-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="mx-3 mt-3 px-4 py-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs leading-7">
            @foreach($errors->all() as $err)
                <div>• {{ $err }}</div>
            @endforeach
        </div>
    @endif

    {{-- ─────── Hero header ─────── --}}
    <div class="relative overflow-hidden rounded-b-[40px] pb-24"
         style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);">
        <div class="flex items-center justify-between px-5 pt-5">
            <a href="{{ route('tech.dashboard') }}"
               class="w-10 h-10 rounded-full bg-white/15 backdrop-blur flex items-center justify-center text-white border border-white/20"
               aria-label="بازگشت">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/>
                </svg>
            </a>
            <div class="text-white font-bold text-base">پروفایل من</div>
            <div class="w-10"></div>
        </div>

        <div class="px-5 mt-6 text-center">
            <div class="w-20 h-20 rounded-full bg-white/15 backdrop-blur border-2 border-white/30 mx-auto flex items-center justify-center overflow-hidden">
                @if($technician->img_personal)
                    <img src="{{ asset('storage/' . $technician->img_personal) }}" alt="avatar" class="w-full h-full object-cover">
                @else
                    <svg class="w-10 h-10 text-white/80" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                @endif
            </div>
            <div class="text-white text-lg font-bold mt-3">{{ $displayName }}</div>
            @if($technician->mobile)
                <div class="text-white/70 text-xs mt-1" dir="ltr">{{ $technician->mobile }}</div>
            @endif
        </div>
    </div>

    {{-- ─────── Identity card ─────── --}}
    <div class="relative z-10 -mt-12 mx-3 bg-white rounded-[24px] shadow-lg p-4 space-y-2.5">
        <div class="flex items-center justify-between">
            <span class="text-xs text-gray-400">وضعیت</span>
            <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 text-[11px] font-bold rounded-full {{ $statusBadge }}">{{ $statusLabel }}</span>
                @if($technician->ready_for_delivery)
                    <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-blue-100 text-blue-800">آماده تحویل</span>
                @endif
            </div>
        </div>
        @if($technician->technician_id)
            <div class="flex items-center justify-between">
                <span class="text-xs text-gray-400">کد تکنسین</span>
                <span class="text-sm text-gray-800 font-medium" dir="ltr">{{ $technician->technician_id }}</span>
            </div>
        @endif
        @if($technician->national_code)
            <div class="flex items-center justify-between">
                <span class="text-xs text-gray-400">کد ملی</span>
                <span class="text-sm text-gray-800 font-medium" dir="ltr">{{ $technician->national_code }}</span>
            </div>
        @endif
        @if($technician->specialty)
            <div class="flex items-center justify-between">
                <span class="text-xs text-gray-400">تخصص</span>
                <span class="text-sm text-gray-800 font-medium">{{ $technician->specialty }}</span>
            </div>
        @endif
        @if($technician->type_tech)
            <div class="flex items-center justify-between">
                <span class="text-xs text-gray-400">نوع تکنسین</span>
                <span class="text-sm text-gray-800 font-medium">{{ $technician->type_tech }}</span>
            </div>
        @endif
        @if($technician->province)
            <div class="flex items-center justify-between">
                <span class="text-xs text-gray-400">استان</span>
                <span class="text-sm text-gray-800 font-medium">{{ $technician->province }}</span>
            </div>
        @endif
    </div>

    {{-- ─────── Editable contact + bio ─────── --}}
    <div class="mx-3 mt-3 bg-white rounded-[24px] shadow-sm p-4">
        <div class="text-[11px] text-gray-400 mb-3">اطلاعات تماس</div>
        <form method="POST" action="{{ route('tech.profile.update') }}" class="space-y-3">
            @csrf
            <div>
                <label class="text-[11px] text-gray-500 mb-1 block">تلفن ثابت</label>
                <input type="tel" name="phone" value="{{ old('phone', $technician->phone) }}"
                       dir="ltr" inputmode="tel"
                       class="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:border-brand-400 focus:outline-none">
            </div>
            <div>
                <label class="text-[11px] text-gray-500 mb-1 block">تلفن اضطراری</label>
                <input type="tel" name="phone_force" value="{{ old('phone_force', $technician->phone_force) }}"
                       dir="ltr" inputmode="tel"
                       class="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:border-brand-400 focus:outline-none">
            </div>
            <div>
                <label class="text-[11px] text-gray-500 mb-1 block">آدرس</label>
                <textarea name="address" rows="2"
                          class="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:border-brand-400 focus:outline-none leading-7">{{ old('address', $technician->address) }}</textarea>
            </div>
            <div>
                <label class="text-[11px] text-gray-500 mb-1 block">توضیحات/بیوگرافی</label>
                <textarea name="description" rows="3"
                          class="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:border-brand-400 focus:outline-none leading-7">{{ old('description', $technician->description) }}</textarea>
            </div>
            <button type="submit"
                    class="w-full py-3 rounded-xl text-white font-bold text-sm transition"
                    style="background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);">
                ذخیره تغییرات
            </button>
        </form>
    </div>

    {{-- ─────── Financial config (read-only) ─────── --}}
    <div class="mx-3 mt-3 bg-white rounded-[24px] shadow-sm p-4 space-y-2.5">
        <div class="text-[11px] text-gray-400">قوانین مالی و کاری</div>
        <p class="text-[11px] text-gray-400 leading-6">
            این مقادیر توسط مدیر تنظیم می‌شوند و از پنل تکنسین قابل ویرایش نیستند.
        </p>
        @if($technician->percent !== null)
            <div class="flex items-center justify-between pt-1">
                <span class="text-xs text-gray-500">درصد کمیسیون</span>
                <span class="text-sm text-gray-800 font-bold">{{ number_format((int) $technician->percent) }}%</span>
            </div>
        @endif
        @if($technician->tech_per_of_all !== null)
            <div class="flex items-center justify-between">
                <span class="text-xs text-gray-500">درصد روی کل</span>
                <span class="text-sm text-gray-800">{{ number_format((int) $technician->tech_per_of_all) }}%</span>
            </div>
        @endif
        @if($technician->type_of_calc_tech)
            <div class="flex items-center justify-between">
                <span class="text-xs text-gray-500">روش محاسبه</span>
                <span class="text-xs text-gray-700">{{ $calcLabels[(int) $technician->type_of_calc_tech] ?? $technician->type_of_calc_tech }}</span>
            </div>
        @endif
        @if($technician->max_order)
            <div class="flex items-center justify-between">
                <span class="text-xs text-gray-500">حداکثر سفارش هم‌زمان</span>
                <span class="text-sm text-gray-800">{{ number_format((int) $technician->max_order) }}</span>
            </div>
        @endif
        @if($technician->max_price)
            <div class="flex items-center justify-between">
                <span class="text-xs text-gray-500">حداکثر مبلغ مجاز</span>
                <span class="text-sm text-gray-800">{{ number_format((int) $technician->max_price) }} <span class="text-[10px] text-gray-400">تومان</span></span>
            </div>
        @endif
    </div>

    {{-- ─────── Password change ─────── --}}
    <div class="mx-3 mt-3 bg-white rounded-[24px] shadow-sm p-4">
        <div class="text-[11px] text-gray-400 mb-3">تغییر رمز عبور</div>
        <form method="POST" action="{{ route('tech.profile.password') }}" class="space-y-3">
            @csrf
            <div>
                <label class="text-[11px] text-gray-500 mb-1 block">رمز عبور فعلی</label>
                <input type="password" name="current_password" required autocomplete="current-password"
                       class="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:border-brand-400 focus:outline-none">
            </div>
            <div>
                <label class="text-[11px] text-gray-500 mb-1 block">رمز عبور جدید (حداقل ۶ کاراکتر)</label>
                <input type="password" name="password" required minlength="6" autocomplete="new-password"
                       class="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:border-brand-400 focus:outline-none">
            </div>
            <div>
                <label class="text-[11px] text-gray-500 mb-1 block">تکرار رمز جدید</label>
                <input type="password" name="password_confirmation" required minlength="6" autocomplete="new-password"
                       class="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:border-brand-400 focus:outline-none">
            </div>
            <button type="submit"
                    class="w-full py-3 rounded-xl bg-gray-800 hover:bg-gray-900 text-white font-bold text-sm transition">
                تغییر رمز
            </button>
        </form>
    </div>

    {{-- ─────── Logout ─────── --}}
    <div class="mx-3 mt-3 bg-white rounded-[24px] shadow-sm p-4">
        <form method="POST" action="{{ route('tech.logout') }}">
            @csrf
            <button type="submit"
                    class="w-full py-3 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-sm transition flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                خروج از حساب
            </button>
        </form>
    </div>

    <div class="h-4"></div>
</div>

@include('crm::tech-panel._partials.bottom-nav', ['current' => 'tech.profile'])
@endsection
