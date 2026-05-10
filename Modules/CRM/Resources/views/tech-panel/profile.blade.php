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
            @php $hasAvatar = !empty($technician->img_personal); @endphp

            <div class="relative w-24 h-24 mx-auto">
                <div class="w-24 h-24 rounded-full bg-white/15 backdrop-blur border-2 border-white/30 flex items-center justify-center overflow-hidden">
                    @if(!empty($brandDefaultAvatar))
                        {{-- آواتار سراسری مدیر — اگر ست شده باشد روی همهٔ تکنسین‌ها
                             اعمال می‌شود (override بر img_personal). این رفتار درخواست
                             ادمین است: همه تصویر یکسان داشته باشند. --}}
                        <img src="{{ route('crm.tech-panel-settings.serve', 'tech_panel_default_avatar') }}" alt="avatar" class="w-full h-full object-cover">
                    @elseif($hasAvatar)
                        <img src="{{ asset('storage/' . $technician->img_personal) }}" alt="avatar" class="w-full h-full object-cover">
                    @else
                        {{-- آواتار پیش‌فرض: تعمیرکار عروسکی (SVG inline) --}}
                        <svg viewBox="0 0 96 96" class="w-full h-full">
                            <defs>
                                <linearGradient id="hat" x1="0" x2="0" y1="0" y2="1">
                                    <stop offset="0" stop-color="#fbbf24"/>
                                    <stop offset="1" stop-color="#f59e0b"/>
                                </linearGradient>
                            </defs>
                            <circle cx="48" cy="48" r="48" fill="#1e40af"/>
                            {{-- صورت --}}
                            <circle cx="48" cy="50" r="22" fill="#fde0c8"/>
                            {{-- چشم‌ها --}}
                            <circle cx="40" cy="48" r="2.5" fill="#1e293b"/>
                            <circle cx="56" cy="48" r="2.5" fill="#1e293b"/>
                            {{-- لبخند --}}
                            <path d="M40 58 Q48 64 56 58" stroke="#1e293b" stroke-width="2" fill="none" stroke-linecap="round"/>
                            {{-- کلاه --}}
                            <path d="M24 42 Q48 14 72 42 L72 38 Q48 24 24 38 Z" fill="url(#hat)"/>
                            <rect x="24" y="40" width="48" height="4" rx="2" fill="#92400e"/>
                            {{-- آرم روی کلاه --}}
                            <circle cx="48" cy="34" r="3" fill="#fff"/>
                            {{-- بدن (لباس کار) --}}
                            <path d="M22 96 Q22 80 48 76 Q74 80 74 96 Z" fill="#1e3a8a"/>
                            <rect x="44" y="76" width="8" height="6" fill="#fde0c8"/>
                        </svg>
                    @endif
                </div>

                @if(! $hasAvatar)
                    {{-- آیکون کوچک «دوربین» روی آواتار، گوشه پایین --}}
                    <label for="avatarInput"
                           class="absolute bottom-0 right-0 w-7 h-7 rounded-full bg-white shadow-md flex items-center justify-center cursor-pointer border border-gray-200">
                        <svg class="w-4 h-4 text-brand-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </label>
                @endif
            </div>

            <div class="text-white text-lg font-bold mt-3">{{ $displayName }}</div>
            @if($technician->mobile)
                <div class="text-white/70 text-xs mt-1" dir="ltr">{{ $technician->mobile }}</div>
            @endif

            {{-- فرم آپلود (یک‌بار) — مخفی، با کلیک روی آیکون دوربین فعال می‌شود --}}
            @if(! $hasAvatar)
                <form id="avatarForm" method="POST" action="{{ route('tech.profile.avatar') }}" enctype="multipart/form-data" class="hidden">
                    @csrf
                    <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/webp"
                           onchange="document.getElementById('avatarForm').submit();">
                </form>
                <p class="text-[10px] text-white/65 mt-2 leading-6 px-4">
                    برای تغییر عکس روی آیکون دوربین بزنید. توجه: عکس فقط یک بار قابل آپلود است.
                </p>
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
        @if($technician->province)
            <div class="flex items-center justify-between">
                <span class="text-xs text-gray-400">استان</span>
                <span class="text-sm text-gray-800 font-medium">{{ $technician->province }}</span>
            </div>
        @endif
    </div>

    {{-- ─────── Training shortcut ─────── --}}
    <a href="{{ route('tech.training') }}"
       class="mx-3 mt-3 bg-white rounded-[24px] shadow-sm p-4 flex items-center gap-3 active:bg-gray-50">
        <div class="w-11 h-11 rounded-2xl bg-amber-50 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
            </svg>
        </div>
        <div class="flex-1 min-w-0">
            <div class="text-sm font-bold text-gray-900">آموزش</div>
            <div class="text-[11px] text-gray-500 mt-0.5">ویدیوهای آموزش، نکات تخصصی و راهنما</div>
        </div>
        <svg class="w-4 h-4 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>

    {{-- بخش «قوانین مالی و کاری» حذف شد به درخواست ادمین — اطلاعاتش
         در پنل ادمین قابل مدیریت است و نیازی به نمایش به تکنسین نیست. --}}


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
