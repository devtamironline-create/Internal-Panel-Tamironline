<?php

namespace Modules\Seo\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Seo\Models\SeoFaq;
use Modules\Seo\Models\SeoSetting;

/**
 * تولید JSON-LD (schema.org) برای هر آیتم. خروجی یک لیست از نودهاست که
 * فرانت هرکدام را در یک <script type="application/ld+json"> رندر می‌کند.
 *
 * نودهای پایه (Organization/WebSite/WebPage/BreadcrumbList) همیشه می‌آیند؛
 * نود اختصاصی بر اساس نوع‌محتوا (Article/Service/QAPage/…) اضافه می‌شود؛
 * و اگر ادمین custom_schema گذاشته باشد، عیناً merge می‌شود.
 */
class SchemaGenerator
{
    /**
     * @param  array<string, mixed>  $meta  خروجی MetaResolver::resolve (بدون jsonld)
     * @return list<array<string, mixed>>
     */
    public function generate(string $type, Model $model, array $meta): array
    {
        $graph = [];

        if ($org = $this->organization()) {
            $graph[] = $org;
        }
        $graph[] = $this->website();
        $graph[] = $this->webPage($type, $meta);

        if ($crumbs = $this->breadcrumb($meta)) {
            $graph[] = $crumbs;
        }

        // override در سطح آیتم: seo_meta.schema_type
        //  - 'none'  → نود اختصاصی حذف می‌شود (غیرفعال‌سازی)
        //  - مقدار دیگر → @type نود اختصاصی override می‌شود
        //  - خالی/null → default_schema نوع (config) + مپینگ پیش‌فرض
        $override = $this->schemaTypeOverride($model);
        $disabled = $override !== null && strcasecmp($override, 'none') === 0;
        $resolved = $this->resolveSchemaType($type, $override);

        if (! $disabled && ($specific = $this->typeSpecific($type, $model, $meta, $resolved))) {
            if ($this->valid($specific)) {
                $graph[] = $specific;
            }
        }

        // FAQPage (§38) — فقط وقتی تنظیم faq_schema_enabled روشن است و آیتم
        // پرسش‌های متداولِ فعال دارد.
        if ($faq = $this->faqPage($model)) {
            $graph[] = $faq;
        }

        if ($lb = $this->localBusiness()) {
            $graph[] = $lb;
        }

        // custom schema ادمین (یک نود یا آرایه‌ای از نودها)
        $custom = method_exists($model, 'seoMeta') ? ($model->seoMeta?->custom_schema) : null;
        if (is_array($custom) && $custom !== []) {
            if (array_is_list($custom)) {
                $graph = array_merge($graph, $custom);
            } else {
                $graph[] = $custom;
            }
        }

        return array_values(array_filter($graph));
    }

    /** override نوع schema در سطح آیتم (seo_meta.schema_type) یا null. */
    private function schemaTypeOverride(Model $model): ?string
    {
        if (! method_exists($model, 'seoMeta')) {
            return null;
        }
        $val = $model->seoMeta?->schema_type;
        $val = is_string($val) ? trim($val) : null;

        return ($val === null || $val === '') ? null : $val;
    }

    /**
     * @type نهاییِ نود اختصاصی را تعیین می‌کند:
     * override آیتم ← default_schema نوع (config) ← null.
     */
    private function resolveSchemaType(string $type, ?string $override): ?string
    {
        if ($override !== null && strcasecmp($override, 'none') !== 0) {
            return $override;
        }

        $default = config("seo.types.$type.default_schema");

        return is_string($default) && $default !== '' ? $default : null;
    }

    private function baseUrl(): string
    {
        return rtrim((string) (SeoSetting::get('canonical_base_url') ?: config('app.url')), '/');
    }

    /** @return array<string, mixed>|null */
    private function organization(): ?array
    {
        $name = SeoSetting::get('kg_name') ?: (SeoSetting::get('site_name') ?: config('app.name'));
        if (! $name) {
            return null;
        }
        $type = SeoSetting::get('kg_type') ?: 'Organization';

        $node = [
            '@context' => 'https://schema.org',
            '@type' => $type,
            '@id' => $this->baseUrl().'/#organization',
            'name' => $name,
            'url' => $this->baseUrl(),
        ];
        if ($logo = SeoSetting::get('kg_logo')) {
            $node['logo'] = $logo;
            $node['image'] = $logo;
        }
        $sameAs = SeoSetting::getJson('kg_same_as', []);
        if ($sameAs !== []) {
            $node['sameAs'] = array_values($sameAs);
        }

        return $node;
    }

    /** @return array<string, mixed> */
    private function website(): array
    {
        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => $this->baseUrl().'/#website',
            'url' => $this->baseUrl(),
            'name' => SeoSetting::get('site_name') ?: config('app.name'),
            'publisher' => ['@id' => $this->baseUrl().'/#organization'],
        ];

        // Sitelinks Searchbox (اگر فرانت مسیر جستجو دارد)
        $node['potentialAction'] = [
            '@type' => 'SearchAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => $this->baseUrl().'/search?q={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ];

        return $node;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function webPage(string $type, array $meta): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            '@id' => ($meta['canonical'] ?? $this->baseUrl()).'#webpage',
            'url' => $meta['canonical'] ?? $this->baseUrl(),
            'name' => $meta['title'] ?? null,
            'description' => $meta['description'] ?? null,
            'isPartOf' => ['@id' => $this->baseUrl().'/#website'],
            'inLanguage' => 'fa-IR',
        ];
    }

    /**
     * BreadcrumbList از روی segmentهای مسیر canonical.
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>|null
     */
    private function breadcrumb(array $meta): ?array
    {
        $canonical = (string) ($meta['canonical'] ?? '');
        $path = trim((string) parse_url($canonical, PHP_URL_PATH), '/');
        if ($path === '') {
            return null;
        }

        $segments = explode('/', $path);
        $items = [[
            '@type' => 'ListItem',
            'position' => 1,
            'name' => 'خانه',
            'item' => $this->baseUrl().'/',
        ]];

        $acc = '';
        foreach ($segments as $i => $seg) {
            $acc .= '/'.$seg;
            $name = $i === array_key_last($segments)
                ? ($meta['breadcrumb_title'] ?? $this->humanize($seg))
                : $this->humanize($seg);
            $items[] = [
                '@type' => 'ListItem',
                'position' => $i + 2,
                'name' => $name,
                'item' => $this->baseUrl().$acc,
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * نود اختصاصیِ نوع‌محتوا.
     *
     * @param  array<string, mixed>  $meta
     * @param  string|null  $resolved  @type نهاییِ نود (از override آیتم یا default_schema)
     * @return array<string, mixed>|null
     */
    private function typeSpecific(string $type, Model $model, array $meta, ?string $resolved = null): ?array
    {
        return match ($type) {
            'article' => $this->article($model, $meta, $resolved ?: 'BlogPosting'),
            'brand', 'device', 'brand_device' => $this->service($model, $meta),
            'forum_question' => strcasecmp((string) $resolved, 'DiscussionForumPosting') === 0
                ? $this->discussionForumPosting($model, $meta)
                : $this->qaPage($model, $meta),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function article(Model $model, array $meta, string $schemaType = 'BlogPosting'): array
    {
        // فقط Article یا BlogPosting مجازند؛ هر مقدار دیگری به پیش‌فرض برمی‌گردد.
        $type = strcasecmp($schemaType, 'Article') === 0 ? 'Article' : 'BlogPosting';

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => $type,
            'headline' => $meta['title'] ?? $model->getAttribute('title'),
            'description' => $meta['description'] ?? null,
            'image' => $meta['og']['image'] ?? null,
            'datePublished' => $this->iso($model->getAttribute('published_at')),
            'dateModified' => $this->iso($model->getAttribute('updated_at')),
            'mainEntityOfPage' => ['@id' => ($meta['canonical'] ?? '').'#webpage'],
            'publisher' => ['@id' => $this->baseUrl().'/#organization'],
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function service(Model $model, array $meta): array
    {
        // برای صفحهٔ ترکیبی، serviceType = نامِ دستگاه و brand جداگانه می‌آید.
        $serviceType = $model->getAttribute('device_name') ?: $model->getAttribute('name');

        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => $meta['title'] ?? $model->getAttribute('name'),
            'description' => $meta['description'] ?? null,
            'serviceType' => $serviceType,
            'areaServed' => SeoSetting::get('default_city') ?: 'IR',
            'provider' => ['@id' => $this->baseUrl().'/#organization'],
            'url' => $meta['canonical'] ?? null,
        ];

        if ($brand = $model->getAttribute('brand_name')) {
            $node['brand'] = ['@type' => 'Brand', 'name' => $brand];
        }

        return array_filter($node, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function qaPage(Model $model, array $meta): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'QAPage',
            'mainEntity' => array_filter([
                '@type' => 'Question',
                'name' => $model->getAttribute('title'),
                'text' => $this->plain((string) $model->getAttribute('body')),
                'answerCount' => (int) $model->getAttribute('answers_count'),
            ], fn ($v) => $v !== null && $v !== ''),
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * نود FAQPage (§38) از پرسش‌های متداولِ فعالِ مدل.
     *
     * گِیت: تنظیم سراسری faq_schema_enabled === '1' و وجودِ حداقل یک FAQ فعال.
     * مدل‌های بدون کلید (مثل BrandDeviceCombo) نادیده گرفته می‌شوند.
     *
     * @return array<string, mixed>|null
     */
    private function faqPage(Model $model): ?array
    {
        if (SeoSetting::get('faq_schema_enabled') !== '1') {
            return null;
        }

        $key = $model->getKey();
        if (! $key) {
            return null;
        }

        $faqs = SeoFaq::query()
            ->where('faqable_type', $model->getMorphClass())
            ->where('faqable_id', $key)
            ->active()
            ->get(['question', 'answer']);

        if ($faqs->isEmpty()) {
            return null;
        }

        $mainEntity = [];
        foreach ($faqs as $faq) {
            $question = trim((string) $faq->question);
            $answer = $this->plain((string) $faq->answer);
            if ($question === '' || $answer === '') {
                continue;
            }
            $mainEntity[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $answer,
                ],
            ];
        }

        if ($mainEntity === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $mainEntity,
        ];
    }

    /**
     * نود DiscussionForumPosting (override برای صفحهٔ پرسش انجمن).
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function discussionForumPosting(Model $model, array $meta): array
    {
        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'DiscussionForumPosting',
            'headline' => $meta['title'] ?? $model->getAttribute('title'),
            'text' => $this->plain((string) $model->getAttribute('body')),
            'datePublished' => $this->iso($model->getAttribute('published_at')),
            'url' => $meta['canonical'] ?? null,
            'mainEntityOfPage' => ['@id' => ($meta['canonical'] ?? '').'#webpage'],
            'publisher' => ['@id' => $this->baseUrl().'/#organization'],
        ];

        if ($author = $model->getAttribute('author_name')) {
            $node['author'] = ['@type' => 'Person', 'name' => $author];
        }

        return array_filter($node, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * اعتبارسنجی سبک: نود اختصاصی فاقد ویژگیِ ضروری‌اش حذف می‌شود تا
     * هیچ نود ناقصی منتشر نشود. (مرور/امتیاز ساختگی تولید نمی‌کنیم.)
     *
     * @param  array<string, mixed>  $node
     */
    private function valid(array $node): bool
    {
        $type = $node['@type'] ?? null;

        return match ($type) {
            'Article', 'BlogPosting', 'DiscussionForumPosting' => ! empty($node['headline']),
            'Service' => ! empty($node['name']),
            'QAPage' => ! empty($node['mainEntity']) && ! empty($node['mainEntity']['name']),
            default => true,
        };
    }

    /** LocalBusiness از تنظیمات Local SEO (در صورت فعال‌بودن). */
    private function localBusiness(): ?array
    {
        if (SeoSetting::get('lb_enabled') !== '1') {
            return null;
        }

        $node = array_filter([
            '@context' => 'https://schema.org',
            '@type' => SeoSetting::get('lb_type') ?: 'LocalBusiness',
            '@id' => $this->baseUrl().'/#localbusiness',
            'name' => SeoSetting::get('lb_name') ?: SeoSetting::get('site_name'),
            'telephone' => SeoSetting::get('lb_phone'),
            'priceRange' => SeoSetting::get('lb_price_range'),
            'url' => $this->baseUrl(),
        ], fn ($v) => $v !== null && $v !== '');

        $address = array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => SeoSetting::get('lb_street'),
            'addressLocality' => SeoSetting::get('lb_city'),
            'addressRegion' => SeoSetting::get('lb_region'),
            'postalCode' => SeoSetting::get('lb_postal'),
            'addressCountry' => SeoSetting::get('lb_country') ?: 'IR',
        ], fn ($v) => $v !== null && $v !== '');
        if (count($address) > 1) {
            $node['address'] = $address;
        }

        $lat = SeoSetting::get('lb_lat');
        $lng = SeoSetting::get('lb_lng');
        if ($lat && $lng) {
            $node['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $lat, 'longitude' => $lng];
        }

        return $node;
    }

    private function iso($val): ?string
    {
        if (! $val) {
            return null;
        }

        return rescue(fn () => \Illuminate\Support\Carbon::parse($val)->toAtomString(), null);
    }

    private function humanize(string $slug): string
    {
        return trim(str_replace(['-', '_'], ' ', urldecode($slug)));
    }

    private function plain(string $html): string
    {
        return \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags($html))), 500, '');
    }
}
