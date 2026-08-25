@csrf

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div>
        <label class="block text-sm font-medium mb-1">دستگاه <span class="text-red-500">*</span></label>
        @if($page)
            <input type="text" disabled value="{{ $page->device->name ?? '' }} ({{ $page->device->slug ?? '' }})"
                   class="w-full px-3 py-2 border border-gray-200 bg-gray-100 rounded text-sm">
            <input type="hidden" name="device_id" value="{{ $page->device_id }}">
        @else
            <select name="device_id" required class="w-full px-3 py-2 border border-gray-300 rounded text-sm">
                <option value="">— انتخاب کنید —</option>
                @foreach($allDevices as $d)
                    <option value="{{ $d->id }}" @selected(old('device_id') == $d->id)>{{ $d->name }} ({{ $d->slug }})</option>
                @endforeach
            </select>
        @endif
        @error('device_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">برند <span class="text-red-500">*</span></label>
        @if($page)
            <input type="text" disabled value="{{ $page->brand->name ?? '' }} ({{ $page->brand->slug ?? '' }})"
                   class="w-full px-3 py-2 border border-gray-200 bg-gray-100 rounded text-sm">
            <input type="hidden" name="brand_id" value="{{ $page->brand_id }}">
        @else
            <select name="brand_id" required class="w-full px-3 py-2 border border-gray-300 rounded text-sm">
                <option value="">— انتخاب کنید —</option>
                @foreach($allBrands as $b)
                    <option value="{{ $b->id }}" @selected(old('brand_id') == $b->id)>{{ $b->name }} ({{ $b->slug }})</option>
                @endforeach
            </select>
        @endif
        @error('brand_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>
    <div class="flex items-end">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $page?->is_active ?? true))
                   class="w-4 h-4 text-brand-600 border-gray-300 rounded">
            <span class="text-sm">فعال (در API نمایش داده شود)</span>
        </label>
    </div>
</div>

<div class="mt-6 border border-gray-200 dark:border-gray-700 rounded-lg p-6 space-y-6">

    {{-- ─── Section toggles ─── --}}
    @php
        $secs = old('sections_enabled', $page?->sections_enabled ?? []);
        $secEnabled = fn(string $k, bool $default = true) => array_key_exists($k, (array) $secs)
            ? (bool) $secs[$k] : $default;
    @endphp
    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 rounded-lg p-4">
        <h4 class="text-sm font-bold text-emerald-900 dark:text-emerald-200 mb-2">سکشن‌های فعال در این صفحه</h4>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm">
            @foreach([
                'hero'                => 'Hero',
                'steps'               => 'مراحل خدمات',
                'live_activity'       => 'Live Activity',
                'content'             => 'محتوای کامل',
                'faq'                 => 'سوالات متداول',
                'brand_other_devices' => 'سایر دستگاه‌های برند',
                'testimonials'        => 'نظرات مشتریان',
                'videos'              => 'ویدیوها',
                'forum_questions'     => 'سوالات انجمن',
                'related_articles'    => 'مقالات مرتبط',
            ] as $key => $label)
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="sections_enabled[{{ $key }}]" value="0">
                    <input type="checkbox" name="sections_enabled[{{ $key }}]" value="1"
                           @checked($secEnabled($key, true))>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </div>

    {{-- Hero --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Badge / eyebrow</label>
            <input type="text" name="eyebrow" value="{{ old('eyebrow', $page?->eyebrow ?? '') }}" maxlength="120"
                   placeholder="سرویس تخصصی"
                   class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm">
            <p class="text-xs text-gray-500 mt-1">خالی = استفاده از مقدار دستگاه ← برند ← الگو</p>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">تیتر (override کامل)</label>
            <input type="text" name="title" value="{{ old('title', $page?->title ?? '') }}" maxlength="200"
                   placeholder="تعمیر ماشین‌ظرفشویی Samsung"
                   class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">زیرتیتر</label>
            <input type="text" name="subtitle" value="{{ old('subtitle', $page?->subtitle ?? '') }}" maxlength="500"
                   class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">کپشن</label>
            <input type="text" name="caption" value="{{ old('caption', $page?->caption ?? '') }}" maxlength="500"
                   placeholder="خدمات @{{device}} @{{brand}} در @{{cities}}"
                   class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm" dir="auto">
            <p class="text-[11px] text-gray-400 mt-1">
                ✨ داینامیک: <code dir="ltr">@{{device}}</code> · <code dir="ltr">@{{brand}}</code> ·
                <code dir="ltr">@{{cities}}</code> (فقط شهرهایی که همین برند در آن‌ها تکنسین دارد) ·
                <code dir="ltr">@{{provinces}}</code> · <code dir="ltr">@{{city_count}}</code>
            </p>
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">محتوای کامل صفحه (سکشن content)</label>
            <textarea name="description" rows="10"
                      class="rich-editor w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm">{{ old('description', $page?->description ?? '') }}</textarea>
            <p class="text-xs text-gray-500 mt-1">HTML از TinyMCE. اگر خالی باشد، از description دستگاه (یا brand یا الگو) استفاده می‌شود.</p>
        </div>
    </div>

    {{-- تصاویر Hero اختصاصی این صفحهٔ ترکیبی --}}
    <div class="pt-4 border-t border-gray-100">
        <h4 class="text-sm font-bold mb-3">🖼️ تصاویر Hero اختصاصی این صفحه</h4>
        @php
            $comboHero = \Modules\Site\Services\PageSectionService::normalizeHeroVisual(old('hero_image', $page?->hero_image ?? null));
        @endphp
        <p class="text-xs text-gray-500 mb-3">
            ۲ تصویر دسکتاپ (چپ/راست) + ۱ تصویر موبایل، هرکدام با alt مجزا. هر slot که پر کنید فقط برای همین صفحهٔ ترکیبی override می‌شود؛
            slotهای خالی از دستگاه ← برند ← <a href="{{ route('site.admin.page-content.edit', 'device_brand') }}" target="_blank" class="text-blue-600 hover:underline">الگوی ترکیبی</a> پر می‌شوند.
        </p>
        @include('site::admin.partials.hero-visual-picker', [
            'name' => 'hero_image',
            'desktopLeftUrl' => $comboHero['desktop_left']['url'],
            'desktopLeftAlt' => $comboHero['desktop_left']['alt'],
            'desktopRightUrl' => $comboHero['desktop_right']['url'],
            'desktopRightAlt' => $comboHero['desktop_right']['alt'],
            'mobileUrl' => $comboHero['mobile']['url'],
            'mobileAlt' => $comboHero['mobile']['alt'],
        ])
    </div>

    {{-- CTAs --}}
    <div class="pt-4 border-t border-gray-100">
        <h4 class="text-sm font-bold mb-3">دکمه‌های اصلی Hero</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="border border-gray-200 rounded-lg p-4 space-y-2 bg-gray-50">
                <p class="text-xs font-bold text-gray-600">دکمه ثبت سفارش (Primary)</p>
                <input type="text" name="cta_primary_label" value="{{ old('cta_primary_label', $page?->cta_primary_label ?? '') }}" maxlength="60" placeholder="ثبت سفارش"
                       class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm">
                <input type="text" name="cta_primary_url" value="{{ old('cta_primary_url', $page?->cta_primary_url ?? '') }}" maxlength="500" dir="ltr" placeholder="/order"
                       class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
                <input type="text" name="cta_primary_icon" value="{{ old('cta_primary_icon', $page?->cta_primary_icon ?? '') }}" maxlength="60" dir="ltr" placeholder="shopping-cart"
                       class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
            </div>
            <div class="border border-gray-200 rounded-lg p-4 space-y-2 bg-gray-50">
                <p class="text-xs font-bold text-gray-600">دکمه تماس فوری (Secondary)</p>
                <input type="text" name="cta_secondary_label" value="{{ old('cta_secondary_label', $page?->cta_secondary_label ?? '') }}" maxlength="60" placeholder="تماس فوری"
                       class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm">
                <input type="text" name="cta_secondary_url" value="{{ old('cta_secondary_url', $page?->cta_secondary_url ?? '') }}" maxlength="500" dir="ltr" placeholder="tel:..."
                       class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
                <input type="text" name="cta_secondary_icon" value="{{ old('cta_secondary_icon', $page?->cta_secondary_icon ?? '') }}" maxlength="60" dir="ltr" placeholder="phone"
                       class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
            </div>
        </div>
    </div>

    {{-- Steps image override --}}
    <div class="pt-4 border-t border-gray-100">
        <h4 class="text-sm font-bold mb-1">تصاویر سکشن مراحل خدمات</h4>
        <p class="text-xs text-gray-500 mb-3">خالی = استفاده از تصویر دستگاه ← برند ← الگو.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium mb-1">URL تصویر دسکتاپ</label>
                <input type="text" name="steps_image_desktop" value="{{ old('steps_image_desktop', $page?->steps_image_desktop ?? '') }}" maxlength="500" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">URL تصویر موبایل</label>
                <input type="text" name="steps_image_mobile" value="{{ old('steps_image_mobile', $page?->steps_image_mobile ?? '') }}" maxlength="500" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
            </div>
        </div>
    </div>

    {{-- FAQ categories --}}
    <div class="pt-4 border-t border-gray-100">
        @php
            $faqCatItems = ($allFaqCategories ?? collect())->map(fn ($c) => (object) [
                'id' => (int) $c->id,
                'label' => $c->name,
                'slug' => $c->slug,
                'description_text' => 'شامل ' . ($c->faqs_count ?? 0) . ' سوال منتشرشده.',
                'badge' => 'دسته‌بندی',
                'badge_color' => 'emerald',
            ]);
        @endphp
        @include('crm::partials.multi-picker', [
            'name'        => 'faq_category_ids',
            'items'       => $faqCatItems,
            'selectedIds' => $selectedFaqCategoryIds ?? [],
            'columns'     => 'wide',
            'label'       => 'دسته‌بندی سوالات متداول',
            'help'        => 'انتخاب نشده = استفاده از انتخاب per-device یا per-brand یا الگو.',
            'emptyText'   => 'دسته‌بندی فعالی برای FAQ تعریف نشده.',
        ])
    </div>

    {{-- FAQ individual --}}
    <div class="pt-4 border-t border-gray-100">
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
            'name'        => 'faq_ids',
            'items'       => $faqItems,
            'selectedIds' => $selectedFaqIds ?? [],
            'columns'     => 'wide',
            'label'       => 'سوالات متداول این صفحه',
            'help'        => 'انتخاب نشده = استفاده از انتخاب per-device یا per-brand یا الگو.',
            'emptyText'   => 'FAQ منتشرشده‌ای نیست.',
        ])
    </div>

    {{-- Reviews --}}
    <div class="pt-4 border-t border-gray-100">
        @php
            $reviewItems = ($allReviews ?? collect())->map(function ($r) {
                $isAudio = $r->type === \Modules\Site\Models\Review::TYPE_AUDIO;
                $stars = str_repeat('★', (int) $r->rating) . str_repeat('☆', max(0, 5 - (int) $r->rating));
                $body = $isAudio
                    ? ($r->topic ? $r->topic . ' (' . $stars . ')' : $stars)
                    : ($r->content ? $stars . ' — ' . \Illuminate\Support\Str::limit(strip_tags((string) $r->content), 180) : $stars);
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
            'name'        => 'review_ids',
            'items'       => $reviewItems,
            'selectedIds' => $selectedReviewIds ?? [],
            'columns'     => 'wide',
            'label'       => 'دیدگاه‌های نمایش‌داده‌شده در این صفحه',
            'help'        => 'انتخاب نشده = fallback به per-device.',
            'emptyText'   => 'دیدگاه تأییدشده‌ای موجود نیست.',
        ])
    </div>

    {{-- SEO --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-gray-100">
        <div>
            <label class="block text-sm font-medium mb-1">Meta Title</label>
            <input type="text" name="meta_title" value="{{ old('meta_title', $page?->meta_title ?? '') }}" maxlength="200"
                   class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Meta Description</label>
            <textarea name="meta_description" rows="2" maxlength="500"
                      class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm">{{ old('meta_description', $page?->meta_description ?? '') }}</textarea>
        </div>
    </div>
</div>

<div class="flex items-center gap-3 mt-6">
    <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">ذخیره</button>
    <a href="{{ route('crm.device-brand-pages.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">انصراف</a>
</div>
