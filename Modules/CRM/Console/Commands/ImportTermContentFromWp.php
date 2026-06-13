<?php

namespace Modules\CRM\Console\Commands;

use DOMDocument;
use DOMElement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Support\HtmlSanitizer;
use Modules\Site\Support\WpImageDownloader;
use Modules\Site\Support\WpShortcodeStripper;

/**
 * Import محتوای CMS دستگاه‌ها و برندها از taxonomy های وردپرس قدیمی.
 *
 *   - taxonomy=repair → crm_devices (description, subtitle, service_name, faq, meta_*)
 *   - taxonomy=brands → crm_brands  (description, subtitle, faq, meta_*)
 *
 * منبع محتوا در WP: term description + فیلدهای ACF در termmeta
 * (editor_content / intro_content / title_content / repeater faqs_N_*)
 * و سئوی RankMath (termmeta) با fallback به Yoast (option wpseo_taxonomy_meta).
 *
 * نگاشت term→دستگاه/برند صریح است چون wp_id فعلیِ جدول‌ها به taxonomy وردپرس
 * اشاره نمی‌کند (برای sync سفارش‌هاست) — به همین دلیل wp_id دست نمی‌خورد.
 *
 * نیاز env (همان اتصال import مقالات):
 *   WP_BLOG_DB_HOST, WP_BLOG_DB_PORT, WP_BLOG_DB_NAME, WP_BLOG_DB_USER,
 *   WP_BLOG_DB_PASS, WP_BLOG_DB_PREFIX
 *
 * Dry-run by default. برای اعمال واقعی --apply بزن:
 *   php artisan crm:import-term-content-from-wp
 *   php artisan crm:import-term-content-from-wp --apply
 *   php artisan crm:import-term-content-from-wp --apply --devices
 *   php artisan crm:import-term-content-from-wp --apply --term-id=214
 *   php artisan crm:import-term-content-from-wp --apply --no-images
 */
class ImportTermContentFromWp extends Command
{
    protected $signature = 'crm:import-term-content-from-wp
                            {--apply : اعمال واقعی (پیش‌فرض dry-run)}
                            {--devices : فقط دستگاه‌ها (taxonomy=repair)}
                            {--brands : فقط برندها (taxonomy=brands)}
                            {--term-id= : فقط یک term خاص}
                            {--no-images : تصاویر inline دانلود نشوند (URL قدیمی بماند)}';

    protected $description = 'Import متن‌های CMS دستگاه‌ها/برندها از termهای taxonomy وردپرس (repair/brands)';

    /**
     * نگاشت term_id تاکسونومی repair → نام دستگاه(ها) در پنل.
     * ترم‌های چنددستگاهی (مثل «تعمیر پکیج و آبگرمکن») روی همه‌ی مقصدها کپی می‌شوند.
     * 408 و 410 هر دو به «تصفیه آب» اشاره دارند — 410 (پرمحتواتر) بعداً پردازش
     * می‌شود و برنده است.
     *
     * @var array<int, list<string>>
     */
    private const DEVICE_TERM_MAP = [
        214 => ['لباسشویی'],
        215 => ['جاروبرقی'],
        249 => ['ظرفشویی'],
        250 => ['یخچال و فریزر', 'ساید بای ساید'],
        252 => ['اجاق گاز', 'گاز رومیزی'],
        253 => ['مایکروفر', 'سولاردام', 'فر'],
        254 => ['اسپیلت'],
        255 => ['تلویزیون'],
        311 => ['جاروشارژی'],
        379 => ['قهوه ساز'],
        384 => ['بخارشوی'],
        385 => ['اتو', 'اتوپرس'],
        390 => ['پکیج', 'آبگرمکن'],
        391 => ['چایی ساز'],
        403 => ['کولر آبی'],
        408 => ['تصفیه آب'],
        410 => ['تصفیه آب'],
    ];

    /**
     * دستگاه‌هایی که در پنل وجود ندارند و در صورت داشتن محتوا ساخته می‌شوند.
     * slug انگلیسی از slug همان term وردپرس گرفته شده است.
     *
     * @var array<string, string> name => slug
     */
    private const CREATABLE_DEVICES = [
        'کولر آبی' => 'evaporative-cooler',
    ];

    /**
     * نگاشت term_id تاکسونومی brands → نام برند در پنل.
     * «خدمات ال جی» → ال جی، «زیرووات» → زیروات (تفاوت املایی)،
     * «اُجنرال» → جنرال (برند کولر O'General).
     *
     * @var array<int, string>
     */
    private const BRAND_TERM_MAP = [
        256 => 'نف',
        257 => 'سامسونگ',
        258 => 'زیمنس',
        259 => 'دوو',
        260 => 'جنرال الکتریک',
        261 => 'بوش',
        262 => 'ایندزیت',
        263 => 'الکترولوکس',
        264 => 'ال جی',
        265 => 'آریستون',
        266 => 'آاگ',
        267 => 'مابه',
        268 => 'گاگنا',
        269 => 'ویرپول',
        270 => 'کنوود',
        271 => 'ارج',
        272 => 'آبسال',
        273 => 'فاگور',
        274 => 'کن',
        275 => 'اخوان',
        276 => 'ایلیا استیل',
        277 => 'لاجرمانیا',
        278 => 'بیمکث',
        279 => 'اسنوا',
        280 => 'بکو',
        281 => 'آدمیرال',
        282 => 'مدیا',
        283 => 'فریجیدر',
        284 => 'گرنیه',
        285 => 'زیروات',
        286 => 'زانوسی',
        287 => 'جنرال',
        312 => 'هایسنس',
    ];

    private bool $apply = false;

    private bool $downloadImages = true;

    private string $prefix = 'wp_';

    /** @var array<string, array<int, array{title: ?string, desc: ?string}>> */
    private ?array $yoastTaxMeta = null;

    private WpImageDownloader $images;

    /** @var array<string, int> */
    private array $stats = [
        'updated' => 0,
        'created' => 0,
        'skipped' => 0,
        'errors' => 0,
        'images_downloaded' => 0,
    ];

    public function handle(): int
    {
        $this->configureWpConnection();

        try {
            DB::connection('wp_blog')->getPdo();
        } catch (\Throwable $e) {
            $this->error('اتصال به WP DB ناموفق: '.$e->getMessage());
            $this->line('چک کن env های WP_BLOG_DB_* در .env ست شده باشند.');

            return self::FAILURE;
        }

        $this->prefix = (string) env('WP_BLOG_DB_PREFIX', 'wp_');
        $this->apply = (bool) $this->option('apply');
        $this->downloadImages = ! $this->option('no-images');
        $this->images = new WpImageDownloader;

        $onlyTermId = $this->option('term-id') ? (int) $this->option('term-id') : null;

        // بدون فلگ → هر دو
        $doDevices = $this->option('devices') || ! $this->option('brands');
        $doBrands = $this->option('brands') || ! $this->option('devices');

        $this->info($this->apply ? 'اعمال واقعی' : 'Dry-run (برای اعمال --apply بزن)');

        if ($doDevices) {
            $this->newLine();
            $this->info('── دستگاه‌ها (taxonomy=repair) ──');
            foreach (self::DEVICE_TERM_MAP as $termId => $deviceNames) {
                if ($onlyTermId !== null && $termId !== $onlyTermId) {
                    continue;
                }
                $this->importTerm('repair', $termId, $deviceNames, isDevice: true);
            }
        }

        if ($doBrands) {
            $this->newLine();
            $this->info('── برندها (taxonomy=brands) ──');
            foreach (self::BRAND_TERM_MAP as $termId => $brandName) {
                if ($onlyTermId !== null && $termId !== $onlyTermId) {
                    continue;
                }
                $this->importTerm('brands', $termId, [$brandName], isDevice: false);
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'نتیجه: %d به‌روز، %d ساخته، %d skip، %d خطا — %d تصویر دانلود.',
            $this->stats['updated'],
            $this->stats['created'],
            $this->stats['skipped'],
            $this->stats['errors'],
            $this->stats['images_downloaded'],
        ));

        if (! $this->apply) {
            $this->comment('این dry-run بود؛ هیچ تغییری ذخیره نشد.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $targetNames
     */
    private function importTerm(string $taxonomy, int $termId, array $targetNames, bool $isDevice): void
    {
        $term = DB::connection('wp_blog')
            ->table("{$this->prefix}terms as t")
            ->join("{$this->prefix}term_taxonomy as tt", 'tt.term_id', '=', 't.term_id')
            ->where('t.term_id', $termId)
            ->where('tt.taxonomy', $taxonomy)
            ->first(['t.term_id', 't.name', 't.slug', 'tt.description']);

        if (! $term) {
            $this->warn("term {$termId}: در وردپرس پیدا نشد — skip");
            $this->stats['skipped']++;

            return;
        }

        $meta = $this->fetchTermMeta($termId);
        $fields = $this->buildFields($term, $meta, $taxonomy, $isDevice);

        if (empty($fields)) {
            $this->line("term {$termId} ({$term->name}): محتوایی برای انتقال ندارد — skip");
            $this->stats['skipped']++;

            return;
        }

        foreach ($targetNames as $targetName) {
            $model = $isDevice
                ? Device::where('name', $targetName)->first()
                : Brand::where('name', $targetName)->first();

            $willCreate = false;
            if (! $model && $isDevice && isset(self::CREATABLE_DEVICES[$targetName])) {
                $willCreate = true;
            } elseif (! $model) {
                $this->warn("term {$termId} ({$term->name}) → «{$targetName}»: در پنل پیدا نشد — skip");
                $this->stats['errors']++;

                continue;
            }

            $action = $willCreate ? 'ساخت دستگاه جدید' : 'به‌روزرسانی';
            $summary = collect($fields)
                ->map(fn ($v, $k) => $k.'('.(is_string($v) ? mb_strlen($v) : count($v)).')')
                ->implode('، ');
            $this->line("term {$termId} ({$term->name}) → «{$targetName}»: {$action} — {$summary}");

            if (! $this->apply) {
                $willCreate ? $this->stats['created']++ : $this->stats['updated']++;

                continue;
            }

            try {
                if ($willCreate) {
                    $model = new Device([
                        'name' => $targetName,
                        'slug' => self::CREATABLE_DEVICES[$targetName],
                        'is_active' => true,
                        'sort_order' => (int) Device::max('sort_order') + 1,
                    ]);
                }
                $model->fill($fields);
                $model->save();
                $willCreate ? $this->stats['created']++ : $this->stats['updated']++;
            } catch (\Throwable $e) {
                $this->error("term {$termId} → «{$targetName}»: خطا — ".$e->getMessage());
                $this->stats['errors']++;
            }
        }
    }

    /**
     * فیلدهای قابل انتقال را از term + termmeta می‌سازد. فیلد خالی ست نمی‌شود
     * (مقدار خالی وردپرس هرگز محتوای موجود پنل را پاک نمی‌کند).
     *
     * @param  array<string, string>  $meta
     * @return array<string, mixed>
     */
    private function buildFields(object $term, array $meta, string $taxonomy, bool $isDevice): array
    {
        $fields = [];

        $description = $this->buildDescription(
            $meta['editor_content'] ?? '',
            (string) $term->description,
        );
        if ($description !== null) {
            $fields['description'] = $description;
        }

        $subtitle = trim($meta['intro_content'] ?? '');
        if ($subtitle !== '') {
            // ستون subtitle دستگاه varchar(200) و برند varchar(500) است
            $fields['subtitle'] = $this->truncate($subtitle, $isDevice ? 200 : 500);
        }

        if ($isDevice) {
            $serviceName = trim($meta['title_content'] ?? '');
            if ($serviceName !== '') {
                $fields['service_name'] = $this->truncate($serviceName, 160);
            }
        }

        $faq = $this->buildFaq($meta);
        if (! empty($faq)) {
            $fields['faq'] = $faq;
        }

        [$metaTitle, $metaDesc] = $this->buildSeo((int) $term->term_id, $taxonomy, $meta);
        if ($metaTitle !== null) {
            $fields['meta_title'] = $this->truncate($metaTitle, 200);
        }
        if ($metaDesc !== null) {
            $fields['meta_description'] = $this->truncate($metaDesc, 500);
        }

        return $fields;
    }

    /**
     * متن اصلی: ACF editor_content + description خود term.
     * اگر یکی دیگری را شامل شود فقط بلندتر، وگرنه پشت‌سرهم (هر دو متن مستقل
     * روی صفحه‌ی قدیمی رندر می‌شدند و نباید از دست بروند).
     */
    private function buildDescription(string $editorContent, string $termDescription): ?string
    {
        $editor = trim($editorContent);
        $termDesc = trim($termDescription);

        if ($editor === '' && $termDesc === '') {
            return null;
        }

        if ($editor !== '' && $termDesc !== '') {
            $normEditor = $this->normalizeForCompare($editor);
            $normTerm = $this->normalizeForCompare($termDesc);
            if (str_contains($normEditor, $normTerm)) {
                $raw = $editor;
            } elseif (str_contains($normTerm, $normEditor)) {
                $raw = $termDesc;
            } else {
                $raw = $editor."\n\n".$termDesc;
            }
        } else {
            $raw = $editor !== '' ? $editor : $termDesc;
        }

        return $this->cleanHtml($raw);
    }

    /**
     * پاکسازی سبک: شورت‌کدهای WP، حذف wrapperهای صفحه‌ساز (Elementor/WPBakery)،
     * دانلود تصاویر inline و در نهایت sanitize با allowlist موجود پروژه.
     */
    private function cleanHtml(string $html): ?string
    {
        $html = (string) WpShortcodeStripper::strip($html);
        $html = $this->stripBuilderWrappers($html);
        if ($this->apply && $this->downloadImages) {
            $html = $this->rewriteInlineImages($html);
        }

        return HtmlSanitizer::clean($html);
    }

    /**
     * unwrap کردن div/section/span هایی که class صفحه‌ساز دارند —
     * HtmlSanitizer تگ div با class را مجاز می‌داند، پس باید قبل از آن حذف شوند.
     */
    private function stripBuilderWrappers(string $html): string
    {
        if (trim($html) === '' || ! preg_match('/elementor|vc_|wpb_/', $html)) {
            return $html;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"?><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        // اولین div همان wrapper بیرونی است (مثل HtmlSanitizer)
        $root = $dom->getElementsByTagName('div')->item(0);
        if (! $root instanceof DOMElement) {
            return $html;
        }

        // تکرار تا وقتی wrapper تو در تو باقی است
        do {
            $unwrapped = false;
            foreach (['div', 'section', 'span'] as $tag) {
                foreach (iterator_to_array($dom->getElementsByTagName($tag)) as $el) {
                    if (! $el instanceof DOMElement || $el === $root) {
                        continue;
                    }
                    $class = $el->getAttribute('class');
                    if ($class === '' || ! preg_match('/(^|\s)(elementor|vc_|wpb_)/', $class)) {
                        continue;
                    }
                    $parent = $el->parentNode;
                    if (! $parent) {
                        continue;
                    }
                    while ($el->firstChild) {
                        $parent->insertBefore($el->firstChild, $el);
                    }
                    $parent->removeChild($el);
                    $unwrapped = true;
                }
            }
        } while ($unwrapped);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    private function rewriteInlineImages(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        return preg_replace_callback(
            '/<img\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>/i',
            function ($m) {
                $src = $m[1];
                $tag = $m[0];
                if (! preg_match('#^https?://#i', $src)) {
                    return $tag;
                }
                $media = $this->images->downloadAndStore($src);
                if (! $media) {
                    return $tag;
                }
                $this->stats['images_downloaded']++;

                // مسیر ریشه‌ای-نسبی (بدون هاست) — مستقل از APP_URL در CLI،
                // تا src تصاویر روی هر دامنه‌ای که پنل سرو می‌شود درست بماند.
                $newUrl = route('site.media.serve', [
                    'id' => $media->id,
                    'name' => $media->hash.'.'.$media->extension,
                ], false);

                return str_replace($src, $newUrl, $tag);
            },
            $html
        ) ?? $html;
    }

    /**
     * repeater سوالات ACF: faqs (تعداد) + faqs_N_question / faqs_N_answer
     * → ساختار inline استفاده‌شده در پنل: [{question, answer}].
     *
     * @param  array<string, string>  $meta
     * @return list<array{question: string, answer: string}>
     */
    private function buildFaq(array $meta): array
    {
        $count = (int) ($meta['faqs'] ?? 0);
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $question = trim($meta["faqs_{$i}_question"] ?? '');
            $answer = trim($meta["faqs_{$i}_answer"] ?? '');
            if ($question === '' || $answer === '') {
                continue;
            }
            $out[] = [
                'question' => strip_tags($question),
                'answer' => (string) HtmlSanitizer::clean($answer),
            ];
        }

        return $out;
    }

    /**
     * سئو: اول RankMath (termmeta) بعد Yoast (option سراسری wpseo_taxonomy_meta).
     * مقادیر دارای placeholder مثل %term% رد می‌شوند.
     *
     * @param  array<string, string>  $meta
     * @return array{0: ?string, 1: ?string}
     */
    private function buildSeo(int $termId, string $taxonomy, array $meta): array
    {
        $title = trim($meta['rank_math_title'] ?? '');
        $desc = trim($meta['rank_math_description'] ?? '');

        if ($title === '' || $desc === '') {
            $yoast = $this->yoastTaxMeta()[$taxonomy][$termId] ?? null;
            if ($yoast) {
                $title = $title !== '' ? $title : trim($yoast['title'] ?? '');
                $desc = $desc !== '' ? $desc : trim($yoast['desc'] ?? '');
            }
        }

        return [
            ($title !== '' && ! str_contains($title, '%')) ? $title : null,
            ($desc !== '' && ! str_contains($desc, '%')) ? $desc : null,
        ];
    }

    /**
     * @return array<string, array<int, array{title: ?string, desc: ?string}>>
     */
    private function yoastTaxMeta(): array
    {
        if ($this->yoastTaxMeta !== null) {
            return $this->yoastTaxMeta;
        }

        $this->yoastTaxMeta = [];
        $row = DB::connection('wp_blog')
            ->table("{$this->prefix}options")
            ->where('option_name', 'wpseo_taxonomy_meta')
            ->first(['option_value']);

        if ($row) {
            $data = @unserialize((string) $row->option_value, ['allowed_classes' => false]);
            if (is_array($data)) {
                foreach ($data as $tax => $terms) {
                    if (! is_array($terms)) {
                        continue;
                    }
                    foreach ($terms as $tid => $entry) {
                        $this->yoastTaxMeta[(string) $tax][(int) $tid] = [
                            'title' => is_array($entry) ? ($entry['wpseo_title'] ?? null) : null,
                            'desc' => is_array($entry) ? ($entry['wpseo_desc'] ?? null) : null,
                        ];
                    }
                }
            }
        }

        return $this->yoastTaxMeta;
    }

    /**
     * @return array<string, string>
     */
    private function fetchTermMeta(int $termId): array
    {
        $rows = DB::connection('wp_blog')
            ->table("{$this->prefix}termmeta")
            ->where('term_id', $termId)
            ->where('meta_key', 'not like', '\_%')
            ->get(['meta_key', 'meta_value']);

        $out = [];
        foreach ($rows as $r) {
            $out[$r->meta_key] = (string) $r->meta_value;
        }

        return $out;
    }

    private function normalizeForCompare(string $html): string
    {
        $text = strip_tags($html);

        return (string) preg_replace('/\s+/u', '', $text);
    }

    private function truncate(string $value, int $max): string
    {
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }

    private function configureWpConnection(): void
    {
        config(['database.connections.wp_blog' => [
            'driver' => 'mysql',
            'host' => env('WP_BLOG_DB_HOST', '127.0.0.1'),
            'port' => (int) env('WP_BLOG_DB_PORT', 3306),
            'database' => env('WP_BLOG_DB_NAME', ''),
            'username' => env('WP_BLOG_DB_USER', ''),
            'password' => env('WP_BLOG_DB_PASS', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'strict' => false,
        ]]);
    }
}
