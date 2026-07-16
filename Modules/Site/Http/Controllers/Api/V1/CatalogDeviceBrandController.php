<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceBrandPage;
use Modules\Site\Models\Review;
use Modules\Site\Services\PageSectionService;
use Modules\Site\Support\CatalogMerger;
use Modules\Site\Support\InlineMediaUrl;
use Modules\Site\Support\MediaUrl;

/**
 * Catalog endpoint برای صفحه‌ی ترکیبی (device, brand) — URL مانند
 * /devices/dishwasher/samsung. ساختار section-based با fallback
 *  per-pair → per-device → per-brand → template.
 *
 * سکشن brand_other_devices دستگاه‌های کمبو-فعالِ این برند (combo-manager)
 * را برمی‌گرداند (برای کارُسل brand_devices در فرانت).
 */
class CatalogDeviceBrandController extends Controller
{
    public function __construct(private PageSectionService $sections) {}

    public function show(string $deviceSlug, string $brandSlug): JsonResponse
    {
        $device = Device::query()->where('slug', $deviceSlug)->where('is_active', true)->first();
        $brand = Brand::query()->where('slug', $brandSlug)->where('is_active', true)->first();

        if (! $device || ! $brand) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        // منبعِ یگانه‌ی رابطه‌ی دستگاه↔برند: صفحه‌ی ترکیبیِ فعال (combo-manager).
        // بدونِ fallbackِ legacyِ pivot — اگر صفحه‌ی ترکیبیِ فعالی برای این جفت
        // نباشد، صفحه وجود ندارد (۴۰۴). این منبع را با combo-manager یکی می‌کند.
        $page = DeviceBrandPage::query()
            ->where('device_id', $device->id)
            ->where('brand_id', $brand->id)
            ->first();

        if (! $page || ! $page->is_active) {
            return response()->json(['message' => 'این صفحه‌ی ترکیبی فعال نیست.'], 404);
        }

        // Template با placeholder substitution — اولویت با device_brand
        // (الگوی اختصاصی ترکیبی) و fallback به template device استاندارد.
        $context = [
            'device' => $device->short_name ?? $device->name,
            'device_label' => $device->name,
            'device_slug' => $device->slug,
            'brand' => $brand->name,
            'brand_slug' => $brand->slug,
            'page_title' => ($device->service_name ?? $device->name).' '.$brand->name,
        ];
        $hasComboTemplate = $this->sections->pageExists('device_brand');
        $template = $hasComboTemplate
            ? $this->sections->loadForPublic('device_brand', $context)
            : ($this->sections->pageExists('device')
                ? $this->sections->loadForPublic('device', $context)
                : []);

        // الگوی اختصاصیِ ترکیبیِ همین دستگاه (device_brand:{slug}) — تیمِ محتوا
        // یک‌بار برای هر دستگاه می‌نویسد (با {brand}) و همه‌ی صفحاتِ ترکیبیِ آن
        // دستگاه یکسان می‌شوند. اولویت: per-pair → «الگوی این دستگاه» → فیلدهای
        // device/brand → الگوی سراسری. (اگر الگوی دستگاه بعد از device میامد،
        // description/عنوانِ خودِ دستگاه همیشه سایه می‌انداخت و محتوای واردشده
        // هرگز نمایش داده نمی‌شد.)
        $deviceCombo = [];
        if ($hasComboTemplate) {
            $deviceCombo = $this->sections->loadForPublic(
                PageSectionService::deviceComboSlug($device->slug),
                $context
            );
            if ($deviceCombo !== []) {
                $template = $this->mergeTemplates($deviceCombo, $template);
            }
        }

        // Fallbackِ hero از «صفحه‌ی دستگاه»: وقتی الگوی ترکیبی (device_brand) جدا از
        // الگوی دستگاه است و hero ترکیبی خالی می‌ماند، همان تصویری که صفحه‌ی دستگاه
        // نشان می‌دهد (device.hero_image یا الگوی device) استفاده شود — تا هیرو خالی نماند.
        $deviceHeroImage = ($hasComboTemplate && $this->sections->pageExists('device'))
            ? ($this->sections->loadForPublic('device', $context)['hero']['image'] ?? null)
            : null;

        $sectionsEnabled = array_replace(
            (array) ($brand->sections_enabled ?? []),
            (array) ($device->sections_enabled ?? []),
            (array) ($page?->sections_enabled ?? [])
        );
        $enabled = fn (string $key, bool $default = true): bool => (bool) ($sectionsEnabled[$key] ?? $default);

        return response()
            ->json([
                'device' => [
                    'id' => (int) $device->id,
                    'slug' => $device->slug,
                    'label' => $device->name,
                    'short_name' => $device->short_name,
                    'icon' => $device->icon,
                    'thumbnail' => MediaUrl::resolve($device->thumbnail),
                    'tone' => $device->tone,
                ],
                'brand' => [
                    'id' => (int) $brand->id,
                    'slug' => $brand->slug,
                    'label' => $brand->name,
                    'logo' => MediaUrl::resolve($brand->logo),
                ],

                'meta_title' => $this->merge(
                    $page?->meta_title,
                    $deviceCombo['seo']['meta_title'] ?? null,
                    $device->meta_title,
                    $brand->meta_title,
                    $template['seo']['meta_title'] ?? null
                ),
                'meta_description' => $this->merge(
                    $page?->meta_description,
                    $deviceCombo['seo']['meta_description'] ?? null,
                    $device->meta_description,
                    $brand->meta_description,
                    $template['seo']['meta_description'] ?? null
                ),

                'sections' => [
                    'hero' => $this->buildHero($page, $device, $brand, $template, $enabled('hero', true), $deviceHeroImage, $deviceCombo),
                    'steps' => $this->buildSteps($page, $device, $brand, $template, $enabled('steps', true), $deviceCombo),
                    'live_activity' => [
                        'enabled' => $enabled('live_activity', true),
                        'device_slug' => $device->slug,
                        'brand_slug' => $brand->slug,
                    ],
                    'content' => [
                        'enabled' => $enabled('content', true),
                        'html' => InlineMediaUrl::normalize(
                            $this->merge(
                                $page?->description,
                                $deviceCombo['content']['html'] ?? null,
                                $device->description,
                                $brand->description,
                                $template['content']['html'] ?? null
                            ),
                            request()->getSchemeAndHttpHost()
                        ),
                    ],
                    'faq' => array_merge(
                        ['enabled' => $enabled('faq', true)],
                        $this->buildFaq($page, $device, $brand, $template),
                    ),
                    'brand_other_devices' => [
                        'enabled' => $enabled('brand_other_devices', true),
                        'current_slug' => $device->slug,
                        'brand' => [
                            'slug' => $brand->slug,
                            'name' => $brand->name,
                        ],
                        'items' => $this->buildBrandOtherDevices($brand),
                    ],
                    'testimonials' => [
                        'enabled' => $enabled('testimonials', true),
                        'items' => $this->buildTestimonials($page, $device, $brand, $template, $deviceCombo),
                    ],
                    'videos' => [
                        'enabled' => $enabled('videos', true),
                        'title' => $template['videos']['title'] ?? null,
                        'subtitle' => $template['videos']['subtitle'] ?? null,
                        'items' => $this->buildVideos($device, $brand, $template, $context, $deviceCombo),
                    ],
                    'forum_questions' => [
                        'enabled' => $enabled('forum_questions', true),
                        'title' => $template['forum_questions']['title'] ?? null,
                        'subtitle' => $template['forum_questions']['subtitle'] ?? null,
                        'see_all_label' => $template['forum_questions']['see_all_label'] ?? null,
                        'see_all_url' => '/forum?device='.$device->slug.'&brand='.$brand->slug,
                        'items' => \Modules\Site\Support\ForumQuestionFeed::forDeviceBrand((int) $device->id, (int) $brand->id, 5),
                    ],
                    'related_articles' => [
                        'enabled' => $enabled('related_articles', true),
                        'title' => $template['related_articles']['title'] ?? null,
                        'subtitle' => $template['related_articles']['subtitle'] ?? null,
                        'items' => \Modules\Site\Support\RelatedArticles::forCombo((int) $device->id, (int) $brand->id),
                    ],
                ],
            ])
            ->header('Cache-Control', 'public, max-age=600, s-maxage=600');
    }

    /**
     * ادغامِ الگوی per-device روی الگوی سراسری — مقدارِ غیرخالیِ override می‌بَرد،
     * خالی‌ها از base می‌آیند. لیست‌ها (repeater مثل videos.items یا faq_ids)
     * وقتی override چیزی دارد، یک‌جا جایگزین می‌شوند تا آیتم‌ها قاطی نشوند.
     *
     * @param  array<string, mixed>  $override
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function mergeTemplates(array $override, array $base): array
    {
        if (array_is_list($override) || array_is_list($base)) {
            return $override !== [] ? $override : $base;
        }

        $out = $base;
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $out[$key] = $this->mergeTemplates($value, $base[$key]);
            } elseif ($value !== null && $value !== '' && $value !== []) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * انتخاب اولین مقدار non-empty از زنجیره — معادل ?? اما با CatalogMerger::pick.
     */
    private function merge(...$values)
    {
        foreach ($values as $v) {
            $picked = CatalogMerger::pick($v, null);
            if ($picked !== null) {
                return $picked;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $deviceCombo  سکشن‌های الگوی اختصاصیِ این
     *                                             دستگاه — بعد از per-pair و قبل از
     *                                             فیلدهای device/brand اولویت دارد.
     */
    private function buildHero(?DeviceBrandPage $page, Device $device, Brand $brand, array $template, bool $enabled, $deviceTemplateImage = null, array $deviceCombo = []): array
    {
        $heroTpl = $template['hero'] ?? [];
        $ctaPrimaryTpl = (array) ($heroTpl['cta_primary'] ?? []);
        $ctaSecondaryTpl = (array) ($heroTpl['cta_secondary'] ?? []);
        $dc = (array) ($deviceCombo['hero'] ?? []);
        $dcCtaPrimary = (array) ($dc['cta_primary'] ?? []);
        $dcCtaSecondary = (array) ($dc['cta_secondary'] ?? []);

        return [
            'enabled' => $enabled,
            'badge' => $this->merge($page?->eyebrow, $dc['badge'] ?? null, $device->eyebrow, $brand->eyebrow, $heroTpl['badge'] ?? null),
            'title' => $this->merge(
                $page?->title,
                $dc['title'] ?? null,
                $device->service_name,
                $heroTpl['title'] ?? null,
                ($device->name.' '.$brand->name)
            ),
            'subtitle' => $this->merge($page?->subtitle, $dc['subtitle'] ?? null, $device->subtitle, $brand->subtitle, $heroTpl['subtitle'] ?? null),
            'caption' => $this->merge($page?->caption, $dc['caption'] ?? null, $device->caption, $brand->caption, $heroTpl['caption'] ?? null),
            'image' => $this->mergeHeroImage($dc['image'] ?? null, $device->hero_image ?? null, $brand->hero_image ?? null, $heroTpl['image'] ?? null, $deviceTemplateImage),
            'cta_primary' => [
                'label' => $this->merge($page?->cta_primary_label, $dcCtaPrimary['label'] ?? null, $device->cta_primary_label, $brand->cta_primary_label, $ctaPrimaryTpl['label'] ?? null),
                'url' => $this->merge($page?->cta_primary_url, $dcCtaPrimary['url'] ?? null, $device->cta_primary_url, $brand->cta_primary_url, $ctaPrimaryTpl['url'] ?? null),
                'icon' => $this->merge($page?->cta_primary_icon, $dcCtaPrimary['icon'] ?? null, $device->cta_primary_icon, $brand->cta_primary_icon, $ctaPrimaryTpl['icon'] ?? null),
            ],
            'cta_secondary' => [
                'label' => $this->merge($page?->cta_secondary_label, $dcCtaSecondary['label'] ?? null, $device->cta_secondary_label, $brand->cta_secondary_label, $ctaSecondaryTpl['label'] ?? null),
                'url' => $this->merge($page?->cta_secondary_url, $dcCtaSecondary['url'] ?? null, $device->cta_secondary_url, $brand->cta_secondary_url, $ctaSecondaryTpl['url'] ?? null),
                'icon' => $this->merge($page?->cta_secondary_icon, $dcCtaSecondary['icon'] ?? null, $device->cta_secondary_icon, $brand->cta_secondary_icon, $ctaSecondaryTpl['icon'] ?? null),
            ],
        ];
    }

    /**
     * Hero image — اولویت: الگوی اختصاصیِ دستگاه (deviceComboImg) > device.hero_image
     * > brand.hero_image > template.hero.image > الگوی صفحه‌ی دستگاه (deviceTemplate).
     * هر slot به‌صورت مستقل merge می‌شود؛ deviceTemplate تضمین می‌کند اگر همه خالی
     * بودند همان هیروِ صفحه‌ی دستگاه بیاید.
     *
     * @param  mixed  $deviceComboImg
     * @param  mixed  $deviceImg
     * @param  mixed  $brandImg
     * @param  mixed  $template
     * @param  mixed  $deviceTemplate
     */
    private function mergeHeroImage($deviceComboImg, $deviceImg, $brandImg, $template, $deviceTemplate = null): array
    {
        $svc = \Modules\Site\Services\PageSectionService::class;
        $chain = [
            $svc::normalizeHeroVisual($deviceComboImg),
            $svc::normalizeHeroVisual($deviceImg),
            $svc::normalizeHeroVisual($brandImg),
            $svc::normalizeHeroVisual($template),
            $svc::normalizeHeroVisual($deviceTemplate),
        ];
        $out = [];
        foreach (['desktop_left', 'desktop_right', 'mobile'] as $slot) {
            $out[$slot] = ['url' => null, 'alt' => null];
            foreach (['url', 'alt'] as $field) {
                foreach ($chain as $candidate) {
                    if (! empty($candidate[$slot][$field])) {
                        $out[$slot][$field] = $candidate[$slot][$field];
                        break;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $deviceCombo
     */
    private function buildSteps(?DeviceBrandPage $page, Device $device, Brand $brand, array $template, bool $enabled, array $deviceCombo = []): array
    {
        $stepsTpl = $template['steps'] ?? [];
        $tplImage = \Modules\Site\Services\PageSectionService::normalizeResponsiveImage($stepsTpl['image'] ?? null);
        $dcSteps = (array) ($deviceCombo['steps'] ?? []);
        $dcImage = \Modules\Site\Services\PageSectionService::normalizeResponsiveImage($dcSteps['image'] ?? null);

        return [
            'enabled' => $enabled,
            'image_desktop' => MediaUrl::resolve($this->merge(
                $page?->steps_image_desktop,
                $dcImage['desktop']['url'],
                $device->steps_image_desktop,
                $brand->steps_image_desktop,
                $tplImage['desktop']['url']
            )),
            'image_mobile' => MediaUrl::resolve($this->merge(
                $page?->steps_image_mobile,
                $dcImage['mobile']['url'],
                $device->steps_image_mobile,
                $brand->steps_image_mobile,
                $tplImage['mobile']['url']
            )),
            'alt' => $this->merge($dcSteps['alt'] ?? null, $stepsTpl['alt'] ?? null),
        ];
    }

    /**
     * FAQ گروه‌بندی‌شده بر اساس دسته‌بندی (tab) + لیست مسطح items.
     * owners به ترتیب اولویت: page → device → brand. legacy: device->faq.
     * fallback: template.
     *
     * @return array{items: array<int, array<string, mixed>>, categories: array<int, array<string, mixed>>}
     */
    private function buildFaq(?DeviceBrandPage $page, Device $device, Brand $brand, array $template): array
    {
        $tplItems = CatalogMerger::templateFaq($template);
        $tplCats = CatalogMerger::templateFaqCategories($template);

        // صفحه‌ی ترکیبی: اگر الگوی اختصاصیِ «device_brand» وجود دارد و محتوا دارد،
        // بر FAQِ سطحِ دستگاه/برند مقدم است — چون با {device} {brand} نوشته شده و
        // مخصوصِ همین ترکیب است (وگرنه FAQِ سطحِ دستگاه که فقط {device} دارد سایه
        // می‌اندازد و برند گم می‌شود). فقط FAQِ per-pairِ خودِ صفحه (page owner) از
        // الگوی ترکیبی مهم‌تر است.
        $hasComboTemplate = $this->sections->pageExists('device_brand')
            && (! empty($tplItems) || ! empty($tplCats));

        if ($hasComboTemplate) {
            if ($page) {
                $pageResult = \Modules\Site\Support\FaqSectionBuilder::build([$page]);
                if (! empty($pageResult['items'])) {
                    return $pageResult;
                }
            }

            return ['items' => $tplItems, 'categories' => $tplCats];
        }

        // حالتِ معمول (الگوی device_brand نیست → fallback به الگوی device):
        // owner (page → device → brand) → template → legacy
        return \Modules\Site\Support\FaqSectionBuilder::build(
            [$page, $device, $brand],
            is_array($device->faq) ? $device->faq : [],
            $tplItems,
            $tplCats,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildBrandOtherDevices(Brand $brand): array
    {
        // منبعِ یگانه = combo-manager (DeviceBrandPageِ فعالِ این برند). بدونِ
        // pivotِ legacy، تا با بقیه‌ی سایت هماهنگ باشد.
        $activeDeviceIds = DeviceBrandPage::query()
            ->where('brand_id', $brand->id)
            ->where('is_active', true)
            ->pluck('device_id')
            ->all();
        if (empty($activeDeviceIds)) {
            return [];
        }

        $devices = Device::query()
            ->whereIn('id', $activeDeviceIds)
            ->where('is_active', true)
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'short_name', 'icon', 'thumbnail', 'tone']);

        return $devices->map(fn ($d) => [
            'id' => (int) $d->id,
            'name' => $d->name,
            'shortName' => $d->short_name,
            'slug' => $d->slug,
            'iconKey' => $d->icon,
            'thumbnail' => MediaUrl::resolve($d->thumbnail),
            'tone' => $d->tone,
        ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    /**
     * @param  array<string, mixed>  $deviceCombo
     */
    private function buildTestimonials(?DeviceBrandPage $page, Device $device, Brand $brand, array $template, array $deviceCombo = []): array
    {
        // context برای جایگزینیِ placeholderهای موضوع ({device}، {brand} و …).
        $ctx = [
            'device' => $device->short_name ?? $device->name,
            'device_label' => $device->name,
            'device_slug' => $device->slug,
            'brand' => $brand->name,
            'brand_slug' => $brand->slug,
        ];

        // اولویت ۱: page
        if ($page) {
            $picked = $page->reviews()->where('site_reviews.status', Review::STATUS_APPROVED)->get();
            if ($picked->isNotEmpty()) {
                return $this->shapeReviews($picked, $ctx);
            }
        }

        // اولویت ۲: الگوی اختصاصیِ این دستگاه (انتخابِ تیمِ محتوا برای صفحاتِ ترکیبی)
        $dcItems = (array) ($deviceCombo['testimonials']['testimonial_ids_items'] ?? []);
        if (! empty($dcItems)) {
            return $this->shapeTemplateReviews($dcItems, $ctx);
        }

        // اولویت ۳: device
        $picked = $device->reviews()->where('site_reviews.status', Review::STATUS_APPROVED)->get();
        if ($picked->isNotEmpty()) {
            return $this->shapeReviews($picked, $ctx);
        }

        // اولویت ۴: brand
        $picked = $brand->reviews()->where('site_reviews.status', Review::STATUS_APPROVED)->get();
        if ($picked->isNotEmpty()) {
            return $this->shapeReviews($picked, $ctx);
        }

        // اولویت ۵: template سراسری
        $tplItems = (array) ($template['testimonials']['testimonial_ids_items'] ?? []);
        if (! empty($tplItems)) {
            return $this->shapeTemplateReviews($tplItems, $ctx);
        }

        // اولویت نهایی: audio generic
        $picked = Review::query()->audio()->approved()
            ->whereDoesntHave('devices')->whereDoesntHave('brands')
            ->orderByDesc('is_featured')->limit(12)->get();

        return $this->shapeReviews($picked, $ctx);
    }

    /**
     * نگاشتِ آیتم‌های hydrate‌شده‌ی الگو (testimonial_ids_items) به خروجیِ استاندارد.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, string|null>  $ctx
     * @return array<int, array<string, mixed>>
     */
    private function shapeTemplateReviews(array $items, array $ctx): array
    {
        return array_map(fn ($t) => [
            'id' => $t['id'] ?? null,
            'type' => Review::TYPE_AUDIO,
            'author_name' => $t['customer_name'] ?? null,
            'topic' => \Modules\Site\Support\ReviewTopic::fill($t['topic'] ?? null, $ctx),
            'rating' => isset($t['rating']) ? (int) $t['rating'] : null,
            'audio_url' => $t['audio_url'] ?? null,
            'duration_seconds' => $t['duration_seconds'] ?? null,
            'content' => null,
        ], $items);
    }

    /**
     * @param  array<string, string|null>  $ctx
     */
    private function shapeReviews($collection, array $ctx = []): array
    {
        return $collection->map(fn (Review $r) => [
            'id' => $r->id,
            'type' => $r->type,
            'author_name' => $r->author_name,
            'topic' => \Modules\Site\Support\ReviewTopic::fill($r->topic, $ctx),
            'rating' => (int) $r->rating,
            'audio_url' => $r->audio_url,
            'duration_seconds' => $r->duration_seconds,
            'content' => $r->content,
        ])->all();
    }

    /**
     * منطق videos برای صفحه‌ی ترکیبی:
     *   الگوی اختصاصیِ دستگاه > device.videos > brand.videos > template.videos.items
     * placeholderهای `{device}` و `{brand}` روی همه‌ی سطح‌ها اعمال می‌شوند.
     *
     * @param  array<string, string>  $context
     * @param  array<string, mixed>  $deviceCombo
     * @return array<int, array<string, mixed>>
     */
    private function buildVideos(Device $device, Brand $brand, array $template, array $context, array $deviceCombo = []): array
    {
        $deviceFallback = \Modules\Site\Support\VideoDate::modelFallback($device);
        $brandFallback = \Modules\Site\Support\VideoDate::modelFallback($brand);

        // الگوی اختصاصیِ این دستگاه — از قبل placeholder خورده (loadForPublic).
        $dcVideos = array_values(array_filter((array) ($deviceCombo['videos']['items'] ?? []), fn ($v) => is_array($v) && ! empty(array_filter($v))));
        if ($dcVideos !== []) {
            return $this->shapeVideos($dcVideos, $deviceFallback);
        }

        $deviceVideos = is_array($device->videos) ? array_values(array_filter($device->videos, fn ($v) => is_array($v) && ! empty(array_filter($v)))) : [];
        if ($deviceVideos !== []) {
            return $this->shapeVideos($this->sections->applyPlaceholders($deviceVideos, $context), $deviceFallback);
        }

        $brandVideos = is_array($brand->videos) ? array_values(array_filter($brand->videos, fn ($v) => is_array($v) && ! empty(array_filter($v)))) : [];
        if ($brandVideos !== []) {
            return $this->shapeVideos($this->sections->applyPlaceholders($brandVideos, $context), $brandFallback);
        }

        // template.videos.items از قبل توسط loadForPublic placeholder خورده
        $tpl = (array) ($template['videos']['items'] ?? []);

        return $this->shapeVideos($tpl, $deviceFallback);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function shapeVideos(array $rows, ?string $fallbackDate = null): array
    {
        $rows = array_values(array_filter($rows, fn ($r) => is_array($r)));

        return array_map(fn (array $r) => [
            'title' => $r['title'] ?? null,
            'aparat_id' => $r['aparat_id'] ?? null,
            'youtube_id' => $r['youtube_id'] ?? null,
            'video_url' => $r['video_url'] ?? null,
            'description' => $r['description'] ?? null,
            'poster_url' => $r['poster_url'] ?? null,
            // uploadDate برای اسکیمای VideoObject (ISO 8601).
            'upload_date' => \Modules\Site\Support\VideoDate::iso($r, $fallbackDate),
        ], $rows);
    }
}
