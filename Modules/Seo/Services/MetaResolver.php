<?php

namespace Modules\Seo\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Seo\Models\SeoMeta;
use Modules\Seo\Models\SeoSetting;

/**
 * موتور «مقدار نهایی» سئو با سطح‌بندی:
 *   override در سطح آیتم (seo_meta) ← قالب نوع‌محتوا ← تنظیم/قالب سراسری.
 *
 * خروجی، آرایهٔ آمادهٔ مصرف برای endpoint متا (title/description/canonical/
 * robots/og/twitter/jsonld) است.
 */
class MetaResolver
{
    public function __construct(
        private readonly SeoableRegistry $registry,
        private readonly VariableRenderer $variables,
        private readonly RobotsBuilder $robots,
        private readonly SchemaGenerator $schema,
    ) {}

    /**
     * اولین مقدار غیرخالی از میان نامزدها (منطق سطح‌بندی fallback) — pure.
     */
    public static function pick(?string ...$candidates): ?string
    {
        foreach ($candidates as $c) {
            if ($c !== null && trim($c) !== '') {
                return $c;
            }
        }

        return null;
    }

    /**
     * قالبِ نهاییِ یک نوع برای title/description با اولویت:
     *   تنظیمِ پنل برای نوع ← تنظیمِ پنل سراسری ← config نوع ← config سراسری.
     * این باعث می‌شود مدیر بتواند قالب‌ها را از پنل و بدونِ کد عوض کند.
     */
    private function template(string $type, string $field): ?string
    {
        return self::pick(
            SeoSetting::get("tpl_{$type}_{$field}"),
            SeoSetting::get("tpl_global_{$field}"),
            config("seo.templates.$type.$field"),
            config("seo.templates.global.$field"),
        );
    }

    /**
     * فقط عنوان و توضیحاتِ نهایی — بدونِ canonical/robots/og/twitter و مهم‌تر از
     * همه بدونِ `SchemaGenerator`، که برای JSON-LD به بانکِ FAQ کوئری می‌زند.
     *
     * برای ابزارِ بازبینیِ سئو لازم است: آن‌جا چند هزار صفحه پشتِ‌سرِ هم خوانده
     * می‌شوند و اجرای کاملِ resolve() برای هرکدام گران است. `resolve()` خودش هم
     * از همین متد استفاده می‌کند تا دو مسیرِ جدا شکل نگیرد و ابزار دقیقاً همان
     * چیزی را گزارش کند که سایت سرو می‌کند.
     *
     * @return array{title: string, description: string}
     */
    public function titleAndDescription(string $type, Model $model): array
    {
        $cfg = $this->registry->config($type) ?? [];
        $meta = method_exists($model, 'seoMeta') ? $model->seoMeta : null;

        return $this->composeText($type, $meta, $model, $this->buildContext($type, $cfg, $model));
    }

    /**
     * @param  array<string, string|null>  $context
     * @return array{title: string, description: string}
     */
    private function composeText(string $type, ?SeoMeta $meta, Model $model, array $context): array
    {
        // اولویت: پنلِ سئو (`seo_meta`) ← فیلدِ خودِ موجودیت (`meta_title`) ← قالب.
        //
        // سطحِ دوم تازه اضافه شده. تا پیش از این، کانالِ سئو ستونِ خودِ موجودیت را
        // نمی‌دید ولی کانالِ کاتالوگ می‌دید — یعنی هر صفحه‌ای که ادمین برایش متا
        // نوشته بود، از دو مسیر دو چیزِ متفاوت می‌گرفت. حالا هر دو یک منبع دارند.
        $title = $this->compose(
            self::pick($meta?->title, $this->attribute($model, 'meta_title')),
            $this->template($type, 'title'),
            $context,
            fn (string $value) => \Modules\Seo\Support\MetaLength::title($value),
        );

        // مقالاتِ بلاگ: Title باید کوتاه و یکدست باشد «{عنوانِ کوتاه} | تعمیرآنلاین»
        // (~۶۵ کاراکتر) — بدونِ پسوندهای اضافهٔ واردشده از وردپرس که Titleِ مقالات
        // را از حدِ سئو رد می‌کردند. فقط نوعِ article؛ خدمات/برندها دست‌نخورده.
        if ($type === 'article' && trim($title) !== '') {
            $title = \Modules\Site\Support\ArticleSeoTitle::build($title);
        }

        $description = $this->compose(
            self::pick($meta?->description, $this->attribute($model, 'meta_description')),
            $this->template($type, 'description'),
            $context,
            fn (string $value) => \Modules\Seo\Support\MetaLength::description($value),
        );

        return ['title' => $title, 'description' => $description];
    }

    /**
     * متنِ نهایی با یک قاعده: **نوشتهٔ دستی دست‌نخورده می‌ماند.**
     *
     * سقفِ طول فقط روی خروجیِ قالب اعمال می‌شود. قالب را ما نوشته‌ایم و بریدنش
     * بی‌ضرر است؛ ولی وقتی ادمین عمداً عنوانی تایپ کرده، کوتاه‌کردنِ بی‌خبرِ آن
     * یعنی «چیزی که نوشتم عوض شد» — و اعتماد به پنل را از بین می‌برد. اگر متنِ
     * دستی از بودجه رد شود، ابزارِ بازبینی علامتش می‌زند تا خودِ ادمین تصمیم بگیرد.
     *
     * @param  array<string, string|null>  $context
     * @param  callable(string):string  $cap
     */
    private function compose(?string $manual, ?string $template, array $context, callable $cap): string
    {
        if ($manual !== null && trim($manual) !== '') {
            return trim($this->variables->render($manual, $context));
        }

        return $cap($this->variables->render($template, $context));
    }

    /** مقدارِ یک ستون، اگر روی این مدل اصلاً وجود داشته باشد. */
    private function attribute(Model $model, string $column): ?string
    {
        $value = $model->getAttribute($column);

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(string $type, Model $model): array
    {
        $cfg = $this->registry->config($type) ?? [];
        $meta = method_exists($model, 'seoMeta') ? $model->seoMeta : null;

        $context = $this->buildContext($type, $cfg, $model);

        ['title' => $title, 'description' => $description] = $this->composeText($type, $meta, $model, $context);

        $canonical = self::pick($meta?->canonical) ?: $this->absoluteUrl($this->registry->pathFor($type, $model));

        $robotsFlags = $this->resolveRobots($type, $cfg, $model, $meta);

        $og = [
            'title' => self::pick($meta?->og_title, $title),
            'description' => self::pick($meta?->og_description, $description),
            'image' => self::pick($meta?->og_image, SeoSetting::get('og_default_image')),
            'type' => self::pick($meta?->og_type, $type === 'article' ? 'article' : 'website'),
            'url' => $canonical,
            'site_name' => $context['sitename'] ?? null,
        ];

        $twitter = [
            'card' => self::pick($meta?->twitter_card, SeoSetting::get('twitter_card'), 'summary_large_image'),
            'title' => self::pick($meta?->twitter_title, $og['title']),
            'description' => self::pick($meta?->twitter_description, $og['description']),
            'image' => self::pick($meta?->twitter_image, $og['image']),
            'site' => SeoSetting::get('twitter_site'),
            'creator' => SeoSetting::get('twitter_creator'),
        ];

        $result = [
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $this->robots->build($robotsFlags),
            'robots_directives' => $this->robots->directives($robotsFlags),
            'og' => $og,
            'twitter' => $twitter,
            'breadcrumb_title' => self::pick($meta?->breadcrumb_title, $context['title'] ?? null),
        ];

        // JSON-LD فقط برای آیتم‌های index‌پذیر تولید می‌شود. (FAQPage از بانکِ
        // مشترکِ FAQ سایت داخلِ SchemaGenerator ساخته می‌شود — منبعِ واحد.)
        $result['jsonld'] = empty($robotsFlags['noindex'])
            ? $this->schema->generate($type, $model, $result)
            : [];

        return $result;
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return array<string, string|null>
     */
    private function buildContext(string $type, array $cfg, Model $model): array
    {
        $sep = SeoSetting::get('separator') ?: (string) config('seo.separator', '–');
        $sitename = SeoSetting::get('site_name') ?: (string) config('app.name');
        $sitedesc = SeoSetting::get('site_description');

        $titleAttr = $cfg['title_attr'] ?? 'title';
        $excerptAttr = $cfg['excerpt_attr'] ?? null;

        $title = (string) ($model->getAttribute($titleAttr) ?? '');
        $excerpt = $excerptAttr ? $this->clean((string) ($model->getAttribute($excerptAttr) ?? '')) : '';

        // تورِ ایمنی: excerpt پیکربندی شده ولی خالی درآمده — یعنی fallback
        // به site_description فعال می‌شود و این صفحه دیسکریپشنِ تکراری
        // می‌گیرد. همین الگو یک‌بار ۸۹ صفحهٔ فروم را در SEMrush تکراری کرد،
        // بی‌هیچ ردی. فقط برای نوع‌هایی که excerpt_attr دارند لاگ می‌شود تا
        // resolve‌های عادیِ دستگاه/برند لاگ را پر نکنند.
        if ($excerptAttr && $excerpt === '') {
            \Illuminate\Support\Facades\Log::info('seo.meta.excerpt_empty_fallback', [
                'type' => $type,
                'id' => $model->getKey(),
            ]);
        }
        $slug = (string) ($model->getAttribute($cfg['slug'] ?? 'slug') ?? '');
        $year = (string) (int) date('Y');

        // متغیرهای دامنه‌ای — پیش‌فرض از attribute؛ برای صفحاتِ شهری از خودِ
        // رکورد (device/brand/city) تا الگویِ «%device% %brand% در %city%» درست شود.
        $brandName = (string) ($model->getAttribute('brand_name') ?? '');
        $deviceName = (string) ($model->getAttribute('device_name') ?? '');
        $city = (string) (SeoSetting::get('default_city') ?: 'تهران');
        if ($model instanceof \Modules\CRM\Models\CityPage) {
            $city = (string) ($model->city?->name ?? $city);
            $deviceName = (string) ($model->device?->name ?? $deviceName);
            $brandName = (string) ($model->brand?->name ?? $brandName);
        }

        return [
            'title' => $title,
            'slug' => $slug,
            'sitename' => $sitename,
            'site_name' => $sitename,    // نام مستعارِ خوانا برای {site_name}
            'sitedesc' => $sitedesc,
            'sep' => $sep,
            'excerpt' => $excerpt !== '' ? $excerpt : $sitedesc,
            // متغیرهای دامنه‌ای — برای قالب‌های دستگاه/برند/شهر (خالی‌ها در render حذف می‌شوند).
            'brand' => $brandName,
            'device' => $deviceName,
            'city' => $city,
            'guarantee' => (string) (SeoSetting::get('guarantee_text') ?? ''),
            'phone' => (string) (SeoSetting::get('contact_phone') ?: SeoSetting::get('lb_phone') ?: ''),
            'date' => $this->formatDate($cfg, $model),
            'currentyear' => $year,
            'year' => $year,             // نام مستعار برای {year}
            'id' => (string) ($model->getKey() ?? ''),
        ];
    }

    /**
     * ادغام پرچم‌های robots: پیش‌فرض سراسری ← override آیتم ← noindex اجباری
     * برای آیتم منتشرنشده.
     *
     * @param  array<string, mixed>  $cfg
     * @return array<string, mixed>
     */
    private function resolveRobots(string $type, array $cfg, Model $model, ?SeoMeta $meta): array
    {
        $flags = (array) config('seo.robots_default', []);

        if ($meta) {
            foreach (['noindex', 'nofollow', 'noarchive', 'noimageindex', 'nosnippet', 'notranslate'] as $f) {
                $col = 'robots_'.$f;
                if ($meta->{$col} !== null) {
                    $flags[$f] = (bool) $meta->{$col};
                }
            }
            foreach (['max_snippet', 'max_image_preview', 'max_video_preview'] as $f) {
                $col = 'robots_'.$f;
                if ($meta->{$col} !== null) {
                    $flags[$f] = $meta->{$col};
                }
            }
        }

        // آیتم منتشرنشده/زمان‌بندی‌شدهٔ آینده → noindex اجباری.
        $publishedCol = $cfg['published'] ?? null;
        if ($publishedCol) {
            $val = $model->getAttribute($publishedCol);
            $isLive = $val !== null && $val !== '' && Carbon::parse($val)->lessThanOrEqualTo(now());
            if (! $isLive) {
                $flags['noindex'] = true;
            }
        }

        // آیتمِ غیرفعال (ستون is_active = false) نباید ایندکس شود.
        if (array_key_exists('is_active', $model->getAttributes())
            && ! (bool) $model->getAttribute('is_active')) {
            $flags['noindex'] = true;
        }

        // وضعیت گردش‌کار انتشار → پیش‌نویس/بایگانی/noindex اجباراً noindex.
        if ($meta && in_array($meta->status, ['draft', 'archived', 'noindex'], true)) {
            $flags['noindex'] = true;
        }

        // سیاستِ داده‌محورِ ایندکس (نقشهٔ Search Console) — فقط وقتی ادمین
        // روی خودِ آیتم robots را صریحاً override نکرده باشد:
        //  - برندِ خارج از whitelist → noindex,follow (برندهای نحیف)
        //  - سوالِ انجمنِ زیرِ آستانهٔ بازدید → noindex,follow
        if ($meta?->robots_noindex === null) {
            if ($type === 'brand'
                && ! \Modules\Seo\Support\IndexingPolicy::brandIndexable((string) $model->getAttribute('slug'))) {
                $flags['noindex'] = true;
            }
            if ($type === 'forum_question'
                && ! \Modules\Seo\Support\IndexingPolicy::forumIndexable((int) $model->getAttribute('view_count'))) {
                $flags['noindex'] = true;
            }
        }

        return $flags;
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    private function formatDate(array $cfg, Model $model): ?string
    {
        $col = $cfg['published'] ?? null;
        $val = $col ? $model->getAttribute($col) : null;
        if (! $val) {
            return null;
        }

        return rescue(function () use ($val) {
            $carbon = Carbon::parse($val);
            if (class_exists(\Morilog\Jalali\Jalalian::class)) {
                return \Morilog\Jalali\Jalalian::fromCarbon($carbon)->format('Y/m/d');
            }

            return $carbon->format('Y-m-d');
        }, null);
    }

    private function clean(string $html): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($html)));

        return Str::limit($text, 300, '');
    }

    private function absoluteUrl(string $path): string
    {
        $base = SeoSetting::siteUrl();

        return $base.'/'.ltrim($path, '/');
    }
}
