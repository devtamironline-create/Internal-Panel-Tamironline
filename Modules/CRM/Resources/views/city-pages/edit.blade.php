@extends('layouts.admin')

@section('page-title', 'ویرایش صفحهٔ سئو')

@section('main')
<div class="p-6 max-w-4xl mx-auto space-y-6">
    <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
        <a href="{{ route('crm.cities.pages.index', $cityPage->city_id) }}" class="hover:text-purple-600">صفحات سئوی {{ $cityPage->city?->name }}</a>
        <span>/</span>
        <span>{{ $cityPage->typeLabel() }}</span>
    </div>

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">ویرایش صفحه</h1>
        <span class="inline-flex px-2.5 py-1 rounded-full text-xs {{ $cityPage->statusBadge() }}">{{ $cityPage->statusLabel() }}</span>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">
        <ul class="list-disc pr-5 space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form action="{{ route('crm.city-pages.update', $cityPage) }}" method="POST" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 space-y-6">
        @csrf @method('PUT')

        {{-- مسیرِ صفحه فقط‌خواندنی است: از slugِ شهر/دستگاه/برند ساخته می‌شود.
             برای تغییرِ آن، slugِ شهر (یا دستگاه/برند) را عوض کنید تا همهٔ
             صفحاتِ زیرمجموعه هماهنگ به‌روزرسانی شوند. --}}
        <div class="bg-gray-50 dark:bg-gray-700/40 rounded-lg p-3 flex items-center justify-between gap-3 flex-wrap">
            <span class="text-xs text-gray-500 dark:text-gray-400 dir-ltr text-left font-mono">/city{{ $cityPage->path }}</span>
            <div class="flex items-center gap-3">
                @if($cityPage->isPublished())
                    <a href="{{ $cityPage->publicUrl() }}" target="_blank" rel="noopener" class="text-xs text-green-600 hover:text-green-800 font-medium whitespace-nowrap">مشاهده در سایت ↗</a>
                @endif
                <a href="{{ route('crm.cities.edit', $cityPage->city_id) }}" class="text-xs text-purple-600 hover:text-purple-800 whitespace-nowrap">ویرایش slug شهر ←</a>
            </div>
        </div>

        {{-- عنوان و H1 --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">عنوان (Title سئو)</label>
                <input type="text" name="title" value="{{ old('title', $cityPage->title) }}" maxlength="255"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">تیتر صفحه (H1)</label>
                <input type="text" name="h1" value="{{ old('h1', $cityPage->h1) }}" maxlength="255"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
            </div>
        </div>

        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6 space-y-6">

            {{-- سکشن‌های فعال --}}
            @php
                $secs = old('sections_enabled', $cityPage->sections_enabled ?? []);
                $secEnabled = fn (string $k, bool $default = true) => array_key_exists($k, (array) $secs)
                    ? (bool) $secs[$k] : $default;
            @endphp
            <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 rounded-lg p-4">
                <h4 class="text-sm font-bold text-emerald-900 dark:text-emerald-200 mb-2">سکشن‌های فعال در این صفحه</h4>
                {{-- کلیدها دقیقاً همان‌هایی‌اند که فرانتِ Next.js می‌شناسد. --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm text-gray-700 dark:text-gray-200">
                    @foreach([
                        'hero'         => 'Hero',
                        'activity'     => 'نوار فعالیت زنده',
                        'steps'        => 'مراحل کار',
                        'content'      => 'متن محتوایی',
                        'pricing'      => 'تعرفه خدمات',
                        'faq'          => 'سوالات متداول',
                        'brands'       => 'برندها / دستگاه‌ها',
                        'otherDevices' => 'سایر دستگاه‌های برند',
                        'videos'       => 'ویدیوها',
                        'forum'        => 'پرسش‌های انجمن',
                        'related'      => 'مقالات مرتبط',
                        'testimonials' => 'نظرات',
                        'promo'        => 'بنر اپلیکیشن',
                        'links'        => 'گرید لینک فرزندان',
                    ] as $key => $label)
                        <label class="inline-flex items-center gap-2">
                            <input type="hidden" name="sections_enabled[{{ $key }}]" value="0">
                            <input type="checkbox" name="sections_enabled[{{ $key }}]" value="1" @checked($secEnabled($key, true))>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Hero متن --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Badge / eyebrow</label>
                    <input type="text" name="eyebrow" value="{{ old('eyebrow', $cityPage->eyebrow) }}" maxlength="120"
                           placeholder="سرویس تخصصی در شهر شما"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">زیرتیتر</label>
                    <input type="text" name="subtitle" value="{{ old('subtitle', $cityPage->subtitle) }}" maxlength="500"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">کپشن (متن کوتاه)</label>
                    <input type="text" name="caption" value="{{ old('caption', $cityPage->caption) }}" maxlength="500" dir="auto"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">محتوای کامل صفحه (سکشن content)</label>
                    <textarea name="content" rows="10"
                              class="rich-editor w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm">{{ old('content', $cityPage->content) }}</textarea>
                    <p class="text-xs text-gray-500 mt-1">HTML از TinyMCE. این متن در بدنهٔ صفحه روی سایت نمایش داده می‌شود.</p>
                </div>
            </div>

            {{-- تصاویر Hero --}}
            <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                <h4 class="text-sm font-bold mb-3">🖼️ تصاویر Hero این صفحه</h4>
                @php
                    $cityHero = \Modules\Site\Services\PageSectionService::normalizeHeroVisual(old('hero_image', $cityPage->hero_image ?? null));
                @endphp
                @include('site::admin.partials.hero-visual-picker', [
                    'name' => 'hero_image',
                    'desktopLeftUrl' => $cityHero['desktop_left']['url'],
                    'desktopLeftAlt' => $cityHero['desktop_left']['alt'],
                    'desktopRightUrl' => $cityHero['desktop_right']['url'],
                    'desktopRightAlt' => $cityHero['desktop_right']['alt'],
                    'mobileUrl' => $cityHero['mobile']['url'],
                    'mobileAlt' => $cityHero['mobile']['alt'],
                ])
            </div>

            {{-- CTAs --}}
            <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                <h4 class="text-sm font-bold mb-3">دکمه‌های اصلی Hero</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-2 bg-gray-50 dark:bg-gray-700/30">
                        <p class="text-xs font-bold text-gray-600 dark:text-gray-300">دکمه ثبت سفارش (Primary)</p>
                        <input type="text" name="cta_primary_label" value="{{ old('cta_primary_label', $cityPage->cta_primary_label) }}" maxlength="60" placeholder="ثبت سفارش"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm">
                        <input type="text" name="cta_primary_url" value="{{ old('cta_primary_url', $cityPage->cta_primary_url) }}" maxlength="500" dir="ltr" placeholder="/order"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm font-mono">
                        <input type="text" name="cta_primary_icon" value="{{ old('cta_primary_icon', $cityPage->cta_primary_icon) }}" maxlength="60" dir="ltr" placeholder="shopping-cart"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm font-mono">
                    </div>
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-2 bg-gray-50 dark:bg-gray-700/30">
                        <p class="text-xs font-bold text-gray-600 dark:text-gray-300">دکمه تماس فوری (Secondary)</p>
                        <input type="text" name="cta_secondary_label" value="{{ old('cta_secondary_label', $cityPage->cta_secondary_label) }}" maxlength="60" placeholder="تماس فوری"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm">
                        <input type="text" name="cta_secondary_url" value="{{ old('cta_secondary_url', $cityPage->cta_secondary_url) }}" maxlength="500" dir="ltr" placeholder="tel:..."
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm font-mono">
                        <input type="text" name="cta_secondary_icon" value="{{ old('cta_secondary_icon', $cityPage->cta_secondary_icon) }}" maxlength="60" dir="ltr" placeholder="phone"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm font-mono">
                    </div>
                </div>
            </div>

            {{-- تصاویر مراحل --}}
            <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                <h4 class="text-sm font-bold mb-1">تصاویر سکشن مراحل خدمات</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
                    <div>
                        <label class="block text-xs font-medium mb-1">URL تصویر دسکتاپ</label>
                        <input type="text" name="steps_image_desktop" value="{{ old('steps_image_desktop', $cityPage->steps_image_desktop) }}" maxlength="500" dir="ltr"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1">URL تصویر موبایل</label>
                        <input type="text" name="steps_image_mobile" value="{{ old('steps_image_mobile', $cityPage->steps_image_mobile) }}" maxlength="500" dir="ltr"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm font-mono">
                    </div>
                </div>
            </div>

            {{-- دسته‌بندی سوالات متداول --}}
            <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                @php
                    $faqCatItems = ($allFaqCategories ?? collect())->map(fn ($c) => (object) [
                        'id' => (int) $c->id,
                        'label' => $c->name,
                        'slug' => $c->slug,
                        'description_text' => 'شامل '.($c->faqs_count ?? 0).' سوال منتشرشده.',
                        'badge' => 'دسته‌بندی',
                        'badge_color' => 'emerald',
                    ]);
                @endphp
                @include('crm::partials.multi-picker', [
                    'name' => 'faq_category_ids',
                    'items' => $faqCatItems,
                    'selectedIds' => $selectedFaqCategoryIds ?? [],
                    'columns' => 'wide',
                    'label' => 'دسته‌بندی سوالات متداول',
                    'help' => 'انتخاب نشده = بدون سکشن دسته‌بندی FAQ.',
                    'emptyText' => 'دسته‌بندی فعالی برای FAQ تعریف نشده.',
                ])
            </div>

            {{-- سوالات متداول --}}
            <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                @php
                    $faqItems = ($allFaqs ?? collect())->map(fn ($f) => (object) [
                        'id' => $f->id,
                        'label' => $f->question,
                        'description_text' => \Illuminate\Support\Str::limit(strip_tags((string) ($f->answer ?? '')), 180),
                        'badge' => 'FAQ',
                        'badge_color' => 'indigo',
                    ]);
                @endphp
                @include('crm::partials.multi-picker', [
                    'name' => 'faq_ids',
                    'items' => $faqItems,
                    'selectedIds' => $selectedFaqIds ?? [],
                    'columns' => 'wide',
                    'label' => 'سوالات متداول این صفحه',
                    'help' => 'انتخاب نشده = بدون سکشن FAQ اختصاصی.',
                    'emptyText' => 'FAQ منتشرشده‌ای نیست.',
                ])
            </div>

            {{-- نظرات --}}
            <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                @php
                    $reviewItems = ($allReviews ?? collect())->map(function ($r) {
                        $isAudio = $r->type === \Modules\Site\Models\Review::TYPE_AUDIO;
                        $stars = str_repeat('★', (int) $r->rating).str_repeat('☆', max(0, 5 - (int) $r->rating));
                        $body = $isAudio
                            ? ($r->topic ? $r->topic.' ('.$stars.')' : $stars)
                            : ($r->content ? $stars.' — '.\Illuminate\Support\Str::limit(strip_tags((string) $r->content), 180) : $stars);

                        return (object) [
                            'id' => $r->id,
                            'label' => $r->author_name,
                            'description_text' => $body,
                            'badge' => $isAudio ? 'صوتی' : 'متنی',
                            'badge_color' => $isAudio ? 'purple' : 'sky',
                        ];
                    });
                @endphp
                @include('crm::partials.multi-picker', [
                    'name' => 'review_ids',
                    'items' => $reviewItems,
                    'selectedIds' => $selectedReviewIds ?? [],
                    'columns' => 'wide',
                    'label' => 'دیدگاه‌های نمایش‌داده‌شده در این صفحه',
                    'help' => 'انتخاب نشده = بدون سکشن نظرات اختصاصی.',
                    'emptyText' => 'دیدگاه تأییدشده‌ای موجود نیست.',
                ])
            </div>

            {{-- سئو — یکپارچه در پنلِ سئوی حرفه‌ای پایین صفحه --}}
            <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    مدیریت کاملِ سئو (عنوان، توضیحات، canonical، robots، Open Graph، اسکیما و امتیاز سئو)
                    در <b>«پنل سئوی حرفه‌ای»</b> در انتهای همین صفحه انجام می‌شود — ساختارِ سئو برای همهٔ
                    صفحات (دستگاه/برند/شهر) یکسان است.
                </p>
            </div>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="px-5 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">ذخیره</button>
            <a href="{{ route('crm.city-pages.preview', $cityPage) }}" target="_blank" class="px-5 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">پیش‌نمایش</a>
            <a href="{{ route('crm.cities.pages.index', $cityPage->city_id) }}" class="px-5 py-2 text-gray-600 hover:text-gray-900">انصراف</a>
        </div>
    </form>

    {{-- پنلِ سئوی حرفه‌ای — همان کامپوننتِ صفحاتِ دستگاه/برند (canonical/robots/OG/schema). --}}
    @can('manage-seo')
        <livewire:seo.meta-panel type="city_page" :model-id="$cityPage->id" :key="'seo-city-'.$cityPage->id" />
    @endcan
</div>
@endsection
