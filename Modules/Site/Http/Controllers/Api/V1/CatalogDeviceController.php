<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\Site\Models\Review;
use Modules\Site\Services\PageSectionService;
use Modules\Site\Support\CatalogMerger;
use Modules\Site\Support\MediaUrl;

/**
 * Catalog endpoints برای دستگاه‌ها.
 *
 * `show()` پاسخ section-based برمی‌گرداند. هر سکشن شامل `enabled` و
 * payload مربوطه است. منطق merge برای هر فیلد:
 *   مقدار per-device (روی crm_devices) ?? مقدار global (page_sections.device)
 */
class CatalogDeviceController extends Controller
{
    public function __construct(private PageSectionService $sections) {}

    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 50);
        $limit = max(1, min($limit, 100));

        $query = Device::query()
            ->active()
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->boolean('featured')) {
            $query->featured();
        }

        // فیلتر بر اساس دسته‌بندی والد (slug)
        $categorySlug = trim((string) $request->query('category', ''));
        if ($categorySlug !== '') {
            $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug)->where('is_active', true));
        }

        $devices = $query->with('category:id,name,slug,icon,tone')
            ->limit($limit)
            ->get(['id', 'device_category_id', 'name', 'slug', 'icon', 'thumbnail', 'tone']);

        $data = $devices->map(fn (Device $d) => [
            'id' => (int) $d->id,
            'label' => $d->name,
            'slug' => $d->slug,
            'href' => '/devices/'.$d->slug,
            'icon' => $d->icon,
            'thumbnail' => MediaUrl::resolve($d->thumbnail),
            'tone' => $d->tone,
            'category' => $d->category ? [
                'id' => (int) $d->category->id,
                'name' => $d->category->name,
                'slug' => $d->category->slug,
                'icon' => $d->category->icon,
                'tone' => $d->category->tone,
            ] : null,
        ])->values();

        $total = Device::query()->where('is_active', true)->count();

        return response()
            ->json([
                'data' => $data,
                'meta' => ['total' => $total],
            ])
            ->header('Cache-Control', 'public, max-age=600, s-maxage=600');
    }

    /**
     * GET /v1/catalog/device-categories — لیست دسته‌بندی‌های والد + دستگاه‌های هر دسته.
     * برای منو/گروه‌بندی صفحه‌ی فهرست دستگاه‌ها.
     */
    public function categories(): JsonResponse
    {
        $categories = \Modules\CRM\Models\DeviceCategory::query()
            ->active()
            ->ordered()
            ->with(['devices' => fn ($q) => $q->where('is_active', true)
                ->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('name')])
            ->get(['id', 'name', 'slug', 'icon', 'tone', 'description']);

        $data = $categories->map(fn ($c) => [
            'id' => (int) $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'icon' => $c->icon,
            'tone' => $c->tone,
            'description' => $c->description,
            'devices' => $c->devices->map(fn ($d) => [
                'id' => (int) $d->id,
                'label' => $d->name,
                'slug' => $d->slug,
                'href' => '/devices/'.$d->slug,
                'icon' => $d->icon,
                'thumbnail' => MediaUrl::resolve($d->thumbnail),
                'tone' => $d->tone,
            ])->values(),
        ])->values();

        return response()
            ->json(['data' => $data])
            ->header('Cache-Control', 'public, max-age=600, s-maxage=600');
    }

    /**
     * GET /v1/catalog/devices/{slug}
     *
     * پاسخ section-based: { id, slug, label, sections: { hero, steps, live_activity, content, faq, brands, testimonials, seo } }
     * هر سکشن دارای `enabled` است؛ ادمین می‌تواند per-device غیرفعال کند.
     */
    public function show(string $slug): JsonResponse
    {
        $device = Device::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $device) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        // Template با placeholder substitution
        $context = [
            'device' => $device->short_name ?? $device->name,
            'device_label' => $device->name,
            'device_slug' => $device->slug,
            'page_title' => $device->service_name ?? $device->name,
        ];
        $template = $this->sections->pageExists('device')
            ? $this->sections->loadForPublic('device', $context)
            : [];

        $sectionsEnabled = (array) ($device->sections_enabled ?? []);
        $enabled = fn (string $key, bool $default = true): bool => (bool) ($sectionsEnabled[$key] ?? $default);

        return response()
            ->json([
                'id' => (int) $device->id,
                'slug' => $device->slug,
                'label' => $device->name,
                'icon' => $device->icon,
                'thumbnail' => MediaUrl::resolve($device->thumbnail),
                'tone' => $device->tone,

                'meta_title' => CatalogMerger::pick($device->meta_title, $template['seo']['meta_title'] ?? null),
                'meta_description' => CatalogMerger::pick($device->meta_description, $template['seo']['meta_description'] ?? null),

                'sections' => [
                    'hero' => $this->buildHero($device, $template, $enabled('hero', true)),
                    'steps' => $this->buildSteps($device, $template, $enabled('steps', true)),
                    'live_activity' => [
                        'enabled' => $enabled('live_activity', true),
                        'device_slug' => $device->slug,
                    ],
                    'content' => [
                        'enabled' => $enabled('content', true),
                        'html' => CatalogMerger::pick($device->description, $template['content']['html'] ?? null),
                    ],
                    'faq' => [
                        'enabled' => $enabled('faq', true),
                        'items' => $this->buildFaq($device, $template),
                    ],
                    'brands' => [
                        'enabled' => $enabled('brands', true),
                        'items' => $this->buildBrands($device),
                    ],
                    'testimonials' => [
                        'enabled' => $enabled('testimonials', true),
                        'items' => $this->buildTestimonials($device, $template),
                    ],
                    'videos' => [
                        'enabled' => $enabled('videos', true),
                        'title' => $template['videos']['title'] ?? null,
                        'subtitle' => $template['videos']['subtitle'] ?? null,
                        'items' => $this->buildVideos($device, $template),
                    ],
                ],
            ])
            ->header('Cache-Control', 'public, max-age=600, s-maxage=600');
    }

    /**
     * منطق: ابتدا videos اختصاصی دستگاه (JSON روی entity)، در صورت خالی → template.videos.items.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildVideos(Device $device, array $template): array
    {
        $entity = is_array($device->videos) ? array_values(array_filter($device->videos, fn ($v) => is_array($v) && ! empty(array_filter($v)))) : [];
        if ($entity !== []) {
            return $this->shapeVideos($entity);
        }
        $tpl = (array) ($template['videos']['items'] ?? []);

        return $this->shapeVideos($tpl);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function shapeVideos(array $rows): array
    {
        return array_values(array_map(fn (array $r) => [
            'title' => $r['title'] ?? null,
            'aparat_id' => $r['aparat_id'] ?? null,
            'youtube_id' => $r['youtube_id'] ?? null,
            'video_url' => $r['video_url'] ?? null,
            'description' => $r['description'] ?? null,
            'poster_url' => $r['poster_url'] ?? null,
        ], $rows));
    }

    private function buildHero(Device $device, array $template, bool $enabled): array
    {
        $heroTpl = $template['hero'] ?? [];
        $ctaPrimaryTpl = (array) ($heroTpl['cta_primary'] ?? []);
        $ctaSecondaryTpl = (array) ($heroTpl['cta_secondary'] ?? []);

        return [
            'enabled' => $enabled,
            'badge' => CatalogMerger::pick($device->eyebrow, $heroTpl['badge'] ?? null),
            'title' => CatalogMerger::pick($device->service_name, $heroTpl['title'] ?? $device->name),
            'subtitle' => CatalogMerger::pick($device->subtitle, $heroTpl['subtitle'] ?? null),
            'caption' => CatalogMerger::pick($device->caption, $heroTpl['caption'] ?? null),
            'cta_primary' => [
                'label' => CatalogMerger::pick($device->cta_primary_label, $ctaPrimaryTpl['label'] ?? null),
                'url' => CatalogMerger::pick($device->cta_primary_url, $ctaPrimaryTpl['url'] ?? null),
                'icon' => CatalogMerger::pick($device->cta_primary_icon, $ctaPrimaryTpl['icon'] ?? null),
            ],
            'cta_secondary' => [
                'label' => CatalogMerger::pick($device->cta_secondary_label, $ctaSecondaryTpl['label'] ?? null),
                'url' => CatalogMerger::pick($device->cta_secondary_url, $ctaSecondaryTpl['url'] ?? null),
                'icon' => CatalogMerger::pick($device->cta_secondary_icon, $ctaSecondaryTpl['icon'] ?? null),
            ],
        ];
    }

    private function buildSteps(Device $device, array $template, bool $enabled): array
    {
        $stepsTpl = $template['steps'] ?? [];
        // responsive_image در template به صورت {desktop, mobile} ذخیره می‌شود
        $tplImage = (array) ($stepsTpl['image'] ?? []);

        return [
            'enabled' => $enabled,
            'image_desktop' => MediaUrl::resolve(
                CatalogMerger::pick($device->steps_image_desktop, $tplImage['desktop'] ?? null)
            ),
            'image_mobile' => MediaUrl::resolve(
                CatalogMerger::pick($device->steps_image_mobile, $tplImage['mobile'] ?? null)
            ),
            'alt' => $stepsTpl['alt'] ?? null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildFaq(Device $device, array $template): array
    {
        // اولویت ۱: union(دسته‌بندی‌های انتخاب‌شده، سوالات منفرد انتخاب‌شده) از بانک FAQ
        $byCategory = collect();
        if ($device->faqCategories()->exists()) {
            $catIds = $device->faqCategories()->pluck('site_taxonomies.id')->all();
            $byCategory = \Modules\Site\Models\Faq::query()
                ->where('is_published', true)
                ->whereHas('taxonomies', fn ($q) => $q->whereIn('site_taxonomies.id', $catIds))
                ->orderBy('sort_order')
                ->orderByDesc('created_at')
                ->get(['id', 'question', 'answer']);
        }

        $byIndividual = $device->faqs()
            ->where('faqs.is_published', true)
            ->get(['faqs.id', 'faqs.question', 'faqs.answer']);

        $merged = $byCategory->concat($byIndividual)->unique('id')->values();

        if ($merged->isNotEmpty()) {
            return $merged->map(fn ($f) => [
                'id' => $f->id,
                'question' => $f->question,
                'answer' => $f->answer,
            ])->all();
        }

        // اولویت ۲: inline JSON column device->faq (legacy)
        if (! empty($device->faq)) {
            return $device->faq;
        }

        // اولویت ۳: template (page_sections.device.faq) — از category/faq references
        return CatalogMerger::templateFaq($template);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildBrands(Device $device): array
    {
        // اولویت ۱: انتخاب per-device از pivot crm_device_brands
        $picked = $device->brands()
            ->where('crm_brands.is_active', true)
            ->get(['crm_brands.id', 'crm_brands.name', 'crm_brands.slug', 'crm_brands.logo']);

        // اولویت ۲: همه‌ی برندهای فعال (default = all)
        if ($picked->isEmpty()) {
            $picked = Brand::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'logo']);
        }

        return $picked->map(fn ($b) => [
            'id' => (int) $b->id,
            'name' => $b->name,
            'slug' => $b->slug,
            'logo' => MediaUrl::resolve($b->logo),
        ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildTestimonials(Device $device, array $template): array
    {
        // اولویت ۱: انتخاب per-device از pivot site_review_devices
        $picked = $device->reviews()
            ->where('site_reviews.status', Review::STATUS_APPROVED)
            ->get();

        // اولویت ۲: testimonialهای انتخاب‌شده در template (شناسه‌های hydrated)
        if ($picked->isEmpty()) {
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

            // اولویت ۳: fallback — audio approved+generic (هم منطبق بر این device هم بدون device)
            $picked = Review::query()
                ->audio()
                ->approved()
                ->where(function ($q) use ($device) {
                    $q->whereHas('devices', fn ($qq) => $qq->where('crm_devices.id', $device->id))
                        ->orWhereDoesntHave('devices');
                })
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->limit(12)
                ->get();
        }

        return $picked->map(fn (Review $r) => [
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
}
