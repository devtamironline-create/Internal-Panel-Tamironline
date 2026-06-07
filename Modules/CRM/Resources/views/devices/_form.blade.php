@php
    $allFaqs = $allFaqs ?? collect();
    $selectedFaqIds = $selectedFaqIds ?? [];
    $allReviews = $allReviews ?? collect();
    $selectedReviewIds = $selectedReviewIds ?? [];
    $secs = old('sections_enabled', $device->sections_enabled ?? []);
    $secEnabled = fn (string $k, bool $default = true) => array_key_exists($k, (array) $secs)
        ? (bool) $secs[$k] : $default;
    $selectedBrandsCount = count($selectedBrandIds ?? []);
    $selectedFaqCatCount = count($selectedFaqCategoryIds ?? []);
    $selectedFaqCount = count($selectedFaqIds ?? []);
    $selectedReviewsCount = count($selectedReviewIds ?? []);
    $videosCount = count((array) old('videos', $device->videos ?? []));
@endphp
@csrf

<div class="space-y-5">

    {{-- ─── ۱) اطلاعات پایه ─── --}}
    <x-crm::section-card sectionKey="basic" title="اطلاعات پایه" icon="📋"
        description="نام، slug، آیکن، دسته، ترتیب و تصویر بندانگشتی">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">نام دستگاه <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $device->name ?? '') }}" required
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg">
                @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Slug</label>
                <input type="text" name="slug" value="{{ old('slug', $device->slug ?? '') }}" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg">
                @error('slug')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">کلید آیکن (Lucide)</label>
                <input type="text" name="icon" value="{{ old('icon', $device->icon ?? '') }}" dir="ltr"
                       placeholder="washing-machine"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg">
                <p class="text-xs text-gray-500 mt-1">kebab-case (مثال: washing-machine، refrigerator، snowflake)</p>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">تم رنگ (Tone)</label>
                <select name="tone" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg">
                    <option value="">—</option>
                    @foreach(['tone-blue','tone-green','tone-cyan','tone-sky','tone-orange','tone-amber','tone-rose','tone-violet','tone-emerald'] as $t)
                        <option value="{{ $t }}" @selected(old('tone', $device->tone ?? '') === $t)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">دسته‌بندی والد</label>
                <select name="device_category_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg">
                    <option value="">— بدون دسته —</option>
                    @foreach(($deviceCategories ?? collect()) as $cat)
                        <option value="{{ $cat->id }}" @selected((int) old('device_category_id', $device->device_category_id ?? 0) === (int) $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">
                    <a href="{{ route('crm.device-categories.index') }}" target="_blank" class="text-blue-600 hover:underline">مدیریت دسته‌ها ↗</a>
                </p>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">ترتیب نمایش</label>
                <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $device->sort_order ?? 0) }}"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg">
            </div>
            <div class="md:col-span-2">
                @php
                    $currentThumbMedia = ! empty($device->thumbnail_media_id ?? null)
                        ? \Modules\Site\Models\Media\Media::with('variants')->find($device->thumbnail_media_id)
                        : null;
                @endphp
                @include('site::admin.partials.media-picker', [
                    'name' => 'thumbnail_media_id',
                    'current' => $currentThumbMedia,
                    'kind' => 'image',
                    'label' => 'تصویر بندانگشتی دستگاه (۴۰۰×۴۰۰)',
                    'previewSize' => 'w-32 h-32',
                ])
                <p class="text-xs text-gray-500 mt-1">یا URL مستقیم:</p>
                <input type="text" name="thumbnail" value="{{ old('thumbnail', $device->thumbnail ?? '') }}" dir="ltr"
                       placeholder="https://..." class="w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono ltr">
            </div>
            <div class="md:col-span-2 flex items-center gap-6">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $device->is_active ?? true)) class="w-4 h-4">
                    <span class="text-sm">فعال</span>
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $device->is_featured ?? false)) class="w-4 h-4">
                    <span class="text-sm">ویژه (نمایش پیش‌فرض در Hero صفحه‌ی اصلی)</span>
                </label>
            </div>
        </div>
    </x-crm::section-card>

    {{-- ─── ۲) تصویر Hero ─── --}}
    <x-crm::section-card sectionKey="hero-image" title="تصاویر Hero اختصاصی این دستگاه" icon="🖼️"
        description="۲ تصویر دسکتاپ (چپ/راست) + ۱ تصویر موبایل، هرکدام با alt مجزا. هر slot خالی → از template دستگاه">
        @php
            $deviceHero = \Modules\Site\Services\PageSectionService::normalizeHeroVisual(old('hero_image', $device->hero_image ?? null));
        @endphp
        <p class="text-xs text-gray-500 mb-3">
            اگر هر slot را پر کنید، در صفحه‌ی همین دستگاه override می‌شود (به‌صورت per-slot — نیازی نیست همه‌ی سه را پر کنید).
            خالی بگذارید تا از <a href="{{ route('site.admin.page-content.edit', 'device') }}" target="_blank" class="text-blue-600 hover:underline">الگوی device</a> استفاده شود.
        </p>
        @include('site::admin.partials.hero-visual-picker', [
            'name' => 'hero_image',
            'desktopLeftUrl' => $deviceHero['desktop_left']['url'],
            'desktopLeftAlt' => $deviceHero['desktop_left']['alt'],
            'desktopRightUrl' => $deviceHero['desktop_right']['url'],
            'desktopRightAlt' => $deviceHero['desktop_right']['alt'],
            'mobileUrl' => $deviceHero['mobile']['url'],
            'mobileAlt' => $deviceHero['mobile']['alt'],
        ])
    </x-crm::section-card>

    {{-- ─── ۳) سکشن‌های فعال ─── --}}
    <x-crm::section-card sectionKey="sections-enabled" title="سکشن‌های فعال در این صفحه" icon="🔘"
        description="نمایش/مخفی‌سازی سکشن‌های صفحه‌ی دستگاه">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm">
            @foreach([
                'hero' => 'Hero',
                'steps' => 'مراحل خدمات',
                'live_activity' => 'Live Activity',
                'content' => 'محتوای کامل',
                'faq' => 'سوالات متداول',
                'brands' => 'برندها',
                'testimonials' => 'نظرات مشتریان',
                'videos' => 'ویدیوها',
                'forum_questions' => 'سوالات انجمن',
            ] as $key => $label)
                <label class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                    <input type="hidden" name="sections_enabled[{{ $key }}]" value="0">
                    <input type="checkbox" name="sections_enabled[{{ $key }}]" value="1"
                           @checked($secEnabled($key, true))
                           class="w-4 h-4">
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </x-crm::section-card>

    {{-- ─── ۴) متن‌های CMS ─── --}}
    <x-crm::section-card sectionKey="cms-text" title="متن‌های صفحه (CMS override)" icon="✍️"
        description="هر فیلد خالی → از الگوی سراسری device خوانده می‌شود">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">نام کوتاه (short_name)</label>
                <input type="text" name="short_name" value="{{ old('short_name', $device->short_name ?? '') }}" maxlength="80"
                       placeholder="لباسشویی" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">نام سرویس (service_name)</label>
                <input type="text" name="service_name" value="{{ old('service_name', $device->service_name ?? '') }}" maxlength="160"
                       placeholder="تعمیر لباسشویی" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Badge / Eyebrow (بالای تیتر)</label>
                <input type="text" name="eyebrow" value="{{ old('eyebrow', $device->eyebrow ?? '') }}" maxlength="120"
                       placeholder="سرویس تخصصی" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">کپشن (متن کوتاه)</label>
                <input type="text" name="caption" value="{{ old('caption', $device->caption ?? '') }}" maxlength="500"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium mb-1">زیرتیتر (subtitle)</label>
                <input type="text" name="subtitle" value="{{ old('subtitle', $device->subtitle ?? '') }}" maxlength="500"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium mb-1">محتوای کامل (سکشن content)</label>
                <textarea name="description" rows="10"
                          class="rich-editor w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ old('description', $device->description ?? '') }}</textarea>
                <p class="text-xs text-gray-500 mt-1">محتوای متنی غنی — HTML در فرانت render می‌شود.</p>
            </div>
            {{-- hidden technician_name (legacy) --}}
            <div style="display:none">
                <input type="text" name="technician_name" value="{{ old('technician_name', $device->technician_name ?? '') }}">
            </div>
        </div>
    </x-crm::section-card>

    {{-- ─── ۵) CTA Buttons ─── --}}
    <x-crm::section-card sectionKey="cta" title="دکمه‌های Hero" icon="🔗"
        description="دو دکمه‌ی primary و secondary بالای صفحه‌ی دستگاه">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-2 bg-gray-50 dark:bg-gray-900">
                <p class="text-xs font-bold text-gray-600 dark:text-gray-400">دکمه‌ی ثبت سفارش (Primary)</p>
                <input type="text" name="cta_primary_label" value="{{ old('cta_primary_label', $device->cta_primary_label ?? '') }}" maxlength="60"
                       placeholder="ثبت سفارش" class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm">
                <input type="text" name="cta_primary_url" value="{{ old('cta_primary_url', $device->cta_primary_url ?? '') }}" maxlength="500" dir="ltr"
                       placeholder="/order" class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
                <input type="text" name="cta_primary_icon" value="{{ old('cta_primary_icon', $device->cta_primary_icon ?? '') }}" maxlength="60" dir="ltr"
                       placeholder="shopping-cart" class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
            </div>
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-2 bg-gray-50 dark:bg-gray-900">
                <p class="text-xs font-bold text-gray-600 dark:text-gray-400">دکمه‌ی تماس فوری (Secondary)</p>
                <input type="text" name="cta_secondary_label" value="{{ old('cta_secondary_label', $device->cta_secondary_label ?? '') }}" maxlength="60"
                       placeholder="تماس فوری" class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm">
                <input type="text" name="cta_secondary_url" value="{{ old('cta_secondary_url', $device->cta_secondary_url ?? '') }}" maxlength="500" dir="ltr"
                       placeholder="tel:..." class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
                <input type="text" name="cta_secondary_icon" value="{{ old('cta_secondary_icon', $device->cta_secondary_icon ?? '') }}" maxlength="60" dir="ltr"
                       placeholder="phone" class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
            </div>
        </div>
    </x-crm::section-card>

    {{-- ─── ۶) تصاویر مراحل ─── --}}
    <x-crm::section-card sectionKey="steps-images" title="تصاویر سکشن مراحل خدمات" icon="🪜"
        description="override تصاویر steps برای این دستگاه (خالی = template)">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium mb-1">URL تصویر دسکتاپ</label>
                <input type="text" name="steps_image_desktop" value="{{ old('steps_image_desktop', $device->steps_image_desktop ?? '') }}" maxlength="500" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">URL تصویر موبایل</label>
                <input type="text" name="steps_image_mobile" value="{{ old('steps_image_mobile', $device->steps_image_mobile ?? '') }}" maxlength="500" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
            </div>
        </div>
    </x-crm::section-card>

    {{-- ─── ۷) قیمت و رنگ ─── --}}
    <x-crm::section-card sectionKey="pricing" title="قیمت و رنگ‌بندی" icon="💰"
        description="قیمت شروع و رنگ accent/پس‌زمینه برای این دستگاه">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">قیمت شروع (ریال)</label>
                <input type="number" name="starting_price" value="{{ old('starting_price', $device->starting_price ?? '') }}" min="0"
                       placeholder="1500000" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">رنگ accent (hex)</label>
                <input type="text" name="accent" value="{{ old('accent', $device->accent ?? '') }}" placeholder="#3B82F6" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm font-mono ltr">
                @error('accent')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">رنگ پس‌زمینه (hex)</label>
                <input type="text" name="bg" value="{{ old('bg', $device->bg ?? '') }}" placeholder="#EFF6FF" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm font-mono ltr">
                @error('bg')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
    </x-crm::section-card>

    {{-- ─── ۸) برندهای پشتیبانی ─── --}}
    <x-crm::section-card sectionKey="brands" title="برندهای پشتیبانی" icon="🏢"
        description="برندهایی که برای این دستگاه تعمیر می‌کنید"
        count="{{ $selectedBrandsCount }}">
        @include('crm::partials.multi-picker', [
            'name' => 'brand_ids',
            'items' => $allBrands ?? collect(),
            'selectedIds' => $selectedBrandIds ?? [],
            'label' => 'برندها',
            'help' => 'خالی = همه‌ی برندهای فعال نمایش داده می‌شوند.',
            'emptyText' => 'برند فعالی موجود نیست.',
        ])
    </x-crm::section-card>

    {{-- ─── ۹) دسته‌بندی FAQ ─── --}}
    <x-crm::section-card sectionKey="faq-categories" title="دسته‌بندی سوالات متداول" icon="🏷️"
        description="با انتخاب دسته، تمام سوالات منتشرشده خودکار نمایش داده می‌شوند"
        count="{{ $selectedFaqCatCount }}">
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
            'label' => 'دسته‌های FAQ',
            'help' => 'مدیریت دسته‌ها: <a href="'.route('site.admin.taxonomies.index', 'faq').'" target="_blank">/admin/site/taxonomies/faq</a>',
            'emptyText' => 'دسته‌بندی فعالی برای FAQ تعریف نشده.',
        ])
    </x-crm::section-card>

    {{-- ─── ۱۰) سوالات منفرد ─── --}}
    <x-crm::section-card sectionKey="faqs" title="سوالات متداول این دستگاه" icon="❓"
        description="سوالات منفرد از بانک FAQ — ترتیب انتخاب = ترتیب نمایش"
        count="{{ $selectedFaqCount }}">
        @php
            $faqItems = $allFaqs->map(fn ($f) => (object) [
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
            'selectedIds' => $selectedFaqIds,
            'columns' => 'wide',
            'label' => 'سوالات اختصاصی',
            'help' => 'مدیریت بانک: <a href="'.route('site.admin.faqs.index').'" target="_blank">/admin/site/faqs</a>',
            'emptyText' => 'بانک FAQ خالی است.',
        ])
    </x-crm::section-card>

    {{-- ─── ۱۱) دیدگاه‌ها ─── --}}
    <x-crm::section-card sectionKey="reviews" title="دیدگاه‌های نمایش‌داده‌شده" icon="💬"
        description="نظرات صوتی/متنی انتخاب‌شده از بانک نظرات"
        count="{{ $selectedReviewsCount }}">
        @php
            $reviewItems = $allReviews->map(function ($r) {
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
            'selectedIds' => $selectedReviewIds,
            'columns' => 'wide',
            'label' => 'انتخاب از بانک نظرات',
            'help' => 'مدیریت بانک: <a href="'.route('site.admin.reviews.index').'" target="_blank">/admin/site/reviews</a>',
            'emptyText' => 'دیدگاه تأییدشده‌ای در بانک نیست.',
        ])
    </x-crm::section-card>

    {{-- ─── ۱۲) ویدیوها ─── --}}
    <x-crm::section-card sectionKey="videos" title="ویدیوهای اختصاصی این دستگاه" icon="🎬"
        description="aparat_id / youtube_id / mp4 مستقیم (اولویت با aparat). خالی = template. در عنوان/توضیح می‌توانید از {device} استفاده کنید — هنگام نمایش با نام دستگاه جایگزین می‌شود."
        count="{{ $videosCount }}">
        @include('crm::partials.json-repeater', [
            'name' => 'videos',
            'label' => 'لیست ویدیوها',
            'items' => old('videos', $device->videos ?? []),
            'item_fields' => [
                'title' => ['label' => 'عنوان ویدیو (با {device})', 'type' => 'string'],
                'aparat_id' => ['label' => 'Aparat ID', 'type' => 'string'],
                'youtube_id' => ['label' => 'YouTube ID', 'type' => 'string'],
                'video_url' => ['label' => 'URL مستقیم (mp4)', 'type' => 'string'],
                'description' => ['label' => 'توضیح کوتاه (با {device})', 'type' => 'textarea'],
                'poster_url' => ['label' => 'تصویر cover — انتخاب از مخزن مدیا', 'type' => 'image'],
            ],
        ])
    </x-crm::section-card>

    {{-- ─── ۱۳) سئو ─── --}}
    <x-crm::section-card sectionKey="seo" title="سئو (Meta)" icon="🔍"
        description="Meta Title و Meta Description مخصوص این دستگاه">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Meta Title</label>
                <input type="text" name="meta_title" value="{{ old('meta_title', $device->meta_title ?? '') }}" maxlength="200"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Meta Description</label>
                <textarea name="meta_description" rows="2" maxlength="500"
                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ old('meta_description', $device->meta_description ?? '') }}</textarea>
            </div>
        </div>
    </x-crm::section-card>

    {{-- ─── فیلدهای legacy مخفی (هنوز submit می‌شوند) ─── --}}
    <div style="display:none">
        @include('crm::partials.json-repeater', [
            'name' => 'issues',
            'label' => 'مشکلات (legacy)',
            'items' => old('issues', $device->issues ?? []),
            'item_fields' => [
                'title' => ['label' => 'عنوان', 'type' => 'string'],
                'description' => ['label' => 'توضیح', 'type' => 'textarea'],
            ],
        ])
        @include('crm::partials.json-repeater', [
            'name' => 'service_steps',
            'label' => 'مراحل (legacy)',
            'items' => old('service_steps', $device->service_steps ?? []),
            'item_fields' => [
                'title' => ['label' => 'عنوان', 'type' => 'string'],
                'description' => ['label' => 'توضیح', 'type' => 'textarea'],
                'icon' => ['label' => 'آیکن', 'type' => 'string'],
            ],
        ])
        <textarea name="warranty_text" rows="3">{{ old('warranty_text', $device->warranty_text ?? '') }}</textarea>
        <textarea name="support_info" rows="3">{{ old('support_info', $device->support_info ?? '') }}</textarea>
    </div>

</div>
