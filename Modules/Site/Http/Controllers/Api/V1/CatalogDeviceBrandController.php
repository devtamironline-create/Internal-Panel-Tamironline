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
 * سکشن brand_other_devices لیست تمام دستگاه‌های پشتیبانی‌شده توسط
 * این برند را برمی‌گرداند (برای کارُسل brand_devices در فرانت).
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

        // اطمینان از اینکه این برند، این دستگاه را پشتیبانی می‌کند
        $supports = $brand->devices()->where('crm_devices.id', $device->id)->exists();
        if (! $supports) {
            return response()->json(['message' => 'این برند این دستگاه را پشتیبانی نمی‌کند.'], 404);
        }

        $page = DeviceBrandPage::query()
            ->where('device_id', $device->id)
            ->where('brand_id', $brand->id)
            ->where('is_active', true)
            ->first();

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
        $template = $this->sections->pageExists('device_brand')
            ? $this->sections->loadForPublic('device_brand', $context)
            : ($this->sections->pageExists('device')
                ? $this->sections->loadForPublic('device', $context)
                : []);

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
                    $device->meta_title,
                    $brand->meta_title,
                    $template['seo']['meta_title'] ?? null
                ),
                'meta_description' => $this->merge(
                    $page?->meta_description,
                    $device->meta_description,
                    $brand->meta_description,
                    $template['seo']['meta_description'] ?? null
                ),

                'sections' => [
                    'hero' => $this->buildHero($page, $device, $brand, $template, $enabled('hero', true)),
                    'steps' => $this->buildSteps($page, $device, $brand, $template, $enabled('steps', true)),
                    'live_activity' => [
                        'enabled' => $enabled('live_activity', true),
                        'device_slug' => $device->slug,
                        'brand_slug' => $brand->slug,
                    ],
                    'content' => [
                        'enabled' => $enabled('content', true),
                        'html' => InlineMediaUrl::absolutize(
                            $this->merge(
                                $page?->description,
                                $device->description,
                                $brand->description,
                                $template['content']['html'] ?? null
                            ),
                            request()->getSchemeAndHttpHost()
                        ),
                    ],
                    'faq' => [
                        'enabled' => $enabled('faq', true),
                        'items' => $this->buildFaq($page, $device, $brand, $template),
                    ],
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
                        'items' => $this->buildTestimonials($page, $device, $brand, $template),
                    ],
                    'videos' => [
                        'enabled' => $enabled('videos', true),
                        'title' => $template['videos']['title'] ?? null,
                        'subtitle' => $template['videos']['subtitle'] ?? null,
                        'items' => $this->buildVideos($device, $brand, $template, $context),
                    ],
                    'forum_questions' => [
                        'enabled' => $enabled('forum_questions', true),
                        'title' => $template['forum_questions']['title'] ?? null,
                        'subtitle' => $template['forum_questions']['subtitle'] ?? null,
                        'see_all_label' => $template['forum_questions']['see_all_label'] ?? null,
                        'see_all_url' => '/forum?device='.$device->slug.'&brand='.$brand->slug,
                        'items' => \Modules\Site\Support\ForumQuestionFeed::forDeviceBrand((int) $device->id, (int) $brand->id, 5),
                    ],
                ],
            ])
            ->header('Cache-Control', 'public, max-age=600, s-maxage=600');
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

    private function buildHero(?DeviceBrandPage $page, Device $device, Brand $brand, array $template, bool $enabled): array
    {
        $heroTpl = $template['hero'] ?? [];
        $ctaPrimaryTpl = (array) ($heroTpl['cta_primary'] ?? []);
        $ctaSecondaryTpl = (array) ($heroTpl['cta_secondary'] ?? []);

        return [
            'enabled' => $enabled,
            'badge' => $this->merge($page?->eyebrow, $device->eyebrow, $brand->eyebrow, $heroTpl['badge'] ?? null),
            'title' => $this->merge(
                $page?->title,
                $device->service_name,
                $heroTpl['title'] ?? null,
                ($device->name.' '.$brand->name)
            ),
            'subtitle' => $this->merge($page?->subtitle, $device->subtitle, $brand->subtitle, $heroTpl['subtitle'] ?? null),
            'caption' => $this->merge($page?->caption, $device->caption, $brand->caption, $heroTpl['caption'] ?? null),
            'image' => $this->mergeHeroImage($device->hero_image ?? null, $brand->hero_image ?? null, $heroTpl['image'] ?? null),
            'cta_primary' => [
                'label' => $this->merge($page?->cta_primary_label, $device->cta_primary_label, $brand->cta_primary_label, $ctaPrimaryTpl['label'] ?? null),
                'url' => $this->merge($page?->cta_primary_url, $device->cta_primary_url, $brand->cta_primary_url, $ctaPrimaryTpl['url'] ?? null),
                'icon' => $this->merge($page?->cta_primary_icon, $device->cta_primary_icon, $brand->cta_primary_icon, $ctaPrimaryTpl['icon'] ?? null),
            ],
            'cta_secondary' => [
                'label' => $this->merge($page?->cta_secondary_label, $device->cta_secondary_label, $brand->cta_secondary_label, $ctaSecondaryTpl['label'] ?? null),
                'url' => $this->merge($page?->cta_secondary_url, $device->cta_secondary_url, $brand->cta_secondary_url, $ctaSecondaryTpl['url'] ?? null),
                'icon' => $this->merge($page?->cta_secondary_icon, $device->cta_secondary_icon, $brand->cta_secondary_icon, $ctaSecondaryTpl['icon'] ?? null),
            ],
        ];
    }

    /**
     * Hero image — اولویت: device.hero_image > brand.hero_image > template.hero.image
     * هر slot به‌صورت مستقل merge می‌شود.
     *
     * @param  mixed  $deviceImg
     * @param  mixed  $brandImg
     * @param  mixed  $template
     */
    private function mergeHeroImage($deviceImg, $brandImg, $template): array
    {
        $svc = \Modules\Site\Services\PageSectionService::class;
        $d = $svc::normalizeHeroVisual($deviceImg);
        $b = $svc::normalizeHeroVisual($brandImg);
        $t = $svc::normalizeHeroVisual($template);
        $out = [];
        foreach (['desktop_left', 'desktop_right', 'mobile'] as $slot) {
            $out[$slot] = [
                'url' => $d[$slot]['url'] ?: ($b[$slot]['url'] ?: $t[$slot]['url']),
                'alt' => $d[$slot]['alt'] ?: ($b[$slot]['alt'] ?: $t[$slot]['alt']),
            ];
        }

        return $out;
    }

    private function buildSteps(?DeviceBrandPage $page, Device $device, Brand $brand, array $template, bool $enabled): array
    {
        $stepsTpl = $template['steps'] ?? [];
        $tplImage = \Modules\Site\Services\PageSectionService::normalizeResponsiveImage($stepsTpl['image'] ?? null);

        return [
            'enabled' => $enabled,
            'image_desktop' => MediaUrl::resolve($this->merge(
                $page?->steps_image_desktop,
                $device->steps_image_desktop,
                $brand->steps_image_desktop,
                $tplImage['desktop']['url']
            )),
            'image_mobile' => MediaUrl::resolve($this->merge(
                $page?->steps_image_mobile,
                $device->steps_image_mobile,
                $brand->steps_image_mobile,
                $tplImage['mobile']['url']
            )),
            'alt' => $stepsTpl['alt'] ?? null,
        ];
    }

    /**
     * منطق FAQ:
     *  ۱) union(categories, individual) از page
     *  ۲) union(categories, individual) از device
     *  ۳) union(categories, individual) از brand
     *  ۴) inline JSON device->faq
     *  ۵) template
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildFaq(?DeviceBrandPage $page, Device $device, Brand $brand, array $template): array
    {
        foreach ([$page, $device, $brand] as $owner) {
            if (! $owner) {
                continue;
            }
            $merged = $this->faqsForOwner($owner);
            if ($merged->isNotEmpty()) {
                return $merged->map(fn ($f) => [
                    'id' => $f->id,
                    'question' => $f->question,
                    'answer' => $f->answer,
                ])->all();
            }
        }

        if (! empty($device->faq)) {
            return $device->faq;
        }

        return CatalogMerger::templateFaq($template);
    }

    private function faqsForOwner($owner)
    {
        $byCategory = collect();
        if ($owner->faqCategories()->exists()) {
            $catIds = $owner->faqCategories()->pluck('site_taxonomies.id')->all();
            $byCategory = \Modules\Site\Models\Faq::query()
                ->where('is_published', true)
                ->whereHas('taxonomies', fn ($q) => $q->whereIn('site_taxonomies.id', $catIds))
                ->orderBy('sort_order')
                ->orderByDesc('created_at')
                ->get(['id', 'question', 'answer']);
        }
        $byIndividual = $owner->faqs()
            ->where('faqs.is_published', true)
            ->get(['faqs.id', 'faqs.question', 'faqs.answer']);

        return $byCategory->concat($byIndividual)->unique('id')->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildBrandOtherDevices(Brand $brand): array
    {
        $devices = $brand->devices()
            ->where('crm_devices.is_active', true)
            ->orderByDesc('crm_devices.is_featured')
            ->orderBy('crm_devices.sort_order')
            ->orderBy('crm_devices.name')
            ->get(['crm_devices.id', 'crm_devices.name', 'crm_devices.slug', 'crm_devices.short_name', 'crm_devices.icon', 'crm_devices.thumbnail', 'crm_devices.tone']);

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
    private function buildTestimonials(?DeviceBrandPage $page, Device $device, Brand $brand, array $template): array
    {
        // اولویت ۱: page
        if ($page) {
            $picked = $page->reviews()->where('site_reviews.status', Review::STATUS_APPROVED)->get();
            if ($picked->isNotEmpty()) {
                return $this->shapeReviews($picked);
            }
        }

        // اولویت ۲: device
        $picked = $device->reviews()->where('site_reviews.status', Review::STATUS_APPROVED)->get();
        if ($picked->isNotEmpty()) {
            return $this->shapeReviews($picked);
        }

        // اولویت ۳: brand
        $picked = $brand->reviews()->where('site_reviews.status', Review::STATUS_APPROVED)->get();
        if ($picked->isNotEmpty()) {
            return $this->shapeReviews($picked);
        }

        // اولویت ۴: template
        $tplItems = (array) ($template['testimonials']['testimonial_ids_items'] ?? []);
        if (! empty($tplItems)) {
            return array_map(fn ($t) => [
                'id' => $t['id'] ?? null,
                'type' => Review::TYPE_AUDIO,
                'author_name' => $t['customer_name'] ?? null,
                'topic' => $t['topic'] ?? null,
                'rating' => isset($t['rating']) ? (int) $t['rating'] : null,
                'audio_url' => $t['audio_url'] ?? null,
                'duration_seconds' => $t['duration_seconds'] ?? null,
                'content' => null,
            ], $tplItems);
        }

        // اولویت نهایی: audio generic
        $picked = Review::query()->audio()->approved()
            ->whereDoesntHave('devices')->whereDoesntHave('brands')
            ->orderByDesc('is_featured')->limit(12)->get();

        return $this->shapeReviews($picked);
    }

    private function shapeReviews($collection): array
    {
        return $collection->map(fn (Review $r) => [
            'id' => $r->id,
            'type' => $r->type,
            'author_name' => $r->author_name,
            'topic' => $r->topic,
            'rating' => (int) $r->rating,
            'audio_url' => $r->audio_url,
            'duration_seconds' => $r->duration_seconds,
            'content' => $r->content,
        ])->all();
    }

    /**
     * منطق videos برای صفحه‌ی ترکیبی:
     *   device.videos > brand.videos > template.videos.items
     * placeholderهای `{device}` و `{brand}` روی هر سه سطح اعمال می‌شوند.
     *
     * @param  array<string, string>  $context
     * @return array<int, array<string, mixed>>
     */
    private function buildVideos(Device $device, Brand $brand, array $template, array $context): array
    {
        $deviceVideos = is_array($device->videos) ? array_values(array_filter($device->videos, fn ($v) => is_array($v) && ! empty(array_filter($v)))) : [];
        if ($deviceVideos !== []) {
            return $this->shapeVideos($this->sections->applyPlaceholders($deviceVideos, $context));
        }

        $brandVideos = is_array($brand->videos) ? array_values(array_filter($brand->videos, fn ($v) => is_array($v) && ! empty(array_filter($v)))) : [];
        if ($brandVideos !== []) {
            return $this->shapeVideos($this->sections->applyPlaceholders($brandVideos, $context));
        }

        // template.videos.items از قبل توسط loadForPublic placeholder خورده
        $tpl = (array) ($template['videos']['items'] ?? []);

        return $this->shapeVideos($tpl);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function shapeVideos(array $rows): array
    {
        $rows = array_values(array_filter($rows, fn ($r) => is_array($r)));

        return array_map(fn (array $r) => [
            'title' => $r['title'] ?? null,
            'aparat_id' => $r['aparat_id'] ?? null,
            'youtube_id' => $r['youtube_id'] ?? null,
            'video_url' => $r['video_url'] ?? null,
            'description' => $r['description'] ?? null,
            'poster_url' => $r['poster_url'] ?? null,
        ], $rows);
    }
}
