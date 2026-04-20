@extends('layouts.admin')
@section('page-title', 'تنظیمات صفحه جذب تکنسین')

@section('main')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">تنظیمات صفحه جذب تکنسین</h1>
            <p class="text-sm text-gray-500 mt-1">محتوای صفحه عمومی را از اینجا مدیریت کنید.</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('technician.landing') }}" target="_blank"
               class="flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                مشاهده صفحه
            </a>
            <form method="POST" action="{{ route('technician.admin.settings.reset') }}"
                  onsubmit="return confirm('آیا از بازنشانی به مقادیر پیش‌فرض مطمئنید؟')">
                @csrf
                <button type="submit"
                        class="flex items-center gap-2 px-4 py-2 bg-amber-100 text-amber-800 rounded-lg text-sm font-medium hover:bg-amber-200 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    بازنشانی
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 flex items-center gap-2">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3">
        <ul class="list-disc list-inside text-sm space-y-1">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('technician.admin.settings.update') }}" enctype="multipart/form-data" x-data="{ activeTab: 'hero' }">
        @csrf
        @method('PUT')

        {{-- Tabs --}}
        <div class="flex gap-1 bg-gray-100 p-1 rounded-xl mb-6 overflow-x-auto">
            @foreach([
                ['hero', 'هیرو'],
                ['benefits', 'مزایا'],
                ['steps', 'مراحل'],
                ['requirements', 'شرایط'],
                ['faq', 'سوالات'],
                ['cta', 'دعوت به عمل'],
                ['contract', 'قرارداد'],
                ['sms', 'پیامک'],
                ['general', 'عمومی'],
            ] as [$tab, $label])
            <button type="button" @click="activeTab = '{{ $tab }}'"
                    :class="activeTab === '{{ $tab }}' ? 'bg-white shadow text-gray-900 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="px-4 py-2 rounded-lg text-sm transition-all whitespace-nowrap flex-shrink-0">
                {{ $label }}
            </button>
            @endforeach
        </div>

        {{-- TAB: هیرو --}}
        <div x-show="activeTab === 'hero'" class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
                <h2 class="font-bold text-gray-800 text-base border-b pb-3">بخش هیرو (بالای صفحه)</h2>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">بج (نوار کوچک بالای عنوان)</label>
                    <input type="text" name="hero_badge" value="{{ $settings['hero_badge'] }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="مثال: جدیدترین فرصت شغلی">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">عنوان اصلی <span class="text-red-500">*</span></label>
                    <input type="text" name="hero_title" value="{{ $settings['hero_title'] }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">زیرعنوان</label>
                    <input type="text" name="hero_subtitle" value="{{ $settings['hero_subtitle'] }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">توضیحات</label>
                    <textarea name="hero_description" rows="3"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none">{{ $settings['hero_description'] }}</textarea>
                </div>
                <div class="border-t pt-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">دکمه‌های هیرو</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">متن دکمه اول</label>
                            <input type="text" name="hero_cta_text" value="{{ $settings['hero_cta_text'] }}"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">لینک دکمه اول</label>
                            <input type="text" name="hero_cta_link" value="{{ $settings['hero_cta_link'] }}"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono" dir="ltr" placeholder="#register-form">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">متن دکمه دوم</label>
                            <input type="text" name="hero_secondary_text" value="{{ $settings['hero_secondary_text'] }}"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="خالی = مخفی">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">لینک دکمه دوم</label>
                            <input type="text" name="hero_secondary_link" value="{{ $settings['hero_secondary_link'] }}"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono" dir="ltr" placeholder="#how-it-works">
                        </div>
                    </div>
                </div>
            </div>

            {{-- تصویر پس‌زمینه + اورلی --}}
            <div class="bg-white rounded-xl shadow-sm p-6 space-y-5">
                <h2 class="font-bold text-gray-800 text-base border-b pb-3">تصویر پس‌زمینه و اورلی</h2>

                {{-- آپلود تصویر --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">تصویر پس‌زمینه هیرو</label>
                    <div class="flex items-start gap-4">
                        @if($settings['hero_bg_image'])
                        <div class="relative flex-shrink-0">
                            <img src="{{ asset('storage/' . $settings['hero_bg_image']) }}" alt="Hero BG"
                                 class="h-24 w-40 object-cover rounded-lg border border-gray-200">
                            <a href="{{ route('technician.admin.settings.delete-hero-bg') }}"
                               onclick="return confirm('تصویر پس‌زمینه حذف شود؟')"
                               class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </a>
                        </div>
                        @endif
                        <div class="flex-1">
                            <input type="file" name="hero_bg_image" accept="image/*"
                                   class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            <p class="text-xs text-gray-400 mt-1">JPG, PNG, WebP — حداکثر 5MB — اگه نذاری gradient پیش‌فرض نمایش داده میشه</p>
                        </div>
                    </div>
                </div>

                {{-- رنگ اورلی + شفافیت --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4"
                     x-data="{ opacity: {{ $settings['hero_overlay_opacity'] ?? 60 }} }">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">رنگ اورلی</label>
                        <div class="flex items-center gap-3">
                            <input type="color" name="hero_overlay_color"
                                   value="{{ $settings['hero_overlay_color'] ?? '#0f2a4a' }}"
                                   class="h-10 w-16 rounded-lg border border-gray-300 cursor-pointer p-0.5">
                            <span class="text-sm text-gray-500">رنگ لایه روی تصویر</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            شفافیت اورلی — <span x-text="opacity + '%'" class="text-blue-600 font-semibold"></span>
                        </label>
                        <input type="range" name="hero_overlay_opacity"
                               min="0" max="90" step="5"
                               x-model="opacity"
                               value="{{ $settings['hero_overlay_opacity'] ?? 60 }}"
                               class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-blue-600">
                        <div class="flex justify-between text-xs text-gray-400 mt-1">
                            <span>شفاف‌تر</span>
                            <span>تیره‌تر</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- TAB: مزایا --}}
        <div x-show="activeTab === 'benefits'" class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
                <h2 class="font-bold text-gray-800 text-base border-b pb-3">بخش مزایا</h2>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">عنوان بخش</label>
                    <input type="text" name="benefits_title" value="{{ $settings['benefits_title'] }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        لیست مزایا
                        <span class="text-xs text-gray-400 mr-2">فرمت JSON — هر آیتم: {"icon": "money|clock|map|support|growth|tools", "title": "...", "description": "..."}</span>
                    </label>
                    <textarea name="benefits_json" rows="12"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-y" dir="ltr">{{ $settings['benefits_json'] }}</textarea>
                </div>
            </div>
        </div>

        {{-- TAB: مراحل --}}
        <div x-show="activeTab === 'steps'" class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
                <h2 class="font-bold text-gray-800 text-base border-b pb-3">بخش "چطور شروع کنی؟"</h2>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">عنوان بخش</label>
                    <input type="text" name="steps_title" value="{{ $settings['steps_title'] }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        مراحل
                        <span class="text-xs text-gray-400 mr-2">فرمت JSON — هر آیتم: {"number": "۱", "title": "...", "description": "..."}</span>
                    </label>
                    <textarea name="steps_json" rows="10"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-y" dir="ltr">{{ $settings['steps_json'] }}</textarea>
                </div>
            </div>
        </div>

        {{-- TAB: شرایط --}}
        <div x-show="activeTab === 'requirements'" class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
                <h2 class="font-bold text-gray-800 text-base border-b pb-3">بخش شرایط همکاری</h2>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">عنوان بخش</label>
                    <input type="text" name="requirements_title" value="{{ $settings['requirements_title'] }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        لیست شرایط
                        <span class="text-xs text-gray-400 mr-2">فرمت JSON — آرایه‌ای از رشته‌ها: ["شرط اول", "شرط دوم", ...]</span>
                    </label>
                    <textarea name="requirements_json" rows="8"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-y" dir="ltr">{{ $settings['requirements_json'] }}</textarea>
                </div>
            </div>
        </div>

        {{-- TAB: سوالات --}}
        <div x-show="activeTab === 'faq'" class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
                <h2 class="font-bold text-gray-800 text-base border-b pb-3">سوالات متداول (FAQ)</h2>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">عنوان بخش</label>
                    <input type="text" name="faq_title" value="{{ $settings['faq_title'] }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        سوالات و جواب‌ها
                        <span class="text-xs text-gray-400 mr-2">فرمت JSON — هر آیتم: {"question": "...", "answer": "..."}</span>
                    </label>
                    <textarea name="faq_json" rows="12"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-y" dir="ltr">{{ $settings['faq_json'] }}</textarea>
                </div>
            </div>
        </div>

        {{-- TAB: دعوت به عمل --}}
        <div x-show="activeTab === 'cta'" class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
                <h2 class="font-bold text-gray-800 text-base border-b pb-3">محتوای بخش CTA</h2>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">بج (نوار کوچک بالای عنوان)</label>
                    <input type="text" name="cta_badge" value="{{ $settings['cta_badge'] }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="مثال: در حال پذیرش تکنسین">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">عنوان</label>
                    <input type="text" name="cta_title" value="{{ $settings['cta_title'] }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">توضیحات</label>
                    <textarea name="cta_description" rows="2"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none">{{ $settings['cta_description'] }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">متن پاورقی (زیر دکمه‌ها)</label>
                    <input type="text" name="cta_footnote" value="{{ $settings['cta_footnote'] }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="مثال: پاسخگویی ۲۴ ساعته · بدون نیاز به قرار قبلی">
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
                <h2 class="font-bold text-gray-800 text-base border-b pb-3">دکمه اول</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">متن دکمه</label>
                        <input type="text" name="cta_button_text" value="{{ $settings['cta_button_text'] }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">لینک دکمه</label>
                        <input type="text" name="cta_button_link" value="{{ $settings['cta_button_link'] }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono" dir="ltr" placeholder="mailto:... یا https://...">
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
                <h2 class="font-bold text-gray-800 text-base border-b pb-3">دکمه دوم (تلفن)</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">متن دکمه</label>
                        <input type="text" name="cta_phone_text" value="{{ $settings['cta_phone_text'] }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">شماره تلفن</label>
                        <input type="text" name="cta_phone" value="{{ $settings['cta_phone'] }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono" dir="ltr" placeholder="02100000000">
                        <p class="text-xs text-gray-400 mt-1">بدون tel: — فقط شماره</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- TAB: عمومی --}}
        <div x-show="activeTab === 'general'" class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
                <h2 class="font-bold text-gray-800 text-base border-b pb-3">تنظیمات عمومی</h2>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">عنوان صفحه (title tag)</label>
                    <input type="text" name="page_title" value="{{ $settings['page_title'] }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نام برند (لوگو و فوتر)</label>
                    <input type="text" name="brand_name" value="{{ $settings['brand_name'] }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">لوگوی صفحه عمومی</label>
                    <div class="flex items-start gap-4">
                        @if($settings['brand_logo'])
                            <div class="relative">
                                <img src="{{ asset('storage/' . $settings['brand_logo']) }}" alt="Logo" class="h-16 max-w-[160px] object-contain rounded-lg border border-gray-200 bg-gray-50 p-1">
                                <a href="{{ route('technician.admin.settings.delete-logo') }}"
                                   onclick="return confirm('آیا مطمئن هستید؟')"
                                   class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </a>
                            </div>
                        @endif
                        <div class="flex-1">
                            <input type="file" name="brand_logo" accept="image/*"
                                   class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            <p class="text-xs text-gray-400 mt-1">فرمت‌های مجاز: JPG, PNG, SVG, WebP (حداکثر 2MB) — اگه خالی بذاری، نام برند نمایش داده میشه</p>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-600">
                    <p class="font-medium text-gray-800 mb-2">لینک صفحه عمومی:</p>
                    <code class="bg-gray-100 px-2 py-1 rounded text-xs" dir="ltr">{{ url('/join-technician') }}</code>
                    <a href="{{ route('technician.landing') }}" target="_blank"
                       class="mr-3 text-blue-600 hover:underline text-xs">باز کردن ←</a>
                </div>
            </div>
        </div>

        {{-- TAB: قرارداد --}}
        <div x-show="activeTab === 'contract'" class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
                <h2 class="font-bold text-gray-800 text-base border-b pb-3">متن قرارداد همکاری</h2>
                <p class="text-xs text-gray-500">متن قرارداد را با استفاده از ویرایشگر زیر تنظیم کنید. این متن پس از تأیید تکنسین به او نمایش داده می‌شود.</p>
                <div>
                    <textarea name="contract_text" class="rich-editor">{!! $settings['contract_text'] ?? '' !!}</textarea>
                </div>
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                    <p class="text-xs text-amber-700 font-semibold mb-1">متغیرهای قابل استفاده در متن قرارداد:</p>
                    <div class="flex flex-wrap gap-2 text-xs text-amber-600" dir="ltr">
                        <code class="bg-amber-100 px-1.5 py-0.5 rounded">{gender_title}</code>
                        <code class="bg-amber-100 px-1.5 py-0.5 rounded">{name}</code>
                        <code class="bg-amber-100 px-1.5 py-0.5 rounded">{father_name}</code>
                        <code class="bg-amber-100 px-1.5 py-0.5 rounded">{national_code}</code>
                        <code class="bg-amber-100 px-1.5 py-0.5 rounded">{address}</code>
                        <code class="bg-amber-100 px-1.5 py-0.5 rounded">{mobile}</code>
                        <code class="bg-amber-100 px-1.5 py-0.5 rounded">{date}</code>
                        <code class="bg-amber-100 px-1.5 py-0.5 rounded">{commission_percent}</code>
                        <code class="bg-amber-100 px-1.5 py-0.5 rounded">{promissory_note_amount}</code>
                        <code class="bg-amber-100 px-1.5 py-0.5 rounded">{contract_number}</code>
                    </div>
                    <p class="text-xs text-amber-600 mt-2">
                        <strong>توضیح:</strong>
                        gender_title = آقای/خانم |
                        father_name = نام پدر |
                        address = استان، شهر |
                        date = تاریخ شمسی |
                        commission_percent = درصد کارمزد |
                        promissory_note_amount = مبلغ سفته (با فرمت عددی)
                    </p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
                <h2 class="font-bold text-gray-800 text-base border-b pb-3">تنظیمات مالی پیش‌فرض قرارداد</h2>
                <p class="text-xs text-gray-500">مقادیر پیش‌فرض برای همه تکنسین‌ها. در صورت نیاز می‌توانید برای هر تکنسین مقدار اختصاصی تنظیم کنید.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">درصد کارمزد پیش‌فرض</label>
                        <div class="relative">
                            <input type="number" name="default_commission_percent" value="{{ $settings['default_commission_percent'] ?? '' }}"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   min="0" max="100" step="0.01" placeholder="مثلاً: 30">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">%</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">درصد سهم تعمیرکار از هر فاکتور</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">مبلغ سفته پیش‌فرض (ریال)</label>
                        <input type="text" name="default_promissory_note_amount" value="{{ $settings['default_promissory_note_amount'] ?? '' }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="مثلاً: 5000000000">
                        <p class="text-xs text-gray-400 mt-1">مبلغ سفته ضمانت به ریال</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
                <h2 class="font-bold text-gray-800 text-base border-b pb-3">تنظیمات پیامک قرارداد</h2>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نام تمپلیت پیامک (کاوه‌نگار)</label>
                    <input type="text" name="contract_sms_template" value="{{ $settings['contract_sms_template'] ?? '' }}" dir="ltr"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent text-left"
                           placeholder="contract-signed">
                    <p class="text-xs text-gray-400 mt-1">نام تمپلیت ثبت‌شده در پنل کاوه‌نگار برای ارسال پیامک پس از امضای قرارداد</p>
                </div>
            </div>
        </div>

        {{-- TAB: پیامک --}}
        <div x-show="activeTab === 'sms'" class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
                <h2 class="font-bold text-gray-800 text-base border-b pb-3">تنظیمات پیامک اطلاع‌رسانی</h2>
                <p class="text-xs text-gray-500">نام تمپلیت‌های ثبت‌شده در پنل کاوه‌نگار را وارد کنید. در صورت خالی بودن هر فیلد، پیامک مربوطه ارسال نمی‌شود.</p>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                    <p class="text-xs text-blue-700 font-semibold mb-1">توکن‌های قابل استفاده در تمپلیت کاوه‌نگار:</p>
                    <div class="flex flex-wrap gap-2 text-xs text-blue-600" dir="ltr">
                        <code class="bg-blue-100 px-1.5 py-0.5 rounded">token = نام و نام خانوادگی</code>
                    </div>
                    <p class="text-xs text-blue-600 mt-2">
                        در پنل کاوه‌نگار تمپلیت را با <code class="bg-blue-100 px-1 rounded" dir="ltr">%token%</code> بسازید تا نام تکنسین جایگزین شود.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-green-500"></span>
                                تمپلیت پیامک تایید درخواست
                            </span>
                        </label>
                        <input type="text" name="sms_approved_template" value="{{ $settings['sms_approved_template'] ?? '' }}" dir="ltr"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent text-left"
                               placeholder="technician-approved">
                        <p class="text-xs text-gray-400 mt-1">ارسال به تکنسین هنگام تایید درخواست ثبت‌نام توسط ادمین</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-red-500"></span>
                                تمپلیت پیامک رد درخواست
                            </span>
                        </label>
                        <input type="text" name="sms_rejected_template" value="{{ $settings['sms_rejected_template'] ?? '' }}" dir="ltr"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent text-left"
                               placeholder="technician-rejected">
                        <p class="text-xs text-gray-400 mt-1">ارسال به تکنسین هنگام رد درخواست ثبت‌نام توسط ادمین</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                تمپلیت پیامک ارسال ویدیو احراز هویت
                            </span>
                        </label>
                        <input type="text" name="sms_biometric_submitted_template" value="{{ $settings['sms_biometric_submitted_template'] ?? '' }}" dir="ltr"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent text-left"
                               placeholder="technician-biometric-submitted">
                        <p class="text-xs text-gray-400 mt-1">ارسال به تکنسین پس از آپلود ویدیو احراز هویت</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Save Button --}}
        <div class="flex justify-end pt-4">
            <button type="submit"
                    class="px-6 py-2.5 bg-blue-700 text-white font-semibold rounded-lg hover:bg-blue-800 transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                ذخیره تنظیمات
            </button>
        </div>
    </form>

</div>
@endsection
