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
     * @return array<string, mixed>
     */
    public function resolve(string $type, Model $model): array
    {
        $cfg = $this->registry->config($type) ?? [];
        $meta = method_exists($model, 'seoMeta') ? $model->seoMeta : null;

        $context = $this->buildContext($type, $cfg, $model);

        $title = $this->variables->render(
            self::pick($meta?->title, config("seo.templates.$type.title"), config('seo.templates.global.title')),
            $context
        );

        $description = $this->variables->render(
            self::pick($meta?->description, config("seo.templates.$type.description"), config('seo.templates.global.description')),
            $context
        );

        $canonical = self::pick($meta?->canonical) ?: $this->absoluteUrl($this->registry->pathFor($type, $model));

        $robotsFlags = $this->resolveRobots($cfg, $model, $meta);

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

        // JSON-LD فقط برای آیتم‌های index‌پذیر تولید می‌شود.
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

        return [
            'title' => $title,
            'sitename' => $sitename,
            'sitedesc' => $sitedesc,
            'sep' => $sep,
            'excerpt' => $excerpt !== '' ? $excerpt : $sitedesc,
            'date' => $this->formatDate($cfg, $model),
            'currentyear' => (string) (int) date('Y'),
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
    private function resolveRobots(array $cfg, Model $model, ?SeoMeta $meta): array
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
        $base = rtrim((string) (SeoSetting::get('canonical_base_url') ?: config('app.url')), '/');

        return $base.'/'.ltrim($path, '/');
    }
}
