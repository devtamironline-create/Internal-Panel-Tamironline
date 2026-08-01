<?php

namespace Modules\Seo\Services;

use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceBrandPage;
use Modules\Seo\Models\SeoMetaAuditRow;
use Modules\Seo\Models\SeoSetting;
use Modules\Seo\Support\BrandDeviceCombo;
use Modules\Seo\Support\MetaLength;
use Modules\Site\Support\ComboMetaChain;

/**
 * ساختِ snapshotِ بازبینیِ متا.
 *
 * سه نکتهٔ طراحی که ارزشِ توضیح دارند:
 *
 * ۱) مقادیر از خودِ کدِ زنده گرفته می‌شوند — `MetaResolver::titleAndDescription()`
 *    برای کانالِ سئو و `ComboMetaChain` برای کانالِ کاتالوگ. هیچ منطقی این‌جا
 *    بازنویسی نشده، وگرنه گزارش به‌مرور با واقعیت اختلاف پیدا می‌کرد.
 *
 * ۲) `loadForPublic()` عمداً صدا زده نمی‌شود: آن، همهٔ سکشن‌های صفحه (هیرو، FAQ،
 *    ویدیوها، نظرات) را با hydrate کردنِ ارجاع‌ها می‌خواند — برای هر صفحه چند
 *    کوئری. ما فقط سکشنِ `seo` را می‌خواهیم که فیلدهایش رشتهٔ ساده‌اند و ارجاعی
 *    ندارند، پس payloadِ خام یک‌بار خوانده و placeholderها دستی اعمال می‌شوند.
 *
 * ۳) تشخیصِ تکراری قبل از نوشتن انجام می‌شود: همهٔ ردیف‌ها در حافظه ساخته
 *    می‌شوند، هش‌ها شمرده می‌شوند، بعد یک‌جا insert. برای چند هزار ردیف چند
 *    مگابایت است و از UPDATE کردنِ ستونِ JSON پس از insert بسیار ارزان‌تر.
 */
class MetaAuditBuilder
{
    public function __construct(
        private readonly MetaResolver $resolver,
        private readonly VariableRenderer $variables,
    ) {}

    /** @var array<string, array{title: ?string, description: ?string}> */
    private array $sectionSeo = [];

    /**
     * @param  callable(string):void|null  $progress
     * @return array<string, int>
     */
    public function rebuild(?callable $progress = null): array
    {
        // بدونِ callback هم باید کار کند (فراخوانی از کنترلر، نه فقط کامند).
        $progress ??= static fn (string $message) => null;

        $this->sectionSeo = $this->loadSeoSections();
        $crawled = $this->latestCrawl();

        $rows = [];
        foreach (['brand_device' => 'combos', 'device' => 'devices', 'brand' => 'brands'] as $type => $method) {
            $progress('جمع‌آوریِ '.SeoMetaAuditRow::PAGE_TYPE_LABELS[$type].'…');
            foreach ($this->{$method}($crawled) as $row) {
                $rows[] = $row;
            }
        }

        $progress('تشخیصِ تکراری‌ها روی '.number_format(count($rows)).' ردیف…');
        $this->markDuplicates($rows);

        $progress('نوشتنِ snapshot…');
        DB::transaction(function () use ($rows) {
            DB::table('seo_meta_audit_rows')->truncate();
            foreach (array_chunk($rows, 300) as $chunk) {
                DB::table('seo_meta_audit_rows')->insert($chunk);
            }
        });

        return $this->summarise($rows);
    }

    /**
     * صفحاتِ ترکیبی — همان شرطی که SitemapBuilder و کنترلرِ کاتالوگ دارند، تا
     * شمارشِ ابزار با sitemap یکی باشد.
     *
     * @param  array<string, object>  $crawled
     * @return \Generator<array<string, mixed>>
     */
    private function combos(array $crawled): \Generator
    {
        $pattern = (string) config('seo.types.brand_device.url', '/services/{device}/{brand}');

        $pages = DeviceBrandPage::query()
            ->where('is_active', true)
            ->with(['device', 'brand'])
            ->cursor();

        foreach ($pages as $page) {
            $device = $page->device;
            $brand = $page->brand;
            if (! $device || ! $brand || ! $device->is_active || ! $brand->is_active) {
                continue;
            }

            $url = str_replace(['{device}', '{brand}'], [$device->slug, $brand->slug], $pattern);
            $context = [
                'device' => $device->short_name ?? $device->name,
                'device_label' => $device->name,
                'device_slug' => $device->slug,
                'brand' => $brand->name,
                'brand_slug' => $brand->slug,
            ];

            $live = $this->resolver->titleAndDescription('brand_device', $this->comboModel($device, $brand, $page));

            $catalog = ComboMetaChain::for(
                $page,
                $device,
                $brand,
                $this->seoSection('device_brand', $context),
                $this->seoSection('device_brand:'.$device->slug, $context),
            );

            yield $this->row(
                type: 'brand_device',
                url: $url,
                entityId: (int) $device->id,
                secondaryId: (int) $brand->id,
                label: trim($device->name.' '.$brand->name),
                live: $live,
                catalogTitle: $catalog['meta_title'],
                catalogDescription: $catalog['meta_description'],
                expected: $this->expected('brand_device', ['device' => $device->name, 'brand' => $brand->name]),
                crawled: $crawled[$this->key($url)] ?? null,
            );
        }
    }

    /**
     * @param  array<string, object>  $crawled
     * @return \Generator<array<string, mixed>>
     */
    private function devices(array $crawled): \Generator
    {
        $pattern = (string) config('seo.types.device.url', '/services/{slug}');

        foreach (Device::query()->where('is_active', true)->cursor() as $device) {
            $url = str_replace('{slug}', $device->slug, $pattern);

            yield $this->row(
                type: 'device',
                url: $url,
                entityId: (int) $device->id,
                secondaryId: null,
                label: $device->name,
                live: $this->resolver->titleAndDescription('device', $device),
                catalogTitle: null,
                catalogDescription: null,
                expected: $this->expected('device', ['title' => $device->name]),
                crawled: $crawled[$this->key($url)] ?? null,
                // صفحاتِ دستگاه تنها جایی‌اند که دو الگوی URL در ریپو با هم
                // نمی‌خوانند: config می‌گوید /services/{slug} و
                // CatalogDeviceController:61 می‌گوید /devices/{slug}.
                urlConflict: true,
            );
        }
    }

    /**
     * @param  array<string, object>  $crawled
     * @return \Generator<array<string, mixed>>
     */
    private function brands(array $crawled): \Generator
    {
        $pattern = (string) config('seo.types.brand.url', '/brands/{slug}');

        foreach (Brand::query()->where('is_active', true)->cursor() as $brand) {
            $url = str_replace('{slug}', $brand->slug, $pattern);

            yield $this->row(
                type: 'brand',
                url: $url,
                entityId: (int) $brand->id,
                secondaryId: null,
                label: $brand->name,
                live: $this->resolver->titleAndDescription('brand', $brand),
                catalogTitle: null,
                catalogDescription: null,
                expected: $this->expected('brand', ['title' => $brand->name]),
                crawled: $crawled[$this->key($url)] ?? null,
            );
        }
    }

    /**
     * @param  array{title: string, description: string}  $live
     * @param  array{title: string, description: string}  $expected
     * @return array<string, mixed>
     */
    private function row(
        string $type,
        string $url,
        int $entityId,
        ?int $secondaryId,
        ?string $label,
        array $live,
        ?string $catalogTitle,
        ?string $catalogDescription,
        array $expected,
        ?object $crawled,
        bool $urlConflict = false,
    ): array {
        $title = (string) $live['title'];
        $description = (string) $live['description'];

        // فقط اختلاف ثبت می‌شود؛ ستون‌های کاتالوگ در حالتِ عادی خالی می‌مانند.
        $diverges = $catalogTitle !== null
            && ($catalogTitle !== $title || (string) $catalogDescription !== $description);

        $flags = [];
        if ($title === '') {
            $flags[] = 'title_empty';
        } elseif (mb_strlen($title) > MetaLength::TITLE_MAX) {
            $flags[] = 'title_long';
        }
        if ($description === '') {
            $flags[] = 'description_empty';
        } elseif (mb_strlen($description) > MetaLength::DESCRIPTION_MAX) {
            $flags[] = 'description_long';
        }
        if ($diverges) {
            $flags[] = 'channels_diverge';
        }

        return [
            'page_type' => $type,
            'url' => $url,
            'entity_id' => $entityId,
            'secondary_id' => $secondaryId,
            'label' => $label,
            'live_title' => $title,
            'live_title_len' => mb_strlen($title),
            'live_description' => $description,
            'live_description_len' => mb_strlen($description),
            'catalog_title' => $diverges ? $catalogTitle : null,
            'catalog_description' => $diverges ? $catalogDescription : null,
            'channels_diverge' => $diverges,
            'title_hash' => $title === '' ? null : md5($title),
            'description_hash' => $description === '' ? null : md5($description),
            'crawled_title' => $crawled->title ?? null,
            'crawled_description' => $crawled->meta_description ?? null,
            'crawled_at' => $crawled->crawled_at ?? null,
            'flags' => json_encode($flags, JSON_UNESCAPED_UNICODE),
            'verdict' => null,   // بعد از تشخیصِ تکراری تعیین می‌شود
            'matches_template' => $title === $expected['title'] && $description === $expected['description'],
            'url_pattern_conflict' => $urlConflict,
            'generated_at' => now(),
        ];
    }

    /**
     * تکراری‌ها + حکمِ نهایی. هر دو به همهٔ ردیف‌ها نیاز دارند، پس بعد از جمع‌آوری.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function markDuplicates(array &$rows): void
    {
        $titleCounts = [];
        $descCounts = [];
        foreach ($rows as $row) {
            if ($row['title_hash']) {
                $titleCounts[$row['title_hash']] = ($titleCounts[$row['title_hash']] ?? 0) + 1;
            }
            if ($row['description_hash']) {
                $descCounts[$row['description_hash']] = ($descCounts[$row['description_hash']] ?? 0) + 1;
            }
        }

        foreach ($rows as &$row) {
            $flags = json_decode((string) $row['flags'], true) ?: [];

            if ($row['title_hash'] && ($titleCounts[$row['title_hash']] ?? 0) > 1) {
                $flags[] = 'title_duplicate';
            }
            if ($row['description_hash'] && ($descCounts[$row['description_hash']] ?? 0) > 1) {
                $flags[] = 'description_duplicate';
            }

            $row['flags'] = json_encode(array_values(array_unique($flags)), JSON_UNESCAPED_UNICODE);
            $row['verdict'] = $this->verdict($row, $flags);
        }
        unset($row);

        // برچسبِ «مطابق الگو» نباید روی ردیفی بنشیند که هنوز مشکلی دارد.
        foreach ($rows as &$row) {
            if ($row['flags'] !== '[]') {
                $row['matches_template'] = $row['matches_template'] && $row['verdict'] !== 'needs_review';
            }
        }
        unset($row);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $flags
     */
    private function verdict(array $row, array $flags): string
    {
        if ($flags !== []) {
            return 'needs_review';
        }

        $before = (string) ($row['crawled_title'] ?? '');
        if ($before === '') {
            // هرگز کرال نشده — نمی‌شود گفت اصلاح شد یا نه.
            return 'awaiting_crawl';
        }

        if ($before === $row['live_title']) {
            return 'ok';
        }

        // کرالِ قدیمی خودش مشکل داشت و مقدارِ زنده ندارد → اصلاح واقعی است و
        // فقط منتظرِ کرالِ بعدی است تا در ابزارهای بیرونی هم دیده شود.
        $beforeBroken = mb_strlen($before) > MetaLength::TITLE_MAX
            || mb_strlen((string) ($row['crawled_description'] ?? '')) > MetaLength::DESCRIPTION_MAX
            || trim((string) ($row['crawled_description'] ?? '')) === '';

        return $beforeBroken ? 'fixed' : 'awaiting_crawl';
    }

    /**
     * خروجیِ قالبِ تأییدشده برای همین صفحه — مبنای برچسبِ «مطابق الگو».
     *
     * @param  array<string, string>  $vars
     * @return array{title: string, description: string}
     */
    private function expected(string $type, array $vars): array
    {
        $key = $type === 'brand_device' ? 'brand_device' : $type;

        $title = SeoSetting::get("tpl_{$key}_title") ?: (string) config("seo.templates.{$key}.title", '');
        $description = SeoSetting::get("tpl_{$key}_description") ?: (string) config("seo.templates.{$key}.description", '');

        return [
            'title' => MetaLength::title($this->variables->render($title, $vars)),
            'description' => MetaLength::description($this->variables->render($description, $vars)),
        ];
    }

    /**
     * payloadِ خامِ سکشنِ `seo` هر الگو — یک‌بار برای کلِ اجرا.
     *
     * @return array<string, array{title: ?string, description: ?string}>
     */
    private function loadSeoSections(): array
    {
        $out = [];
        $rows = DB::table('site_page_sections')
            ->where('section_key', 'seo')
            ->where('is_published', true)
            ->get(['page_slug', 'payload']);

        foreach ($rows as $row) {
            $payload = json_decode((string) $row->payload, true) ?: [];
            $out[$row->page_slug] = [
                'title' => $payload['meta_title'] ?? null,
                'description' => $payload['meta_description'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $context
     * @return array<string, array<string, ?string>>
     */
    private function seoSection(string $pageSlug, array $context): array
    {
        $row = $this->sectionSeo[$pageSlug] ?? null;
        if (! $row || ($row['title'] === null && $row['description'] === null)) {
            return [];
        }

        return ['seo' => [
            'meta_title' => $row['title'] === null ? null : $this->variables->render($row['title'], $context),
            'meta_description' => $row['description'] === null ? null : $this->variables->render($row['description'], $context),
        ]];
    }

    /**
     * تازه‌ترین کرالِ هر URL از `seo_audits` — «قبل».
     *
     * مرتب‌سازیِ صعودی و keyBy یعنی آخرین مقدارِ نوشته‌شده تازه‌ترین است.
     *
     * @return array<string, object>
     */
    private function latestCrawl(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('seo_audits')) {
            return [];
        }

        $out = [];
        DB::table('seo_audits')
            ->select('url', 'title', 'meta_description', 'crawled_at')
            ->orderBy('id')
            ->chunk(2000, function ($rows) use (&$out) {
                foreach ($rows as $row) {
                    $out[$this->key($row->url)] = $row;
                }
            });

        return $out;
    }

    /** تطبیقِ URLِ کرال با URLِ ساخته‌شده: دامنه و اسلشِ انتهایی نباید مانع شوند. */
    private function key(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;

        return '/'.trim($path, '/');
    }

    private function comboModel(Device $device, Brand $brand, DeviceBrandPage $page): BrandDeviceCombo
    {
        $combo = new BrandDeviceCombo([
            'slug' => $device->slug.'/'.$brand->slug,
            'device' => $device->slug,
            'brand' => $brand->slug,
            'device_name' => $device->name,
            'brand_name' => $brand->name,
            'name' => trim($device->name.' '.$brand->name),
            'is_active' => true,
        ]);
        $combo->deviceModel = $device;
        $combo->brandModel = $brand;
        $combo->setRelation('seoMeta', $page->seoMeta);

        return $combo;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function summarise(array $rows): array
    {
        $out = ['total' => count($rows)];
        foreach (array_keys(SeoMetaAuditRow::FLAG_LABELS) as $flag) {
            $out[$flag] = 0;
        }
        foreach (array_keys(SeoMetaAuditRow::VERDICT_LABELS) as $verdict) {
            $out[$verdict] = 0;
        }

        foreach ($rows as $row) {
            foreach (json_decode((string) $row['flags'], true) ?: [] as $flag) {
                $out[$flag] = ($out[$flag] ?? 0) + 1;
            }
            $out[$row['verdict']] = ($out[$row['verdict']] ?? 0) + 1;
        }

        return $out;
    }
}
