<?php

namespace Modules\Seo\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Seo\Models\SeoMeta;

/**
 * گزارش هم‌نوع‌خواری کلمات کلیدی (Keyword Cannibalization) و محتوای تکراری:
 * یافتن کلمات کلیدی هدفِ بیش از یک صفحه و عنوان‌های سئوی تکراری.
 */
class CannibalizationReport
{
    /** سقف تعداد گروه‌های برگشتی برای سبک‌ماندن گزارش. */
    private const LIMIT = 200;

    /**
     * نگاشت «کلاس مدل => نوعِ رجیستری» (به‌ازای هر نمونه کش می‌شود) برای resolveUrl.
     *
     * @var array<class-string,string>|null
     */
    private ?array $typeByClass = null;

    public function __construct(private SeoableRegistry $registry) {}

    /**
     * کلمات کلیدی اصلی (focus keyword) که بیش از یک صفحهٔ متمایز آن‌ها را هدف گرفته‌اند.
     * ردیف‌های seo_meta دارای focus_keywords را chunk می‌کند و نگاشتِ
     * keyword => list از صفحات می‌سازد؛ فقط کلماتِ دارای ≥ ۲ صفحهٔ متمایز را برمی‌گرداند.
     *
     * @return list<array{keyword:string,pages:list<array{title:?string,url:?string}>}>
     */
    public function focusKeywordConflicts(): array
    {
        // keyword => [ "type#id" => ['seoable_type'=>..,'seoable_id'=>..,'title'=>..] ]
        $map = [];

        SeoMeta::query()
            ->select('id', 'seoable_type', 'seoable_id', 'title', 'focus_keywords', 'canonical')
            ->whereNotNull('focus_keywords')
            ->where('focus_keywords', '!=', '')
            ->where('focus_keywords', '!=', '[]')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$map) {
                foreach ($rows as $row) {
                    $keywords = $row->focus_keywords; // cast → array
                    if (! is_array($keywords)) {
                        continue;
                    }

                    $pageKey = $row->seoable_type.'#'.$row->seoable_id;

                    foreach ($keywords as $kw) {
                        $norm = $this->normalize((string) $kw);
                        if ($norm === '') {
                            continue;
                        }

                        // صفحهٔ متمایز را یک‌بار به‌ازای هر کلمه نگه می‌داریم.
                        $map[$norm][$pageKey] ??= [
                            'seoable_type' => (string) $row->seoable_type,
                            'seoable_id' => (int) $row->seoable_id,
                            'title' => $row->title,
                        ];
                    }
                }
            });

        $conflicts = [];

        foreach ($map as $keyword => $pages) {
            if (count($pages) < 2) {
                continue;
            }

            $conflicts[] = [
                'keyword' => (string) $keyword,
                'count' => count($pages),
                'pages_raw' => array_values($pages),
            ];
        }

        // پرتکرارترین تداخل‌ها ابتدا؛ سپس سقف اعمال می‌شود.
        usort($conflicts, fn ($a, $b) => $b['count'] <=> $a['count']);
        $conflicts = array_slice($conflicts, 0, self::LIMIT);

        // URLها را فقط برای صفحاتِ داخل گروه‌های تداخل resolve می‌کنیم.
        return array_map(function (array $group) {
            $pages = array_map(function (array $p) {
                return [
                    'title' => $p['title'],
                    'url' => $this->resolveUrl($p['seoable_type'], $p['seoable_id']),
                ];
            }, $group['pages_raw']);

            return [
                'keyword' => $group['keyword'],
                'pages' => $pages,
            ];
        }, $conflicts);
    }

    /**
     * صفحاتی که عنوان سئوی (title) یکسانِ نرمال‌شده دارند:
     * GROUP BY title HAVING COUNT(*) > 1 روی عنوان‌های غیرخالی، سپس واکشی ردیف‌های آن گروه‌ها.
     *
     * @return list<array{title:string,pages:list<array{title:?string,url:?string}>}>
     */
    public function duplicateTitles(): array
    {
        // مرحلهٔ ۱: عنوان‌هایی که بیش از یک ردیف دارند (نرمال‌سازی روی LOWER(TRIM())).
        $dupes = SeoMeta::query()
            ->selectRaw('LOWER(TRIM(title)) as norm_title')
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->groupBy('norm_title')
            ->havingRaw('COUNT(*) > 1')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(self::LIMIT)
            ->pluck('norm_title')
            ->all();

        if (empty($dupes)) {
            return [];
        }

        $dupeSet = array_flip($dupes);

        // مرحلهٔ ۲: ردیف‌های متعلق به آن گروه‌ها را واکشی و بر اساس عنوانِ نرمال‌شده گروه می‌کنیم.
        $groups = [];

        SeoMeta::query()
            ->select('id', 'seoable_type', 'seoable_id', 'title')
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$groups, $dupeSet) {
                foreach ($rows as $row) {
                    $norm = $this->normalize((string) $row->title);
                    if ($norm === '' || ! isset($dupeSet[$norm])) {
                        continue;
                    }

                    $groups[$norm][] = [
                        'seoable_type' => (string) $row->seoable_type,
                        'seoable_id' => (int) $row->seoable_id,
                        'title' => $row->title,
                    ];
                }
            });

        $result = [];

        foreach ($groups as $norm => $pages) {
            if (count($pages) < 2) {
                continue;
            }

            $result[] = [
                'title' => (string) $norm,
                'pages' => array_map(function (array $p) {
                    return [
                        'title' => $p['title'],
                        'url' => $this->resolveUrl($p['seoable_type'], $p['seoable_id']),
                    ];
                }, $pages),
            ];
        }

        usort($result, fn ($a, $b) => count($b['pages']) <=> count($a['pages']));

        return $result;
    }

    /** نرمال‌سازی یک رشته: trim + حروف کوچک (برای مقایسهٔ کلمه/عنوان). */
    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    /**
     * تبدیل (seoable_type + id) به مسیر عمومی با استفاده از رجیستری.
     * کلاسِ مدل → نوعِ رجیستری (cfg['model'] === type) → find($id) → pathFor.
     * نوع‌های resolver/مجازی (دارای کلید resolver) نادیده گرفته می‌شوند.
     */
    private function resolveUrl(string $seoableType, int $id): ?string
    {
        $type = $this->typeForClass($seoableType);
        if ($type === null) {
            return null;
        }

        /** @var class-string<Model> $seoableType */
        if (! class_exists($seoableType)) {
            return null;
        }

        /** @var Model|null $model */
        $model = $seoableType::find($id);
        if (! $model) {
            return null;
        }

        return $this->registry->pathFor($type, $model);
    }

    /**
     * نگاشتِ کش‌شدهٔ «کلاس مدل => نوع رجیستری»، با حذف نوع‌های resolver/مجازی.
     */
    private function typeForClass(string $class): ?string
    {
        if ($this->typeByClass === null) {
            $this->typeByClass = [];

            foreach ($this->registry->all() as $type => $cfg) {
                // نوع‌های resolver/مجازی ردیفِ DB واحد ندارند → skip.
                if (! empty($cfg['resolver'])) {
                    continue;
                }

                $modelClass = $cfg['model'] ?? null;
                if (is_string($modelClass) && $modelClass !== '') {
                    // اولین نوعِ متناظر با یک کلاس را نگه می‌داریم.
                    $this->typeByClass[$modelClass] ??= $type;
                }
            }
        }

        return $this->typeByClass[$class] ?? null;
    }
}
