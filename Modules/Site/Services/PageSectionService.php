<?php

namespace Modules\Site\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Site\Models\PageSection;

/**
 * مدیریت محتوای سکشن‌های صفحات سایت بر اساس schema در config.
 *
 * منبع حقیقت برای ساختار: config('site.page-sections').
 * منبع حقیقت برای مقادیر:  جدول site_page_sections.
 */
class PageSectionService
{
    /**
     * بازگرداندن schema کامل (یا یک صفحه‌ی خاص).
     */
    public function schema(?string $pageSlug = null): array
    {
        $all = config('site.page-sections', []);
        if ($pageSlug === null) {
            return $all;
        }

        return $all[$pageSlug] ?? [];
    }

    /**
     * فهرست صفحات تعریف‌شده در schema (slug => title).
     *
     * @return array<string, string>
     */
    public function pages(): array
    {
        return collect($this->schema())
            ->mapWithKeys(fn ($v, $k) => [$k => $v['title'] ?? $k])
            ->all();
    }

    /**
     * آیا صفحه‌ی داده‌شده در schema تعریف شده است؟
     */
    public function pageExists(string $pageSlug): bool
    {
        return array_key_exists($pageSlug, $this->schema());
    }

    /**
     * بازگرداندن سکشن‌های تعریف‌شده برای یک صفحه.
     */
    public function sectionsOf(string $pageSlug): array
    {
        return $this->schema($pageSlug)['sections'] ?? [];
    }

    /**
     * بازگرداندن مقادیر فعلی سکشن‌های یک صفحه به‌صورت
     *   [section_key => ['payload' => array, 'is_published' => bool]]
     *
     * سکشن‌های بدون رکورد در DB با payload خالی برمی‌گردند.
     */
    public function loadForAdmin(string $pageSlug): array
    {
        $rows = PageSection::query()
            ->forPage($pageSlug)
            ->get(['section_key', 'payload', 'is_published'])
            ->keyBy('section_key');

        $out = [];
        foreach ($this->sectionsOf($pageSlug) as $sectionKey => $def) {
            $row = $rows->get($sectionKey);
            $out[$sectionKey] = [
                'payload' => $row?->payload ?? [],
                'is_published' => $row ? (bool) $row->is_published : true,
            ];
        }

        return $out;
    }

    /**
     * بازگرداندن مقادیر public برای API — فقط سکشن‌های منتشرشده،
     * بدون فیلدهای ادمین، با hydrate کردن مراجع (faqs/testimonials/brands).
     *
     * @return array<string, mixed>
     */
    public function loadForPublic(string $pageSlug, array $context = []): array
    {
        $rows = PageSection::query()
            ->forPage($pageSlug)
            ->where('is_published', true)
            ->get(['section_key', 'payload'])
            ->keyBy('section_key');

        $out = [];
        foreach ($this->sectionsOf($pageSlug) as $sectionKey => $def) {
            $row = $rows->get($sectionKey);
            if (! $row) {
                continue; // سکشن‌های پر نشده یا منتشر نشده در public نمی‌آیند
            }
            $payload = (array) $row->payload;
            $hydrated = $this->hydrateReferences($payload, $def['fields'] ?? []);
            $out[$sectionKey] = $this->applyPlaceholders($hydrated, $context);
        }

        // ─── Auto-fallback مخصوص hero.services در صفحه‌ی home ───
        if ($pageSlug === 'home' && isset($out['hero'])) {
            $items = $out['hero']['services_items'] ?? [];
            if (empty($items)) {
                $out['hero']['services_items'] = $this->defaultDevicesForHero();
            }
            $out['hero']['services_total'] = (int) \Modules\CRM\Models\Device::query()
                ->where('is_active', true)
                ->count();
        }

        return $out;
    }

    /**
     * Placeholder‌های پشتیبانی‌شده:
     *   {device}, {device_slug}, {page_title}
     *
     * Context مثلاً برای صفحه‌ی دستگاه از /v1/devices/{slug} پاس داده می‌شود:
     *   ['device' => 'لباس‌شویی', 'device_slug' => 'washing-machine']
     *
     * @param  mixed  $value
     * @param  array<string, string>  $context
     */
    private function applyPlaceholders($value, array $context)
    {
        if ($context === []) {
            return $value;
        }

        if (is_string($value)) {
            $replacements = [];
            foreach ($context as $k => $v) {
                $replacements['{'.$k.'}'] = (string) $v;
            }

            return strtr($value, $replacements);
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->applyPlaceholders($v, $context);
            }

            return $out;
        }

        return $value;
    }

    /**
     * اعتبارسنجی و ذخیره‌سازی محتوای تمام سکشن‌های یک صفحه.
     *
     * @param  array<string, array{payload?: array, is_published?: bool}>  $input
     *
     * @throws ValidationException
     */
    public function saveAll(string $pageSlug, array $input): void
    {
        if (! $this->pageExists($pageSlug)) {
            abort(404, 'Page schema not found.');
        }

        $sections = $this->sectionsOf($pageSlug);
        $validated = [];

        foreach ($sections as $sectionKey => $def) {
            $sectionInput = $input[$sectionKey] ?? ['payload' => [], 'is_published' => false];
            $payload = (array) ($sectionInput['payload'] ?? []);
            $isPublished = (bool) ($sectionInput['is_published'] ?? false);

            $cleanPayload = $this->validateSection(
                $pageSlug,
                $sectionKey,
                $def['fields'] ?? [],
                $payload
            );

            $validated[$sectionKey] = [
                'payload' => $cleanPayload,
                'is_published' => $isPublished,
            ];
        }

        foreach ($validated as $sectionKey => $data) {
            PageSection::query()->updateOrCreate(
                ['page_slug' => $pageSlug, 'section_key' => $sectionKey],
                ['payload' => $data['payload'], 'is_published' => $data['is_published']]
            );
        }
    }

    /**
     * اعتبارسنجی payload یک سکشن بر اساس fields. فقط فیلدهای schema
     * نگه داشته می‌شوند؛ موارد ناشناخته نادیده گرفته می‌شوند.
     *
     * @throws ValidationException
     */
    private function validateSection(string $pageSlug, string $sectionKey, array $fields, array $payload): array
    {
        $result = $this->processFields($fields, $payload);

        $validator = Validator::make($result['data'], $result['rules'], []);

        if ($validator->fails()) {
            $prefixed = [];
            foreach ($validator->errors()->toArray() as $field => $errs) {
                $prefixed["sections.{$sectionKey}.payload.{$field}"] = $errs;
            }
            throw ValidationException::withMessages($prefixed);
        }

        return $result['data'];
    }

    /**
     * پردازش بازگشتی فیلدها — برای سکشن و group های تو در تو.
     * در همان فراخوانی، `data` نرمالایز شده و `rules` با کلیدهای dot-notation
     * برمی‌گردد. group ها به‌صورت زیرشاخه‌ی همان data بازمی‌گردند.
     *
     * @param  array<string, array>  $fields
     * @param  array<string, mixed>  $payload
     * @return array{data: array<string, mixed>, rules: array<string, string>}
     */
    private function processFields(array $fields, array $payload, string $prefix = ''): array
    {
        $rules = [];
        $data = [];

        foreach ($fields as $key => $field) {
            $type = $field['type'] ?? 'string';
            $fullKey = $prefix === '' ? $key : "{$prefix}.{$key}";
            $baseRules = $field['rules'] ?? 'nullable';

            if ($type === 'group') {
                $subPayload = Arr::get($payload, $key, []);
                if (! is_array($subPayload)) {
                    $subPayload = [];
                }
                $sub = $this->processFields($field['fields'] ?? [], $subPayload, $fullKey);
                $data[$key] = $sub['data'];
                foreach ($sub['rules'] as $rk => $rv) {
                    $rules[$rk] = $rv;
                }

                continue;
            }

            if ($type === 'repeater') {
                $items = Arr::get($payload, $key, []);
                if (! is_array($items)) {
                    $items = [];
                }
                $items = array_values(array_filter($items, fn ($i) => is_array($i) && $this->hasMeaningfulValues($i)));
                $data[$key] = $items;

                $rules[$fullKey] = 'nullable|array';
                foreach (($field['item_fields'] ?? []) as $itemKey => $itemDef) {
                    $itemRule = $itemDef['rules'] ?? 'nullable';
                    if (($itemDef['type'] ?? null) === 'bool') {
                        $itemRule = str_replace('required', 'nullable', $itemRule);
                    }
                    $rules["{$fullKey}.*.{$itemKey}"] = $itemRule;
                }

                continue;
            }

            if ($type === 'reference') {
                $ids = Arr::get($payload, $key, []);
                $source = $field['source'] ?? null;
                if (! is_array($ids)) {
                    $ids = [];
                }
                $ids = array_values(array_filter($ids, fn ($v) => $v !== null && $v !== ''));
                if (in_array($source, ['brands', 'devices'], true)) {
                    $ids = array_values(array_unique(array_map('intval', $ids)));
                }
                $data[$key] = $ids;
                $rules[$fullKey] = 'nullable|array';
                $rules["{$fullKey}.*"] = $this->referenceItemRule($source);

                continue;
            }

            if ($type === 'banner_zone') {
                $value = Arr::get($payload, $key);
                $slug = is_string($value) ? trim($value) : null;
                $data[$key] = ($slug !== null && $slug !== '') ? $slug : null;
                $rules[$fullKey] = 'nullable|string|max:120|exists:site_banner_zones,slug,is_active,1';

                continue;
            }

            if ($type === 'responsive_image') {
                $value = Arr::get($payload, $key, []);
                if (! is_array($value)) {
                    $value = [];
                }
                $desktop = isset($value['desktop']) && is_string($value['desktop']) ? trim($value['desktop']) : null;
                $mobile = isset($value['mobile']) && is_string($value['mobile']) ? trim($value['mobile']) : null;
                $data[$key] = [
                    'desktop' => $desktop !== '' ? $desktop : null,
                    'mobile' => $mobile !== '' ? $mobile : null,
                ];
                $rules["{$fullKey}.desktop"] = 'nullable|site_url|max:500';
                $rules["{$fullKey}.mobile"] = 'nullable|site_url|max:500';

                continue;
            }

            if ($type === 'bool') {
                $data[$key] = (bool) Arr::get($payload, $key, false);
                $rules[$fullKey] = 'nullable|boolean';

                continue;
            }

            if ($type === 'int') {
                $value = Arr::get($payload, $key);
                $data[$key] = ($value === null || $value === '') ? null : (int) $value;
                $rules[$fullKey] = $baseRules;

                continue;
            }

            // string | textarea | url | image_url | select
            $value = Arr::get($payload, $key);
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    $value = null;
                }
            }
            $data[$key] = $value;
            $rules[$fullKey] = $baseRules;
        }

        return ['data' => $data, 'rules' => $rules];
    }

    /**
     * قاعده‌ی validation برای IDهای reference بسته به منبع.
     */
    private function referenceItemRule(?string $source): string
    {
        return match ($source) {
            'faqs' => 'string|exists:faqs,id',
            'testimonials' => 'string|exists:site_reviews,id',
            'brands' => 'integer|exists:crm_brands,id',
            'devices' => 'integer|exists:crm_devices,id',
            'device_categories' => 'integer|exists:crm_device_categories,id',
            'faq_categories' => 'integer|exists:site_taxonomies,id',
            'testimonial_categories' => 'integer|exists:site_taxonomies,id',
            default => 'string',
        };
    }

    /**
     * آیا آرایه‌ی item حداقل یک مقدار غیرخالی دارد؟ (برای حذف ردیف‌های خالی repeater)
     */
    private function hasMeaningfulValues(array $item): bool
    {
        foreach ($item as $v) {
            if (is_string($v) && trim($v) !== '') {
                return true;
            }
            if (is_numeric($v)) {
                return true;
            }
            if (is_bool($v) && $v) {
                return true;
            }
            if (is_array($v) && $v !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * جایگزینی IDهای reference با داده‌ی hydrate شده از مخازن مربوطه.
     * بازگشتی — برای group های تو در تو هم اعمال می‌شود.
     */
    private function hydrateReferences(array $payload, array $fields): array
    {
        foreach ($fields as $key => $field) {
            $type = $field['type'] ?? null;

            if ($type === 'group') {
                $subPayload = $payload[$key] ?? [];
                if (is_array($subPayload)) {
                    $payload[$key] = $this->hydrateReferences($subPayload, $field['fields'] ?? []);
                }

                continue;
            }

            if ($type === 'banner_zone') {
                $slug = is_string($payload[$key] ?? null) ? trim($payload[$key]) : null;
                $payload[$key.'_data'] = $slug !== null && $slug !== ''
                    ? $this->resolveBannerZone($slug)
                    : null;

                continue;
            }

            if ($type !== 'reference') {
                continue;
            }
            $ids = (array) ($payload[$key] ?? []);
            if ($ids === []) {
                $payload[$key.'_items'] = [];

                continue;
            }
            $payload[$key.'_items'] = $this->resolveReference($field['source'] ?? null, $ids);
        }

        return $payload;
    }

    /**
     * Inline hydration برای یک Banner Zone:
     *  - از همان cache key استفاده می‌کنیم که BannerController public از آن استفاده می‌کند
     *    (`banners:zone:{slug}`) → صفر duplication، invalidate یکپارچه از طریق observer بنر.
     *  - شکل خروجی دقیقاً مثل /v1/banners/{slug} است.
     *
     * @return array<string, mixed>|null null اگر زون وجود نداشت یا غیرفعال بود.
     */
    private function resolveBannerZone(string $zoneSlug): ?array
    {
        $key = \Modules\Site\Support\BannerCache::keyForZone($zoneSlug);

        return \Illuminate\Support\Facades\Cache::remember($key, 60, function () use ($zoneSlug) {
            $zone = \Modules\Site\Models\BannerZone::query()
                ->active()
                ->where('slug', $zoneSlug)
                ->first();
            if (! $zone) {
                return null;
            }
            $banners = \Modules\Site\Models\Banner::query()
                ->live()
                ->where('zone_id', $zone->id)
                ->with(['media.variants', 'mediaMobile.variants'])
                ->orderBy('sort_order')
                ->orderByDesc('created_at')
                ->get();

            return [
                'zone' => [
                    'slug' => $zone->slug,
                    'name' => $zone->name,
                    'recommended' => $zone->recommended_width && $zone->recommended_height
                        ? ['width' => $zone->recommended_width, 'height' => $zone->recommended_height]
                        : null,
                ],
                'banners' => $banners->map(fn (\Modules\Site\Models\Banner $b) => $this->shapeBanner($b))->values()->all(),
                'updated_at' => optional($banners->max('updated_at'))->toIso8601String(),
            ];
        });
    }

    /**
     * شکل سبک یک Banner برای پاسخ public — مطابق BannerController.shape.
     *
     * @return array<string, mixed>
     */
    private function shapeBanner(\Modules\Site\Models\Banner $b): array
    {
        return [
            'id' => $b->id,
            'title' => $b->title,
            'subtitle' => $b->subtitle,
            'link' => [
                'url' => $b->link_url,
                'label' => $b->link_label,
            ],
            'image' => [
                'desktop' => $this->shapeBannerMedia($b->media, $b->image_url),
                'mobile' => $this->shapeBannerMedia($b->mediaMobile, null),
            ],
            'sort_order' => (int) $b->sort_order,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function shapeBannerMedia($media, ?string $fallbackUrl): ?array
    {
        if (! $media) {
            return $fallbackUrl ? ['url' => $fallbackUrl, 'variants' => []] : null;
        }

        return [
            'url' => $media->url(),
            'alt' => $media->alt,
            'width' => $media->width,
            'height' => $media->height,
            'aspect_ratio' => $media->aspect_ratio,
            'variants' => $media->variants->mapWithKeys(fn ($v) => [
                $v->variant => [
                    'url' => $media->variantUrl($v->variant),
                    'width' => (int) $v->width,
                    'height' => (int) $v->height,
                ],
            ])->all(),
        ];
    }

    /**
     * پیدا کردن صفحاتی که در سکشن‌هایشان به یک Banner Zone مشخص ارجاع داده‌اند.
     * توسط BannerObserver فراخوانی می‌شود تا کش page-ها همراه با کش بنر invalidate شود.
     *
     * @return array<int, string> لیست slugهای منحصربه‌فرد صفحات.
     */
    public function pagesUsingBannerZone(string $zoneSlug): array
    {
        // ۱) از schema config، نقشه‌ی [pageSlug => [sectionKey => [bannerZoneFieldKeys]]] را می‌سازیم
        $candidates = [];
        foreach ($this->schema() as $pageSlug => $pageConfig) {
            foreach (($pageConfig['sections'] ?? []) as $sectionKey => $sectionDef) {
                foreach (($sectionDef['fields'] ?? []) as $fieldKey => $fieldDef) {
                    if (($fieldDef['type'] ?? null) === 'banner_zone') {
                        $candidates[$pageSlug][$sectionKey][] = $fieldKey;
                    }
                }
            }
        }

        if ($candidates === []) {
            return [];
        }

        // ۲) فقط ردیف‌های مرتبط را load می‌کنیم
        $sectionKeys = collect($candidates)
            ->flatMap(fn ($sections) => array_keys($sections))
            ->unique()
            ->values()
            ->all();

        $rows = PageSection::query()
            ->whereIn('page_slug', array_keys($candidates))
            ->whereIn('section_key', $sectionKeys)
            ->get(['page_slug', 'section_key', 'payload']);

        // ۳) هر ردیف را بر اساس schema بررسی می‌کنیم
        $matchedPages = [];
        foreach ($rows as $row) {
            $fieldKeys = $candidates[$row->page_slug][$row->section_key] ?? [];
            $payload = is_array($row->payload) ? $row->payload : (array) $row->payload;
            foreach ($fieldKeys as $fk) {
                $value = $payload[$fk] ?? null;
                if (is_string($value) && $value === $zoneSlug) {
                    $matchedPages[$row->page_slug] = true;
                    break;
                }
            }
        }

        return array_keys($matchedPages);
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function resolveReference(?string $source, array $ids): array
    {
        if ($source === 'faqs') {
            $rows = \Modules\Site\Models\Faq::query()
                ->whereIn('id', $ids)
                ->where('is_published', true)
                ->get(['id', 'question', 'answer'])
                ->keyBy('id');

            return collect($ids)
                ->map(fn ($id) => $rows->get($id))
                ->filter()
                ->map(fn ($f) => [
                    'id' => $f->id,
                    'question' => $f->question,
                    'answer' => $f->answer,
                ])
                ->values()
                ->all();
        }

        if ($source === 'testimonials') {
            $rows = \Modules\Site\Models\Review::query()
                ->audio()
                ->whereIn('id', $ids)
                ->where('is_published', true)
                ->get(['id', 'author_name', 'topic', 'rating', 'audio_url', 'duration_seconds', 'published_at'])
                ->keyBy('id');

            return collect($ids)
                ->map(fn ($id) => $rows->get($id))
                ->filter()
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'customer_name' => $t->author_name,
                    'topic' => $t->topic,
                    'rating' => $t->rating,
                    'audio_url' => $t->audio_url,
                    'duration_seconds' => $t->duration_seconds,
                    'published_at' => $t->published_at?->utc()->toIso8601ZuluString(),
                ])
                ->values()
                ->all();
        }

        if ($source === 'brands') {
            $rows = \Modules\CRM\Models\Brand::query()
                ->whereIn('id', $ids)
                ->where('is_active', true)
                ->get(['id', 'name', 'slug', 'logo'])
                ->keyBy('id');

            return collect($ids)
                ->map(fn ($id) => $rows->get((int) $id))
                ->filter()
                ->map(fn ($b) => [
                    'id' => (int) $b->id,
                    'name' => $b->name,
                    'slug' => $b->slug,
                    'logo' => \Modules\Site\Support\MediaUrl::resolve($b->logo),
                ])
                ->values()
                ->all();
        }

        if ($source === 'devices') {
            $rows = \Modules\CRM\Models\Device::query()
                ->whereIn('id', $ids)
                ->where('is_active', true)
                ->get(['id', 'name', 'slug', 'icon', 'thumbnail', 'tone'])
                ->keyBy('id');

            return collect($ids)
                ->map(fn ($id) => $rows->get((int) $id))
                ->filter()
                ->map(fn ($d) => $this->shapeDevice($d))
                ->values()
                ->all();
        }

        if ($source === 'device_categories') {
            $rows = \Modules\CRM\Models\DeviceCategory::query()
                ->whereIn('id', $ids)
                ->where('is_active', true)
                ->get(['id', 'name', 'slug', 'icon', 'tone', 'description'])
                ->keyBy('id');

            return collect($ids)
                ->map(fn ($id) => $rows->get((int) $id))
                ->filter()
                ->map(fn ($c) => [
                    'id' => (int) $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'icon' => $c->icon,
                    'tone' => $c->tone,
                    'description' => $c->description,
                ])
                ->values()
                ->all();
        }

        if ($source === 'faq_categories' || $source === 'testimonial_categories') {
            $type = $source === 'faq_categories'
                ? \Modules\Site\Models\Taxonomy::TYPE_FAQ
                : \Modules\Site\Models\Taxonomy::TYPE_TESTIMONIAL;
            $cats = \Modules\Site\Models\Taxonomy::query()
                ->ofType($type)
                ->active()
                ->whereIn('id', $ids)
                ->ordered()
                ->get(['id', 'slug', 'name']);

            // ترتیب آرایه‌ی ورودی محترم است
            $catById = $cats->keyBy('id');
            $ordered = collect($ids)->map(fn ($id) => $catById->get((int) $id))->filter();

            return $ordered->map(function ($cat) use ($type) {
                if ($type === \Modules\Site\Models\Taxonomy::TYPE_FAQ) {
                    $items = $cat->faqs()
                        ->where('faqs.is_published', true)
                        ->orderBy('faqs.sort_order')
                        ->orderByDesc('faqs.created_at')
                        ->get(['faqs.id', 'faqs.question', 'faqs.answer'])
                        ->map(fn ($f) => [
                            'id' => $f->id,
                            'question' => $f->question,
                            'answer' => $f->answer,
                        ])
                        ->all();
                } else {
                    $items = $cat->reviews()
                        ->where('site_reviews.type', \Modules\Site\Models\Review::TYPE_AUDIO)
                        ->where('site_reviews.is_published', true)
                        ->orderBy('site_reviews.sort_order')
                        ->orderByDesc('site_reviews.published_at')
                        ->get(['site_reviews.id', 'site_reviews.author_name', 'site_reviews.topic', 'site_reviews.rating', 'site_reviews.audio_url', 'site_reviews.duration_seconds', 'site_reviews.published_at'])
                        ->map(fn ($t) => [
                            'id' => $t->id,
                            'customer_name' => $t->author_name,
                            'topic' => $t->topic,
                            'rating' => $t->rating,
                            'audio_url' => $t->audio_url,
                            'duration_seconds' => $t->duration_seconds,
                            'published_at' => $t->published_at?->utc()->toIso8601ZuluString(),
                        ])
                        ->all();
                }

                return [
                    'id' => (int) $cat->id,
                    'slug' => $cat->slug,
                    'label' => $cat->name,
                    'items' => $items,
                ];
            })->values()->all();
        }

        return [];
    }

    /**
     * شکل خروجی API برای یک Device.
     */
    private function shapeDevice(\Modules\CRM\Models\Device $d): array
    {
        return [
            'id' => (int) $d->id,
            'label' => $d->name,
            'slug' => $d->slug,
            'href' => '/devices/'.$d->slug,
            'icon' => $d->icon,
            'thumbnail' => \Modules\Site\Support\MediaUrl::resolve($d->thumbnail ?? null),
            'tone' => $d->tone,
        ];
    }

    /**
     * fallback خودکار: اگر سکشن hero.services خالی باشد، همه‌ی devices فعال
     * را به ترتیب sort_order برمی‌گرداند (با ترجیح is_featured).
     *
     * @return array<int, array<string, mixed>>
     */
    public function defaultDevicesForHero(): array
    {
        return \Modules\CRM\Models\Device::query()
            ->where('is_active', true)
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'slug', 'icon', 'thumbnail', 'tone'])
            ->map(fn ($d) => $this->shapeDevice($d))
            ->all();
    }
}
