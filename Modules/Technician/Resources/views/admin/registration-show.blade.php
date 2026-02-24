@extends('layouts.admin')
@section('page-title', 'جزئیات درخواست - ' . $registration->first_name . ' ' . $registration->last_name)

@php
    $statusLabels = ['incomplete' => 'ناقص', 'pending' => 'در انتظار بررسی', 'approved' => 'تایید شده', 'rejected' => 'رد شده'];
    $statusColors = ['incomplete' => 'gray', 'pending' => 'amber', 'approved' => 'green', 'rejected' => 'red'];
    $activityLabels = ['install' => 'نصب', 'repair' => 'تعمیر', 'install_repair' => 'نصب و تعمیر'];
    $transportLabels = ['motorcycle' => 'موتور', 'car' => 'خودرو', 'none' => 'وسیله نقلیه ندارم'];
    $repairSkillLabels = ['board_repair' => 'تعمیر برد و قطعات', 'parts_only' => 'صرفاً تعویض قطعات', 'both' => 'هر دو'];
    $boardExpLabels = ['none' => 'تجربه‌ای ندارم', 'beginner' => 'مبتدی (کمتر از ۱ سال)', 'intermediate' => 'متوسط (۱ تا ۳ سال)', 'advanced' => 'حرفه‌ای (۳ تا ۵ سال)', 'expert' => 'متخصص (بیش از ۵ سال)'];
    $educationLabels = ['below_diploma' => 'زیر دیپلم', 'diploma' => 'دیپلم', 'associate' => 'کاردانی', 'bachelor' => 'کارشناسی', 'master' => 'کارشناسی ارشد', 'doctorate' => 'دکتری'];
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

    {{-- دلیل رد --}}
    @if($registration->status === 'rejected' && $registration->rejection_reason)
    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <div class="flex items-start gap-2">
            <svg class="w-5 h-5 text-red-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
            <div>
                <h3 class="text-sm font-bold text-red-700">دلیل رد درخواست</h3>
                <p class="text-sm text-red-600 mt-1">{{ $registration->rejection_reason }}</p>
            </div>
        </div>
    </div>
    @endif

    {{-- تغییر وضعیت --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5" x-data="{ showRejectForm: false }">
        <h2 class="text-sm font-bold text-gray-800 mb-3">تغییر وضعیت</h2>

        @if($errors->has('rejection_reason'))
        <div class="bg-red-50 border border-red-200 text-red-600 px-3 py-2 rounded-lg text-xs mb-3">
            {{ $errors->first('rejection_reason') }}
        </div>
        @endif

        <div class="flex gap-2">
            @if($registration->status !== 'pending')
            <form method="POST" action="{{ route('technician.admin.registrations.update-status', $registration->id) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="status" value="pending">
                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-amber-100 text-amber-700 hover:bg-amber-200">
                    در انتظار بررسی
                </button>
            </form>
            @endif

            @if($registration->status !== 'approved')
            <form method="POST" action="{{ route('technician.admin.registrations.update-status', $registration->id) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="status" value="approved">
                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-green-100 text-green-700 hover:bg-green-200">
                    تایید
                </button>
            </form>
            @endif

            @if($registration->status !== 'rejected')
            <button @click="showRejectForm = !showRejectForm" type="button"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-red-100 text-red-700 hover:bg-red-200">
                رد
            </button>
            @endif
        </div>

        {{-- فرم دلیل رد --}}
        <div x-show="showRejectForm" x-transition class="mt-4 pt-4 border-t border-gray-100">
            <form method="POST" action="{{ route('technician.admin.registrations.update-status', $registration->id) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="status" value="rejected">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">دلیل رد درخواست:</label>
                <textarea name="rejection_reason" rows="3" required placeholder="دلیل رد درخواست را وارد کنید..."
                          class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-red-400 focus:ring-1 focus:ring-red-400 outline-none resize-none">{{ old('rejection_reason') }}</textarea>
                <div class="flex gap-2 mt-3">
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors">
                        تایید رد درخواست
                    </button>
                    <button @click="showRejectForm = false" type="button" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
                        انصراف
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- تغییر مرحله ثبت‌نام --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            مرحله فعلی ثبت‌نام
        </h2>
        <form method="POST" action="{{ route('technician.admin.registrations.update-step', $registration->id) }}" class="flex items-end gap-3">
            @csrf
            @method('PUT')
            <div class="flex-1">
                <select name="current_step" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-indigo-400 focus:ring-1 focus:ring-indigo-400 outline-none">
                    @php
                        $stepOptions = [
                            1 => '۱- احراز هویت',
                            2 => '۲- اطلاعات شخصی',
                            3 => '۳- اطلاعات تکمیلی',
                            4 => '۴- حوزه فعالیت',
                            5 => '۵- مناطق تحت پوشش',
                            6 => '۶- تکمیل (منتظر بررسی)',
                            7 => '۷- احراز هویت ویدیویی',
                            8 => '۸- امضای قرارداد',
                            9 => '۹- آپلود مدارک',
                        ];
                    @endphp
                    @foreach($stepOptions as $value => $label)
                        <option value="{{ $value }}" @if($registration->current_step == $value) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors whitespace-nowrap">
                تغییر مرحله
            </button>
        </form>
        <p class="text-xs text-gray-400 mt-2">
            مرحله فعلی: <span class="font-bold text-gray-600">{{ $stepOptions[$registration->current_step] ?? $registration->current_step }}</span>
            @if($registration->contract_signed_at)
                | <span class="text-green-600 font-bold">قرارداد امضا شده</span>
            @endif
            @if($registration->documents_uploaded)
                | <span class="text-green-600 font-bold">مدارک آپلود شده</span>
            @endif
            @if($registration->biometric_status)
                | <span class="font-bold {{ $registration->biometric_status === 'verified' ? 'text-green-600' : ($registration->biometric_status === 'rejected' ? 'text-red-600' : 'text-amber-600') }}">
                    احراز هویت ویدیویی: {{ $registration->biometric_status === 'verified' ? 'تایید شده' : ($registration->biometric_status === 'rejected' ? 'رد شده' : 'در حال بررسی') }}
                </span>
            @endif
        </p>
        @if($errors->has('current_step'))
        <p class="text-red-500 text-xs mt-1">{{ $errors->first('current_step') }}</p>
        @endif
    </div>

    {{-- تنظیمات مالی قرارداد (اختصاصی هر تکنسین) --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5" x-data="{
        saving: false,
        saved: false,
        async save() {
            this.saving = true;
            this.saved = false;
            try {
                const res = await fetch('{{ route('technician.admin.registrations.update-contract-fields', $registration->id) }}', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        commission_percent: this.$refs.commission.value || null,
                        promissory_note_amount: this.$refs.promissory.value || null
                    })
                });
                const data = await res.json();
                if (data.success) this.saved = true;
            } catch(e) { }
            this.saving = false;
            setTimeout(() => this.saved = false, 3000);
        }
    }">
        <h2 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            تنظیمات مالی قرارداد (اختصاصی)
        </h2>
        <p class="text-xs text-gray-400 mb-3">اگر خالی باشد، مقادیر پیش‌فرض از تنظیمات عمومی استفاده می‌شود.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">درصد کارمزد</label>
                <div class="relative">
                    <input type="number" x-ref="commission" value="{{ $registration->commission_percent }}"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 outline-none"
                           min="0" max="100" step="0.01" placeholder="پیش‌فرض عمومی">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">%</span>
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">مبلغ سفته (ریال)</label>
                <input type="text" x-ref="promissory" value="{{ $registration->promissory_note_amount }}"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 outline-none"
                       placeholder="پیش‌فرض عمومی">
            </div>
        </div>
        <div class="flex items-center gap-3 mt-3">
            <button @click="save()" :disabled="saving" type="button"
                    class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition-colors disabled:opacity-50 whitespace-nowrap">
                <span x-show="!saving">ذخیره</span>
                <span x-show="saving">در حال ذخیره...</span>
            </button>
            <span x-show="saved" x-transition class="text-xs text-green-600 font-medium">ذخیره شد</span>
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
                    <dt class="text-xs text-gray-500">سریال کارت ملی</dt>
                    <dd class="text-sm font-medium text-gray-800" dir="ltr">{{ $registration->national_card_serial ?? '—' }}</dd>
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
                @if($registration->marital_status === 'married')
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">تعداد فرزندان</dt>
                    <dd class="text-sm font-medium text-gray-800">{{ $registration->children_count ?? 0 }}</dd>
                </div>
                @endif
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
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">همکاری با شرکت‌ها</dt>
                    <dd class="text-sm font-medium text-gray-800">{{ $registration->has_cooperation ? 'بله' : 'خیر' }}</dd>
                </div>
                @if($registration->has_cooperation && $registration->cooperation_companies)
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">نام شرکت‌ها</dt>
                    <dd class="text-sm font-medium text-gray-800 text-left max-w-[200px]">{{ $registration->cooperation_companies }}</dd>
                </div>
                @endif
                @if($registration->referrer_name)
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">معرف</dt>
                    <dd class="text-sm font-medium text-gray-800">{{ $registration->referrer_name }} @if($registration->referrer_phone)<span class="text-gray-400 mx-1">|</span><span dir="ltr">{{ $registration->referrer_phone }}</span>@endif</dd>
                </div>
                @endif
                @if($registration->colleague1_name)
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">هم‌صنفی اول</dt>
                    <dd class="text-sm font-medium text-gray-800">{{ $registration->colleague1_name }} @if($registration->colleague1_phone)<span class="text-gray-400 mx-1">|</span><span dir="ltr">{{ $registration->colleague1_phone }}</span>@endif</dd>
                </div>
                @endif
                @if($registration->colleague2_name)
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">هم‌صنفی دوم</dt>
                    <dd class="text-sm font-medium text-gray-800">{{ $registration->colleague2_name }} @if($registration->colleague2_phone)<span class="text-gray-400 mx-1">|</span><span dir="ltr">{{ $registration->colleague2_phone }}</span>@endif</dd>
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
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">نوع تعمیرات</dt>
                    <dd class="text-sm font-medium text-gray-800">{{ $repairSkillLabels[$registration->repair_skill] ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">سابقه تعمیر برد</dt>
                    <dd class="text-sm font-medium text-gray-800">{{ $boardExpLabels[$registration->board_repair_experience] ?? '—' }}</dd>
                </div>
                @if($registration->additional_notes)
                <div>
                    <dt class="text-xs text-gray-500 mb-1">توضیحات تکمیلی</dt>
                    <dd class="text-sm text-gray-800">{{ $registration->additional_notes }}</dd>
                </div>
                @endif
            </dl>
        </div>

    </div>

    {{-- مدارک آپلود شده --}}
    @if($registration->documents_uploaded)
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-bold text-gray-800 mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            مدارک آپلود شده
        </h2>
        @php
            $documents = [
                ['field' => 'doc_national_card_front', 'label' => 'روی کارت ملی'],
                ['field' => 'doc_national_card_back', 'label' => 'پشت کارت ملی'],
                ['field' => 'doc_birth_certificate_p1', 'label' => 'شناسنامه صفحه ۱'],
                ['field' => 'doc_birth_certificate_p2', 'label' => 'شناسنامه صفحه ۲'],
                ['field' => 'doc_criminal_record', 'label' => 'گواهی سوپیشینه'],
                ['field' => 'doc_photo_3x4', 'label' => 'عکس ۳×۴'],
                ['field' => 'doc_lease_agreement', 'label' => 'اجاره‌نامه'],
                ['field' => 'doc_utility_bill', 'label' => 'قبض آب/برق'],
            ];
        @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach($documents as $doc)
                <div class="text-center">
                    <p class="text-xs font-semibold text-gray-600 mb-1.5">{{ $doc['label'] }}</p>
                    @if($registration->{$doc['field']})
                        <a href="{{ asset('storage/' . $registration->{$doc['field']}) }}" target="_blank" class="block group">
                            <div class="aspect-[3/4] bg-gray-100 rounded-lg overflow-hidden border border-gray-200 group-hover:border-blue-400 transition-colors">
                                <img src="{{ asset('storage/' . $registration->{$doc['field']}) }}" alt="{{ $doc['label'] }}" class="w-full h-full object-cover">
                            </div>
                            <span class="text-xs text-blue-500 mt-1 inline-block group-hover:underline">مشاهده</span>
                        </a>
                    @else
                        <div class="aspect-[3/4] bg-gray-50 rounded-lg border border-dashed border-gray-200 flex items-center justify-center">
                            <span class="text-xs text-gray-300">آپلود نشده</span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- اطلاعات قرارداد و بایومتریک --}}
    @if($registration->contract_signed_at || $registration->biometric_status)
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- قرارداد --}}
        @if($registration->contract_signed_at)
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-bold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                اطلاعات قرارداد
            </h2>
            <dl class="space-y-3">
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">وضعیت</dt>
                    <dd class="text-sm font-bold text-green-600">امضا شده</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">تاریخ امضا</dt>
                    <dd class="text-sm font-medium text-gray-800" dir="ltr">{{ $registration->contract_signed_at->format('Y/m/d H:i') }}</dd>
                </div>
                @if($registration->contract_signature)
                <div>
                    <dt class="text-xs text-gray-500 mb-2">امضای دیجیتال</dt>
                    <dd class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                        <img src="{{ $registration->contract_signature }}" alt="امضا" class="max-h-24 mx-auto">
                    </dd>
                </div>
                @endif
            </dl>
        </div>
        @endif

        {{-- بایومتریک --}}
        @if($registration->biometric_status)
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-bold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                احراز هویت ویدیویی
            </h2>
            <dl class="space-y-3">
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">وضعیت</dt>
                    <dd>
                        @if($registration->biometric_status === 'verified')
                            <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-green-100 text-green-700">تایید شده</span>
                        @elseif($registration->biometric_status === 'rejected')
                            <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-red-100 text-red-700">رد شده</span>
                        @else
                            <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-amber-100 text-amber-700">در حال بررسی</span>
                        @endif
                    </dd>
                </div>
                @if($registration->national_card_serial)
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">سریال کارت ملی</dt>
                    <dd class="text-sm font-medium text-gray-800" dir="ltr">{{ $registration->national_card_serial }}</dd>
                </div>
                @endif
                @if($registration->biometric_session_id)
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">شناسه نشست</dt>
                    <dd class="text-xs font-mono text-gray-600" dir="ltr">{{ $registration->biometric_session_id }}</dd>
                </div>
                @endif
                @if($registration->biometric_verified_at)
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">تاریخ تایید</dt>
                    <dd class="text-sm font-medium text-gray-800" dir="ltr">{{ $registration->biometric_verified_at->format('Y/m/d H:i') }}</dd>
                </div>
                @endif
                @if($registration->biometric_status === 'rejected' && $registration->biometric_reject_reason)
                <div class="flex justify-between">
                    <dt class="text-xs text-gray-500">دلیل رد</dt>
                    <dd class="text-sm font-medium text-red-600">{{ $registration->biometric_reject_reason }}</dd>
                </div>
                @endif
            </dl>
        </div>
        @endif

    </div>
    @endif

</div>
@endsection
