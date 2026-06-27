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
use Modules\Site\Support\InlineMediaUrl;
use Modules\Site\Support\MediaUrl;

/**
 * Catalog endpoints برای برندها.
 *
 * `show()` پاسخ section-based برمی‌گرداند — ساختار مشابه CatalogDeviceController
 * با این تفاوت که سکشن «brands» اینجا جای خود را به «devices» می‌دهد
 * (لیست دستگاه‌هایی که این برند تعمیر می‌شود).
 */
class CatalogBrandController extends Controller
{
    public function __construct(private PageSectionService $sections) {}

    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 50);
        $limit = max(1, min($limit, 100));

        $query = Brand::query()->active()->ordered();

        if ($request->boolean('featured')) {
            $query->featured();
        }

        $deviceSlug = trim((string) $request->query('device', ''));
        if ($deviceSlug !== '') {
            $query->whereHas('devices', function ($q) use ($deviceSlug) {
                $q->where('crm_devices.slug', $deviceSlug)
                    ->where('crm_devices.is_active', true);
            });
        }

        $brands = $query->limit($limit)->get(['id', 'name', 'slug', 'logo']);

        $data = $brands->map(fn (Brand $b) => [
            'id' => (int) $b->id,
            'name' => $b->name,
            'slug' => $b->slug,
            'logo' => MediaUrl::resolve($b->logo),
        ])->values();

        $total = Brand::query()->where('is_active', true)->count();

        return response()
            ->json([
                'data' => $data,
                'meta' => ['total' => $total],
            ])
            ->header('Cache-Control', 'public, max-age=600, s-maxage=600');
    }

    /**
     * GET /v1/catalog/brands/{slug} — جزئیات یک برند.
     *
     * پاسخ section-based: { id, slug, label, sections: { hero, steps, live_activity, content, faq, devices, testimonials, seo } }
     */
    public function show(string $slug): JsonResponse
    {
        $brand = Brand::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $brand) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $context = [
            'brand' => $brand->name,
            'brand_slug' => $brand->slug,
            'page_title' => $brand->name,
        ];
        $template = $this->sections->pageExists('brand')
            ? $this->sections->loadForPublic('brand', $context)
            : [];

        $sectionsEnabled = (array) ($brand->sections_enabled ?? []);
        $enabled = fn (string $key, bool $default = true): bool => (bool) ($sectionsEnabled[$key] ?? $default);

        return response()
            ->json([
                'id' => (int) $brand->id,
                'slug' => $brand->slug,
                'label' => $brand->name,
                'logo' => MediaUrl::resolve($brand->logo),
                'tone' => $brand->tone,
                'bg' => $brand->bg,

                'meta_title' => CatalogMerger::pick($brand->meta_title, $template['seo']['meta_title'] ?? null),
                'meta_description' => CatalogMerger::pick($brand->meta_description, $template['seo']['meta_description'] ?? null),

                'sections' => [
                    'hero' => $this->buildHero($brand, $template, $enabled('hero', true)),
                    'steps' => $this->buildSteps($brand, $template, $enabled('steps', true)),
                    'live_activity' => [
                        'enabled' => $enabled('live_activity', true),
                        'brand_slug' => $brand->slug,
                    ],
                    'content' => [
                        'enabled' => $enabled('content', true),
                        'html' => InlineMediaUrl::absolutize(
                            CatalogMerger::pick($brand->description, $template['content']['html'] ?? null),
                            request()->getSchemeAndHttpHost()
                        ),
                    ],
                    'faq' => array_merge(
                        ['enabled' => $enabled('faq', true)],
                        $this->buildFaq($brand, $template),
                    ),
                    'devices' => [
                        'enabled' => $enabled('devices', true),
                        'items' => $this->buildDevices($brand),
                    ],
                    'testimonials' => [
                        'enabled' => $enabled('testimonials', true),
                        'items' => $this->buildTestimonials($brand, $template),
                    ],
                    'videos' => [
                        'enabled' => $enabled('videos', true),
                        'title' => $template['videos']['title'] ?? null,
                        'subtitle' => $template['videos']['subtitle'] ?? null,
                        'items' => $this->buildVideos($brand, $template, $context),
                    ],
                    'forum_questions' => [
                        'enabled' => $enabled('forum_questions', true),
                        'title' => $template['forum_questions']['title'] ?? null,
                        'subtitle' => $template['forum_questions']['subtitle'] ?? null,
                        'see_all_label' => $template['forum_questions']['see_all_label'] ?? null,
                        'see_all_url' => '/forum?brand='.$brand->slug,
                        'items' => \Modules\Site\Support\ForumQuestionFeed::forBrand((int) $brand->id, 5),
                    ],
                ],
            ])
            ->header('Cache-Control', 'public, max-age=600, s-maxage=600');
    }

    /**
     * منطق: ابتدا videos اختصاصی برند (JSON روی entity)، در صورت خالی → template.videos.items.
     * در هر دو حالت `{brand}` و دیگر placeholderها روی متن‌ها اعمال می‌شوند.
     *
     * @param  array<string, string>  $context
     * @return array<int, array<string, mixed>>
     */
    private function buildVideos(Brand $brand, array $template, array $context = []): array
    {
        $entity = is_array($brand->videos) ? array_values(array_filter($brand->videos, fn ($v) => is_array($v) && ! empty(array_filter($v)))) : [];
        if ($entity !== []) {
            $hydrated = $context === [] ? $entity : $this->sections->applyPlaceholders($entity, $context);

            return $this->shapeVideos($hydrated);
        }
        // template.videos.items از قبل توسط loadForPublic placeholder خورده — دوباره نخور
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

    private function buildHero(Brand $brand, array $template, bool $enabled): array
    {
        $heroTpl = $template['hero'] ?? [];
        $ctaPrimaryTpl = (array) ($heroTpl['cta_primary'] ?? []);
        $ctaSecondaryTpl = (array) ($heroTpl['cta_secondary'] ?? []);

        return [
            'enabled' => $enabled,
            'badge' => CatalogMerger::pick($brand->eyebrow, $heroTpl['badge'] ?? null),
            'title' => CatalogMerger::pick(null, $heroTpl['title'] ?? $brand->name),
            'subtitle' => CatalogMerger::pick($brand->subtitle, $heroTpl['subtitle'] ?? null),
            'caption' => CatalogMerger::pick($brand->caption, $heroTpl['caption'] ?? null),
            'tagline' => CatalogMerger::pick($brand->tagline, null),
            'image' => $this->mergeHeroImage($brand->hero_image ?? null, $heroTpl['image'] ?? null),
            'cta_primary' => [
                'label' => CatalogMerger::pick($brand->cta_primary_label, $ctaPrimaryTpl['label'] ?? null),
                'url' => CatalogMerger::pick($brand->cta_primary_url, $ctaPrimaryTpl['url'] ?? null),
                'icon' => CatalogMerger::pick($brand->cta_primary_icon, $ctaPrimaryTpl['icon'] ?? null),
            ],
            'cta_secondary' => [
                'label' => CatalogMerger::pick($brand->cta_secondary_label, $ctaSecondaryTpl['label'] ?? null),
                'url' => CatalogMerger::pick($brand->cta_secondary_url, $ctaSecondaryTpl['url'] ?? null),
                'icon' => CatalogMerger::pick($brand->cta_secondary_icon, $ctaSecondaryTpl['icon'] ?? null),
            ],
        ];
    }

    /**
     * Hero image — entity hero_image اولویت بالاتر دارد؛
     * هر slot به‌صورت مستقل merge می‌شود (مثلاً اگر برند فقط desktop داشته باشد،
     * mobile از template می‌آید).
     *
     * @param  mixed  $entity  مقدار hero_image از crm_brands/crm_devices (array | null)
     * @param  mixed  $template  مقدار image از hero template (array | null)
     */
    private function mergeHeroImage($entity, $template): array
    {
        $svc = \Modules\Site\Services\PageSectionService::class;
        $e = $svc::normalizeHeroVisual($entity);
        $t = $svc::normalizeHeroVisual($template);
        $out = [];
        foreach (['desktop_left', 'desktop_right', 'mobile'] as $slot) {
            $out[$slot] = [
                'url' => $e[$slot]['url'] ?: $t[$slot]['url'],
                'alt' => $e[$slot]['alt'] ?: $t[$slot]['alt'],
            ];
        }

        return $out;
    }

    private function buildSteps(Brand $brand, array $template, bool $enabled): array
    {
        $stepsTpl = $template['steps'] ?? [];
        $tplImage = \Modules\Site\Services\PageSectionService::normalizeResponsiveImage($stepsTpl['image'] ?? null);

        return [
            'enabled' => $enabled,
            'image_desktop' => MediaUrl::resolve(
                CatalogMerger::pick($brand->steps_image_desktop, $tplImage['desktop']['url'])
            ),
            'image_mobile' => MediaUrl::resolve(
                CatalogMerger::pick($brand->steps_image_mobile, $tplImage['mobile']['url'])
            ),
            'alt' => $stepsTpl['alt'] ?? null,
        ];
    }

    /**
     * FAQ گروه‌بندی‌شده بر اساس دسته‌بندی (tab) + لیست مسطح items.
     * owner: brand. legacy: ستون JSON brand->faq. fallback: template.
     *
     * @return array{items: array<int, array<string, mixed>>, categories: array<int, array<string, mixed>>}
     */
    private function buildFaq(Brand $brand, array $template): array
    {
        return \Modules\Site\Support\FaqSectionBuilder::build(
            [$brand],
            is_array($brand->faq) ? $brand->faq : [],
            CatalogMerger::templateFaq($template),
            CatalogMerger::templateFaqCategories($template),
        );
    }

    /**
     * منبعِ یگانه‌ی دستگاه‌های مرتبط = صفحات ترکیبیِ فعال (combo-manager).
     * اگر برند هیچ صفحه‌ی ترکیبی‌ای نداشته باشد → fallbackِ legacy
     * (pivot crm_device_brands، سپس همه‌ی دستگاه‌های فعال).
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildDevices(Brand $brand): array
    {
        // وضعیتِ صفحات ترکیبیِ این برند: device_id => is_active
        $comboRows = \Modules\CRM\Models\DeviceBrandPage::query()
            ->where('brand_id', $brand->id)
            ->pluck('is_active', 'device_id');

        if ($comboRows->isNotEmpty()) {
            // combo-manager این برند را مدیریت می‌کند → فقط دستگاه‌های combo-فعال.
            $activeDeviceIds = $comboRows->filter()->keys()->all();
            if (empty($activeDeviceIds)) {
                return [];
            }
            $picked = Device::query()
                ->whereIn('id', $activeDeviceIds)
                ->where('is_active', true)
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'icon', 'thumbnail', 'tone']);
        } else {
            // legacy: pivot per-brand، سپس همه‌ی دستگاه‌های فعال.
            $picked = $brand->devices()
                ->where('crm_devices.is_active', true)
                ->get(['crm_devices.id', 'crm_devices.name', 'crm_devices.slug', 'crm_devices.icon', 'crm_devices.thumbnail', 'crm_devices.tone']);
            if ($picked->isEmpty()) {
                $picked = Device::query()
                    ->where('is_active', true)
                    ->orderByDesc('is_featured')
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug', 'icon', 'thumbnail', 'tone']);
            }
        }

        return $picked->map(fn ($d) => [
            'id' => (int) $d->id,
            'label' => $d->name,
            'slug' => $d->slug,
            'href' => '/devices/'.$d->slug,
            // صفحه‌ی ترکیبیِ این دستگاه × برند.
            'combo_href' => '/services/'.$d->slug.'/'.$brand->slug,
            'combo_active' => (bool) ($comboRows[$d->id] ?? false),
            'icon' => $d->icon,
            'thumbnail' => MediaUrl::resolve($d->thumbnail),
            'tone' => $d->tone,
        ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildTestimonials(Brand $brand, array $template): array
    {
        // context برای جایگزینیِ placeholderهای موضوع ({brand} و …).
        $ctx = [
            'brand' => $brand->name,
            'brand_slug' => $brand->slug,
        ];

        $picked = $brand->reviews()
            ->where('site_reviews.status', Review::STATUS_APPROVED)
            ->get();

        if ($picked->isEmpty()) {
            $tplItems = (array) ($template['testimonials']['testimonial_ids_items'] ?? []);
            if (! empty($tplItems)) {
                return array_map(fn ($t) => [
                    'id' => $t['id'] ?? null,
                    'type' => Review::TYPE_AUDIO,
                    'author_name' => $t['customer_name'] ?? null,
                    'topic' => \Modules\Site\Support\ReviewTopic::fill($t['topic'] ?? null, $ctx),
                    'rating' => isset($t['rating']) ? (int) $t['rating'] : null,
                    'audio_url' => $t['audio_url'] ?? null,
                    'duration_seconds' => $t['duration_seconds'] ?? null,
                    'content' => null,
                ], $tplItems);
            }

            // fallback نهایی: audio generic approved
            $picked = Review::query()
                ->audio()
                ->approved()
                ->whereDoesntHave('devices')
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->limit(12)
                ->get();
        }

        return $picked->map(fn (Review $r) => [
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
}
