@csrf

@php
    $secs = old('sections_enabled', $brand->sections_enabled ?? []);
    $secEnabled = fn (string $k, bool $default = true) => array_key_exists($k, (array) $secs)
        ? (bool) $secs[$k] : $default;
    $selectedDevicesCount = count($selectedDeviceIds ?? []);
    $selectedFaqCatCount = count($selectedFaqCategoryIds ?? []);
    $selectedFaqCount = count($selectedFaqIds ?? []);
    $selectedReviewsCount = count($selectedReviewIds ?? []);
    $videosCount = count((array) old('videos', $brand->videos ?? []));
@endphp

<div class="space-y-5">

    {{-- ─── ۱) اطلاعات پایه ─── --}}
    <x-crm::section-card sectionKey="basic" title="اطلاعات پایه" icon="📋"
        description="نام، slug، ترتیب، وضعیت فعال/ویژه و لوگوی برند">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نام برند <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $brand->name ?? '') }}" required
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
                @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Slug (خودکار از نام)</label>
                <input type="text" name="slug" value="{{ old('slug', $brand->slug ?? '') }}"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500" dir="ltr">
                @error('slug')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2">
                @php
                    $currentLogoMedia = ! empty($brand->logo_media_id ?? null)
                        ? \Modules\Site\Models\Media\Media::with('variants')->find($brand->logo_media_id)
                        : null;
                @endphp
                @include('site::admin.partials.media-picker', [
                    'name' => 'logo_media_id',
                    'current' => $currentLogoMedia,
                    'kind' => 'image',
                    'label' => 'لوگوی برند (۴۰۰×۴۰۰، PNG شفاف یا SVG)',
                    'previewSize' => 'w-32 h-32',
                ])
                <p class="text-xs text-gray-500 mt-1">یا URL مستقیم:</p>
                <input type="text" name="logo" value="{{ old('logo', $brand->logo ?? '') }}" dir="ltr"
                       class="w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono ltr">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">ترتیب نمایش</label>
                <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $brand->sort_order ?? 0) }}"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
            </div>
            <div class="flex items-end gap-6">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $brand->is_active ?? true))
                           class="w-4 h-4">
                    <span class="text-sm">فعال</span>
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $brand->is_featured ?? false))
                           class="w-4 h-4">
                    <span class="text-sm">برند ویژه (صفحه‌ی اصلی)</span>
                </label>
            </div>
        </div>
    </x-crm::section-card>

    {{-- ─── ۲) رنگ‌های هویت ─── --}}
    <x-crm::section-card sectionKey="identity" title="رنگ‌های هویت برند" icon="🎨"
        description="رنگ accent و پس‌زمینه برای استفاده در فرانت — خالی = پیش‌فرض قالب">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4"
             x-data="{
                tone: @js(old('tone', $brand->tone ?? '')),
                bg:   @js(old('bg', $brand->bg ?? '')),
                normalize(v) {
                    if (!v) return '';
                    v = v.trim();
                    if (v[0] !== '#') v = '#' + v;
                    return v;
                },
             }">
            <div>
                <label class="block text-sm font-medium mb-1">رنگ اصلی برند (accent)</label>
                <div class="flex items-center gap-2">
                    <input type="color"
                           :value="(tone && /^#[0-9a-fA-F]{6}$/.test(tone)) ? tone : '#1428A0'"
                           @input="tone = $event.target.value"
                           class="h-10 w-14 border border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer bg-transparent">
                    <input type="text" name="tone" x-model="tone" :value="tone" maxlength="9" dir="ltr"
                           placeholder="#1428A0" @blur="tone = normalize(tone)"
                           class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm font-mono ltr">
                </div>
                <p class="text-xs text-gray-500 mt-1">مثال سامسونگ: <span dir="ltr">#1428A0</span></p>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">رنگ پس‌زمینه ملایم</label>
                <div class="flex items-center gap-2">
                    <input type="color"
                           :value="(bg && /^#[0-9a-fA-F]{6}$/.test(bg)) ? bg : '#EEF2FF'"
                           @input="bg = $event.target.value"
                           class="h-10 w-14 border border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer bg-transparent">
                    <input type="text" name="bg" x-model="bg" :value="bg" maxlength="9" dir="ltr"
                           placeholder="#EEF2FF" @blur="bg = normalize(bg)"
                           class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm font-mono ltr">
                </div>
                <p class="text-xs text-gray-500 mt-1">پس‌زمینه‌ی کارت‌ها و سکشن‌های فرعی</p>
            </div>
            <div class="sm:col-span-2">
                <p class="text-xs text-gray-500 mb-2">پیش‌نمایش زنده:</p>
                <div class="rounded-xl p-6 border border-gray-200 dark:border-gray-700"
                     :style="{ backgroundColor: (bg && /^#[0-9a-fA-F]{6}$/.test(bg)) ? bg : '#f9fafb' }">
                    <div class="flex items-center gap-3 flex-wrap">
                        <span class="px-4 py-2 rounded-lg text-white font-bold text-sm"
                              :style="{ backgroundColor: (tone && /^#[0-9a-fA-F]{6}$/.test(tone)) ? tone : '#374151' }">
                            {{ $brand->name ?? 'نام برند' }}
                        </span>
                        <span class="text-sm font-medium"
                              :style="{ color: (tone && /^#[0-9a-fA-F]{6}$/.test(tone)) ? tone : '#374151' }">
                            پیش‌نمایش متن تأکیدی
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </x-crm::section-card>

    {{-- ─── ۳) تصویر Hero ─── --}}
    <x-crm::section-card sectionKey="hero-image" title="تصاویر Hero اختصاصی این برند" icon="🖼️"
        description="۲ تصویر دسکتاپ (چپ/راست) + ۱ تصویر موبایل، هرکدام با alt مجزا. هر slot خالی → از template برند">
        @php
            $brandHero = \Modules\Site\Services\PageSectionService::normalizeHeroVisual(old('hero_image', $brand->hero_image ?? null));
        @endphp
        <p class="text-xs text-gray-500 mb-3">
            اگر هر slot را پر کنید، در صفحه‌ی همین برند override می‌شود (به‌صورت per-slot — نیازی نیست همه‌ی سه را پر کنید).
            خالی بگذارید تا از <a href="{{ route('site.admin.page-content.edit', 'brand') }}" target="_blank" class="text-blue-600 hover:underline">الگوی brand</a> استفاده شود.
        </p>
        @include('site::admin.partials.hero-visual-picker', [
            'name' => 'hero_image',
            'desktopLeftUrl' => $brandHero['desktop_left']['url'],
            'desktopLeftAlt' => $brandHero['desktop_left']['alt'],
            'desktopRightUrl' => $brandHero['desktop_right']['url'],
            'desktopRightAlt' => $brandHero['desktop_right']['alt'],
            'mobileUrl' => $brandHero['mobile']['url'],
            'mobileAlt' => $brandHero['mobile']['alt'],
        ])
    </x-crm::section-card>

    {{-- ─── ۴) سکشن‌های فعال ─── --}}
    <x-crm::section-card sectionKey="sections-enabled" title="سکشن‌های فعال در این صفحه" icon="🔘"
        description="نمایش/مخفی‌سازی سکشن‌های صفحه‌ی برند">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm">
            @foreach([
                'hero' => 'Hero',
                'steps' => 'مراحل خدمات',
                'live_activity' => 'Live Activity',
                'content' => 'محتوای کامل',
                'faq' => 'سوالات متداول',
                'devices' => 'دستگاه‌ها',
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

    {{-- ─── ۵) متن‌های CMS ─── --}}
    <x-crm::section-card sectionKey="cms-text" title="متن‌های صفحه (CMS override)" icon="✍️"
        description="هر فیلد خالی → از الگوی سراسری brand خوانده می‌شود">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">شعار / Tagline</label>
                <input type="text" name="tagline" value="{{ old('tagline', $brand->tagline ?? '') }}" maxlength="1000"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Badge / Eyebrow (بالای تیتر)</label>
                <input type="text" name="eyebrow" value="{{ old('eyebrow', $brand->eyebrow ?? '') }}" maxlength="120"
                       placeholder="نمایندگی رسمی"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">زیرتیتر (subtitle)</label>
                <input type="text" name="subtitle" value="{{ old('subtitle', $brand->subtitle ?? '') }}" maxlength="500"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">کپشن (متن کوتاه)</label>
                <input type="text" name="caption" value="{{ old('caption', $brand->caption ?? '') }}" maxlength="500"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium mb-1">محتوای کامل (سکشن content)</label>
                <textarea name="description" rows="10"
                          class="rich-editor w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ old('description', $brand->description ?? '') }}</textarea>
                <p class="text-xs text-gray-500 mt-1">محتوای متنی غنی برای سکشن «محتوای کامل» — HTML در فرانت render می‌شود.</p>
            </div>
        </div>
    </x-crm::section-card>

    {{-- ─── ۶) CTA Buttons ─── --}}
    <x-crm::section-card sectionKey="cta" title="دکمه‌های Hero" icon="🔗"
        description="دو دکمه‌ی primary و secondary بالای صفحه‌ی برند">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-2 bg-gray-50 dark:bg-gray-900">
                <p class="text-xs font-bold text-gray-600 dark:text-gray-400">دکمه‌ی ثبت سفارش (Primary)</p>
                <input type="text" name="cta_primary_label" value="{{ old('cta_primary_label', $brand->cta_primary_label ?? '') }}" maxlength="60"
                       placeholder="ثبت سفارش" class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm">
                <input type="text" name="cta_primary_url" value="{{ old('cta_primary_url', $brand->cta_primary_url ?? '') }}" maxlength="500" dir="ltr"
                       placeholder="/order یا https://..." class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
                <input type="text" name="cta_primary_icon" value="{{ old('cta_primary_icon', $brand->cta_primary_icon ?? '') }}" maxlength="60" dir="ltr"
                       placeholder="shopping-cart" class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
            </div>
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-2 bg-gray-50 dark:bg-gray-900">
                <p class="text-xs font-bold text-gray-600 dark:text-gray-400">دکمه‌ی تماس فوری (Secondary)</p>
                <input type="text" name="cta_secondary_label" value="{{ old('cta_secondary_label', $brand->cta_secondary_label ?? '') }}" maxlength="60"
                       placeholder="تماس فوری" class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm">
                <input type="text" name="cta_secondary_url" value="{{ old('cta_secondary_url', $brand->cta_secondary_url ?? '') }}" maxlength="500" dir="ltr"
                       placeholder="tel:..." class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
                <input type="text" name="cta_secondary_icon" value="{{ old('cta_secondary_icon', $brand->cta_secondary_icon ?? '') }}" maxlength="60" dir="ltr"
                       placeholder="phone" class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
            </div>
        </div>
    </x-crm::section-card>

    {{-- ─── ۷) تصاویر مراحل ─── --}}
    <x-crm::section-card sectionKey="steps-images" title="تصاویر سکشن مراحل خدمات" icon="🪜"
        description="override تصاویر steps برای این برند (خالی = template)">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium mb-1">URL تصویر دسکتاپ</label>
                <input type="text" name="steps_image_desktop" value="{{ old('steps_image_desktop', $brand->steps_image_desktop ?? '') }}" maxlength="500" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">URL تصویر موبایل</label>
                <input type="text" name="steps_image_mobile" value="{{ old('steps_image_mobile', $brand->steps_image_mobile ?? '') }}" maxlength="500" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
            </div>
        </div>
    </x-crm::section-card>

    {{-- ─── ۸) دستگاه‌ها ─── --}}
    <x-crm::section-card sectionKey="devices" title="دستگاه‌های پشتیبانی" icon="🔧"
        description="دستگاه‌هایی که این برند تعمیر می‌کند"
        count="{{ $selectedDevicesCount }}">
        @include('crm::partials.multi-picker', [
            'name'        => 'device_ids',
            'items'       => $allDevices ?? collect(),
            'selectedIds' => $selectedDeviceIds ?? [],
            'label'       => 'دستگاه‌های قابل پشتیبانی',
            'help'        => 'خالی = همه‌ی دستگاه‌های فعال نمایش داده می‌شوند.',
            'emptyText'   => 'دستگاه فعالی موجود نیست.',
        ])
    </x-crm::section-card>

    {{-- ─── ۹) دسته‌بندی FAQ ─── --}}
    <x-crm::section-card sectionKey="faq-categories" title="دسته‌بندی سوالات متداول" icon="🏷️"
        description="با انتخاب دسته، تمام سوالات منتشرشده‌ی آن خودکار نمایش داده می‌شوند"
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
            'name'        => 'faq_category_ids',
            'items'       => $faqCatItems,
            'selectedIds' => $selectedFaqCategoryIds ?? [],
            'columns'     => 'wide',
            'label'       => 'دسته‌های FAQ',
            'help'        => 'مدیریت دسته‌ها: <a href="'.route('site.admin.taxonomies.index', 'faq').'" target="_blank">/admin/site/taxonomies/faq</a>',
            'emptyText'   => 'دسته‌بندی فعالی برای FAQ تعریف نشده.',
        ])
    </x-crm::section-card>

    {{-- ─── ۱۰) سوالات منفرد ─── --}}
    <x-crm::section-card sectionKey="faqs" title="سوالات متداول این برند" icon="❓"
        description="سوالات منفرد از بانک FAQ"
        count="{{ $selectedFaqCount }}">
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
            'label'       => 'سوالات اختصاصی',
            'help'        => 'مدیریت بانک: <a href="'.route('site.admin.faqs.index').'" target="_blank">/admin/site/faqs</a>',
            'emptyText'   => 'بانک FAQ خالی است.',
        ])
    </x-crm::section-card>

    {{-- ─── ۱۱) دیدگاه‌ها ─── --}}
    <x-crm::section-card sectionKey="reviews" title="دیدگاه‌های نمایش‌داده‌شده" icon="💬"
        description="نظرات صوتی/متنی انتخاب‌شده از بانک نظرات"
        count="{{ $selectedReviewsCount }}">
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
            'name'        => 'review_ids',
            'items'       => $reviewItems,
            'selectedIds' => $selectedReviewIds ?? [],
            'columns'     => 'wide',
            'label'       => 'انتخاب از بانک نظرات',
            'help'        => 'مدیریت بانک: <a href="'.route('site.admin.reviews.index').'" target="_blank">/admin/site/reviews</a>',
            'emptyText'   => 'دیدگاه تأییدشده‌ای در بانک نیست.',
        ])
    </x-crm::section-card>

    {{-- ─── ۱۲) ویدیوها ─── --}}
    <x-crm::section-card sectionKey="videos" title="ویدیوهای اختصاصی این برند" icon="🎬"
        description="aparat_id / youtube_id / mp4 مستقیم (اولویت با aparat). خالی = template"
        count="{{ $videosCount }}">
        @include('crm::partials.json-repeater', [
            'name' => 'videos',
            'label' => 'لیست ویدیوها',
            'items' => old('videos', $brand->videos ?? []),
            'item_fields' => [
                'title' => ['label' => 'عنوان ویدیو', 'type' => 'string'],
                'aparat_id' => ['label' => 'Aparat ID', 'type' => 'string'],
                'youtube_id' => ['label' => 'YouTube ID', 'type' => 'string'],
                'video_url' => ['label' => 'URL مستقیم (mp4)', 'type' => 'string'],
                'description' => ['label' => 'توضیح کوتاه', 'type' => 'textarea'],
                'poster_url' => ['label' => 'تصویر cover — انتخاب از مخزن مدیا', 'type' => 'image'],
            ],
        ])
    </x-crm::section-card>

    {{-- ─── ۱۳) سئو ─── --}}
    <x-crm::section-card sectionKey="seo" title="سئو (Meta)" icon="🔍"
        description="Meta Title و Meta Description مخصوص این برند">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Meta Title</label>
                <input type="text" name="meta_title" value="{{ old('meta_title', $brand->meta_title ?? '') }}" maxlength="200"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Meta Description</label>
                <textarea name="meta_description" rows="2" maxlength="500"
                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ old('meta_description', $brand->meta_description ?? '') }}</textarea>
            </div>
        </div>
    </x-crm::section-card>

    {{-- ─── فیلدهای legacy مخفی (هنوز submit می‌شوند) ─── --}}
    <div style="display:none">
        @include('crm::partials.json-repeater', [
            'name' => 'stats',
            'label' => 'کارت‌های آمار (legacy)',
            'items' => old('stats', $brand->stats ?? []),
            'item_fields' => [
                'value' => ['label' => 'مقدار', 'type' => 'string'],
                'label' => ['label' => 'برچسب', 'type' => 'string'],
            ],
        ])
        @include('crm::partials.json-repeater', [
            'name' => 'issues',
            'label' => 'مشکلات (legacy)',
            'items' => old('issues', $brand->issues ?? []),
            'item_fields' => [
                'title' => ['label' => 'عنوان', 'type' => 'string'],
                'description' => ['label' => 'توضیح', 'type' => 'textarea'],
                'icon' => ['label' => 'آیکن', 'type' => 'string'],
            ],
        ])
        @include('crm::partials.json-repeater', [
            'name' => 'why_us',
            'label' => 'چرا ما (legacy)',
            'items' => old('why_us', $brand->why_us ?? []),
            'item_fields' => [
                'title' => ['label' => 'عنوان', 'type' => 'string'],
                'description' => ['label' => 'توضیح', 'type' => 'textarea'],
                'icon' => ['label' => 'آیکن', 'type' => 'string'],
            ],
        ])
        @include('crm::partials.json-repeater', [
            'name' => 'faq',
            'label' => 'سوالات inline (legacy)',
            'items' => old('faq', $brand->faq ?? []),
            'item_fields' => [
                'question' => ['label' => 'سوال', 'type' => 'string'],
                'answer' => ['label' => 'پاسخ', 'type' => 'textarea'],
            ],
        ])
        <textarea name="warranty_text" rows="3">{{ old('warranty_text', $brand->warranty_text ?? '') }}</textarea>
        <textarea name="support_info" rows="3">{{ old('support_info', $brand->support_info ?? '') }}</textarea>
    </div>

</div>
