@extends('layouts.admin')

@section('page-title', 'تنظیمات ظاهری پنل تکنسین')

@php
    $imageFields = [
        [
            'key'         => 'tech_panel_logo',
            'label'       => 'لوگو / آیکون PWA',
            'description' => 'آیکون اپلیکیشن وقتی کاربر پنل را به صفحهٔ خانه اضافه می‌کند. مربعی، ترجیحاً ۵۱۲×۵۱۲.',
            'preview'     => 'square',
            'aspect'      => 'aspect-square w-20',
            'accept'      => '.jpg,.jpeg,.png,.gif,.svg,.webp',
            'hint'        => 'فرمت‌ها: JPG, PNG, GIF, SVG, WebP — حداکثر ۲ مگابایت.',
        ],
        [
            'key'         => 'tech_panel_banner',
            'label'       => 'بنر داشبورد ("کاربلدهای تعمیر آنلاین")',
            'description' => 'تصویری که در داشبورد بالا پایین کارت‌ها نشان داده می‌شود. نسبت ۱۶:۹.',
            'preview'     => 'wide',
            'aspect'      => 'aspect-video w-48',
            'accept'      => '.jpg,.jpeg,.png,.gif,.webp',
            'hint'        => 'فرمت‌ها: JPG, PNG, GIF, WebP — حداکثر ۵ مگابایت.',
        ],
        [
            'key'         => 'tech_panel_hero',
            'label'       => 'تصویر صفحه ورود (Hero)',
            'description' => 'تصویری که در بالای صفحه لاگین تکنسین نمایش داده می‌شود.',
            'preview'     => 'wide',
            'aspect'      => 'aspect-[3/2] w-48',
            'accept'      => '.jpg,.jpeg,.png,.gif,.webp',
            'hint'        => 'فرمت‌ها: JPG, PNG, GIF, WebP — حداکثر ۵ مگابایت.',
        ],
    ];
@endphp

@section('main')
<div class="max-w-3xl mx-auto">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">تنظیمات ظاهری پنل تکنسین</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                این تصاویر و متن‌ها در پنل تکنسین (آدرس <span dir="ltr">/tech</span>) برای همهٔ تکنسین‌ها نمایش داده می‌شود.
            </p>
        </div>

        @if(session('success'))
            <div class="mx-6 mt-6 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mx-6 mt-6 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg text-sm">
                @foreach($errors->all() as $err)
                    <div>• {{ $err }}</div>
                @endforeach
            </div>
        @endif

        {{-- ─── فرم متن‌ها ─── (بدون فایل، سبک) --}}
        <form action="{{ route('crm.tech-panel-settings.update') }}" method="POST" class="p-6 space-y-5 border-b border-gray-100 dark:border-gray-700">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">نام نمایشی پنل</label>
                <input type="text" name="tech_panel_name"
                       value="{{ old('tech_panel_name', $settings['tech_panel_name']) }}"
                       placeholder="تعمیرآنلاین"
                       class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-brand-500">
                <p class="text-xs text-gray-400 mt-1">روی هدر پنل، تب مرورگر و آیکون PWA دیده می‌شود.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">شماره پشتیبانی</label>
                <input type="tel" name="tech_panel_support_phone" dir="ltr"
                       value="{{ old('tech_panel_support_phone', $settings['tech_panel_support_phone']) }}"
                       placeholder="02112345678"
                       class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-brand-500">
                <p class="text-xs text-gray-400 mt-1">دکمهٔ تماس بالای داشبورد تکنسین به این شماره می‌رود. خالی بگذارید تا دکمه نمایش داده نشود.</p>
            </div>

            <button type="submit"
                    class="px-6 py-2.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-bold text-sm transition">
                ذخیره متن‌ها
            </button>
        </form>

        {{-- ─── هر تصویر، یک فرم مستقل ───
             قبلاً همهٔ فایل‌ها در یک فرم می‌رفتند که اگر مجموع حجم بیشتر
             از post_max_size PHP می‌شد (پیش‌فرض ۸MB) درخواست ساکت رد
             می‌شد و عکس Hero ذخیره نمی‌شد. حالا هر آپلود فرم خودش را
             دارد و بسیار سبک‌تر است. --}}
        @foreach($imageFields as $field)
            <div class="px-6 py-6 {{ ! $loop->last ? 'border-b border-gray-100 dark:border-gray-700' : '' }}">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    {{ $field['label'] }}
                </label>
                <p class="text-xs text-gray-500 mb-3 leading-6">{{ $field['description'] }}</p>

                <div class="flex items-start gap-4">
                    @if($settings[$field['key']])
                        @php
                            // از روت Laravel استفاده می‌کنیم چون symlink
                            // استوریج روی هاست‌های cPanel/LiteSpeed ۴۰۴ می‌دهد.
                            // cache-bust با timestamp فعلی تا بعد از آپلود
                            // فوراً نسخهٔ جدید را ببینیم.
                            $imgUrl = route('crm.tech-panel-settings.serve', $field['key']) . '?v=' . time();
                        @endphp
                        <div class="relative flex-shrink-0">
                            <img src="{{ $imgUrl }}"
                                 alt="{{ $field['label'] }}"
                                 class="{{ $field['aspect'] }} object-cover rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700">
                            <form action="{{ route('crm.tech-panel-settings.delete-image', $field['key']) }}"
                                  method="POST" class="absolute -top-2 -right-2"
                                  onsubmit="return confirm('این تصویر حذف شود؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    @endif

                    <form action="{{ route('crm.tech-panel-settings.update') }}" method="POST" enctype="multipart/form-data"
                          class="flex-1 min-w-0 flex items-end gap-3">
                        @csrf
                        <div class="flex-1 min-w-0">
                            <input type="file" name="{{ $field['key'] }}" accept="{{ $field['accept'] }}" required
                                   class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 dark:file:bg-brand-900/30 dark:file:text-brand-300">
                            <p class="text-xs text-gray-400 mt-1">{{ $field['hint'] }}</p>
                        </div>
                        <button type="submit"
                                class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm transition whitespace-nowrap">
                            آپلود
                        </button>
                    </form>
                </div>
            </div>
        @endforeach

        <div class="px-6 py-5 border-t border-gray-100 dark:border-gray-700 flex items-center gap-3">
            <a href="{{ route('tech.dashboard') }}" target="_blank"
               class="px-4 py-2.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm transition">
                پیش‌نمایش پنل تکنسین
            </a>
            <a href="{{ route('tech.login') }}" target="_blank"
               class="px-4 py-2.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm transition">
                پیش‌نمایش صفحه ورود
            </a>
        </div>
    </div>
</div>
@endsection
