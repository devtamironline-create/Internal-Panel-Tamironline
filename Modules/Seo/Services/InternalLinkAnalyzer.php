<?php

namespace Modules\Seo\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceBrandPage;
use Modules\Seo\Models\LinkSuggestion;
use Modules\Seo\Models\SeoRedirect;
use Modules\Site\Models\Article;

/**
 * تحلیل‌گرِ لینک‌سازیِ داخلی — مستقیم از محتوای دیتابیس (بدونِ نیاز به کرال):
 *
 *  - جهانِ صفحات: دستگاه‌ها (/services/x)، ترکیبی‌ها (/services/x/y)،
 *    برندها (/brands/x)، مقالات (/blog/x).
 *  - یال‌ها: هر <a href> داخلی در محتوای همین صفحات.
 *  - امتیازِ هر صفحه (۰–۱۰۰)، عمقِ تقریبی، یتیم‌ها، جزیره‌ها (به تفکیکِ دستگاه)،
 *    و تولیدِ پیشنهادِ لینک طبق قوانینِ جزیره.
 *
 * فرضِ عمق: صفحاتِ مادرِ خدمات در منوی اصلی‌اند → عمقِ ۱؛ بقیه با BFS روی
 * یال‌های محتوایی. صفحهٔ بدونِ مسیرِ ورودی، عمقِ ۵ (عمیق) می‌گیرد.
 */
class InternalLinkAnalyzer
{
    public const CACHE_KEY = 'seo.internal_link_analysis';

    public const CACHE_TTL = 900; // ۱۵ دقیقه

    /** @return array<string, mixed> */
    public function analyze(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->build());
    }

    /** @return array<string, mixed> */
    private function build(): array
    {
        $pages = [];   // path => [type,label,entity_id,island,out:[],in:0,depth:null]
        $devices = Device::query()->where('is_active', true)->get(['id', 'name', 'slug']);
        $brands = Brand::query()->get(['id', 'name', 'slug'])->keyBy('id');

        foreach ($devices as $d) {
            $pages['/services/'.$d->slug] = $this->page('device', $d->name, $d->id, $d->slug);
        }

        $combos = DeviceBrandPage::query()->get(['id', 'device_id', 'brand_id', 'title', 'description', 'cta_primary_url', 'cta_secondary_url']);
        $deviceById = $devices->keyBy('id');
        foreach ($combos as $c) {
            $d = $deviceById[$c->device_id] ?? null;
            $b = $brands[$c->brand_id] ?? null;
            if (! $d || ! $b) {
                continue;
            }
            $pages['/services/'.$d->slug.'/'.$b->slug] = $this->page('combo', trim($d->name.' '.$b->name), $c->id, $d->slug);
        }

        foreach ($brands as $b) {
            $pages['/brands/'.$b->slug] = $this->page('brand', $b->name, $b->id, null);
        }

        $articles = [];
        Article::query()->where('is_published', true)
            ->select(['id', 'title', 'slug', 'content'])
            ->chunkById(100, function ($chunk) use (&$pages, &$articles) {
                foreach ($chunk as $a) {
                    $pages['/blog/'.$a->slug] = $this->page('article', (string) $a->title, $a->id, null);
                    $articles[$a->id] = ['slug' => $a->slug, 'title' => (string) $a->title, 'text' => mb_strtolower(strip_tags((string) $a->content)), 'html' => (string) $a->content];
                }
            });

        // ─── یال‌ها ───
        $edges = [];   // from => list<to>
        $addEdges = function (string $fromPath, ?string $html) use (&$edges, &$pages) {
            if (! $html) {
                return;
            }
            foreach ($this->extractPaths($html) as $to) {
                if ($to !== $fromPath && isset($pages[$to])) {
                    $edges[$fromPath][] = $to;
                }
            }
        };

        foreach ($articles as $id => $a) {
            $addEdges('/blog/'.$a['slug'], $a['html']);
        }
        foreach ($devices as $d) {
            $full = Device::query()->find($d->id, ['description', 'cta_primary_url', 'cta_secondary_url', 'faq', 'issues']);
            $blob = implode(' ', array_filter([
                $full->description, $full->cta_primary_url, $full->cta_secondary_url,
                json_encode($full->faq ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($full->issues ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]));
            $addEdges('/services/'.$d->slug, $blob);
        }
        foreach ($combos as $c) {
            $d = $deviceById[$c->device_id] ?? null;
            $b = $brands[$c->brand_id] ?? null;
            if (! $d || ! $b) {
                continue;
            }
            $addEdges('/services/'.$d->slug.'/'.$b->slug, implode(' ', array_filter([$c->description, $c->cta_primary_url, $c->cta_secondary_url])));
        }

        // شمارشِ in/out (یال‌های یکتا per from→to)
        foreach ($edges as $from => $tos) {
            $tos = array_values(array_unique($tos));
            $edges[$from] = $tos;
            $pages[$from]['out'] = count($tos);
            foreach ($tos as $to) {
                $pages[$to]['in']++;
            }
        }

        // ─── عمق (BFS از منو: دستگاه‌ها عمقِ ۱) ───
        $queue = [];
        foreach ($pages as $path => $p) {
            if ($p['type'] === 'device') {
                $pages[$path]['depth'] = 1;
                $queue[] = $path;
            }
        }
        while ($queue) {
            $cur = array_shift($queue);
            foreach ($edges[$cur] ?? [] as $to) {
                if ($pages[$to]['depth'] === null) {
                    $pages[$to]['depth'] = $pages[$cur]['depth'] + 1;
                    $queue[] = $to;
                }
            }
        }
        foreach ($pages as $path => $p) {
            if ($p['depth'] === null) {
                $pages[$path]['depth'] = 5; // بدونِ مسیرِ ورودی از منو — عمیق
            }
        }

        // ─── لینک به ریدایرکت ───
        $redirectSources = [];
        if (Schema::hasTable('seo_redirects')) {
            foreach (SeoRedirect::active()->where('match_type', 'exact')->pluck('source') as $s) {
                $redirectSources[$this->normalizePath((string) $s)] = true;
            }
        }
        $linksToRedirects = 0;
        foreach ($articles as $a) {
            foreach ($this->extractPaths($a['html']) as $to) {
                if (isset($redirectSources[$to])) {
                    $linksToRedirects++;
                }
            }
        }

        // ─── امتیاز و جزیره‌ها ───
        $islandOf = [];  // device slug => island data
        foreach ($devices as $d) {
            $islandOf[$d->slug] = ['label' => $d->name, 'mother' => '/services/'.$d->slug, 'members' => 0, 'orphans' => 0, 'score_sum' => 0, 'linked_mother' => 0, 'articles' => 0];
        }

        // island مقالات از روی نامِ دستگاه در عنوان/متن
        foreach ($articles as $id => $a) {
            $path = '/blog/'.$a['slug'];
            foreach ($devices as $d) {
                $needle = mb_strtolower($d->name);
                if ($needle !== '' && (mb_stripos($a['title'], $d->name) !== false || mb_strpos($a['text'], $needle) !== false)) {
                    $pages[$path]['island'] = $d->slug;
                    break;
                }
            }
        }

        foreach ($pages as $path => $p) {
            $score = 0;
            $score += min(40, $p['in'] * 8);
            $score += $p['out'] > 0 ? 20 : 0;
            $score += max(0, 20 - 5 * (($p['depth'] ?? 5) - 1));
            // بونوسِ اتصال به مادر برای مقاله/ترکیبی
            if ($p['island'] && in_array($p['type'], ['article', 'combo'], true)) {
                $mother = '/services/'.$p['island'];
                $linked = in_array($mother, $edges[$path] ?? [], true);
                $score += $linked ? 20 : 0;
                if (isset($islandOf[$p['island']])) {
                    $islandOf[$p['island']]['linked_mother'] += $linked ? 1 : 0;
                }
            } elseif ($p['type'] === 'device') {
                $score += 20; // مادر خودش مرجع است
            }
            $pages[$path]['score'] = min(100, $score);

            if ($p['island'] && isset($islandOf[$p['island']])) {
                $islandOf[$p['island']]['members']++;
                $islandOf[$p['island']]['score_sum'] += $pages[$path]['score'];
                $islandOf[$p['island']]['orphans'] += ($p['in'] === 0 && $p['type'] !== 'device') ? 1 : 0;
                $islandOf[$p['island']]['articles'] += $p['type'] === 'article' ? 1 : 0;
            }
        }

        $islands = [];
        foreach ($islandOf as $slug => $i) {
            $islands[$slug] = $i + ['avg' => $i['members'] > 0 ? (int) round($i['score_sum'] / $i['members']) : 0];
        }

        $all = collect($pages);

        return [
            'generated_at' => now()->toDateTimeString(),
            'pages' => $pages,
            'edges' => $edges,
            'islands' => $islands,
            'stats' => [
                'total' => $all->count(),
                'avg_score' => (int) round($all->avg('score')),
                'orphans' => $all->filter(fn ($p) => $p['in'] === 0 && $p['type'] !== 'device')->count(),
                'low_link' => $all->filter(fn ($p) => $p['in'] <= 1 && $p['type'] !== 'device')->count(),
                'links_to_redirects' => $linksToRedirects,
                'deep_pages' => $all->filter(fn ($p) => $p['depth'] >= 4)->count(),
            ],
            'depth_dist' => $all->groupBy('depth')->map(fn ($g) => ['count' => $g->count(), 'avg' => (int) round($g->avg('score'))])->sortKeys()->toArray(),
        ];
    }

    /**
     * تولیدِ پیشنهادها طبق قوانینِ جزیره (فقط ردیف‌های جدید؛ تکراری‌ها رد می‌شوند).
     *
     * @return array{created:int, skipped:int}
     */
    public function generateSuggestions(): array
    {
        $analysis = $this->analyze(true);
        $pages = $analysis['pages'];
        $edges = $analysis['edges'];

        $devices = Device::query()->where('is_active', true)->get(['id', 'name', 'slug'])->keyBy('slug');
        $brandNames = Brand::query()->get(['id', 'name', 'slug'])->keyBy('id');
        $combosByDevice = DeviceBrandPage::query()->get(['id', 'device_id', 'brand_id'])
            ->groupBy('device_id');

        $created = 0;
        $skipped = 0;

        Article::query()->where('is_published', true)
            ->select(['id', 'title', 'slug', 'content'])
            ->chunkById(100, function ($chunk) use (&$created, &$skipped, $pages, $edges, $devices, $brandNames, $combosByDevice) {
                foreach ($chunk as $a) {
                    $path = '/blog/'.$a->slug;
                    $page = $pages[$path] ?? null;
                    if (! $page || ! $page['island']) {
                        continue;
                    }
                    $device = $devices[$page['island']] ?? null;
                    if (! $device) {
                        continue;
                    }

                    $text = strip_tags((string) $a->content);
                    $linked = $edges[$path] ?? [];

                    // قانون ۱: لینک به صفحهٔ مادر (اولویتِ بالا)
                    $mother = '/services/'.$device->slug;
                    if (! in_array($mother, $linked, true) && mb_stripos($text, $device->name) !== false) {
                        $created += $this->push($a, $path, $mother, $device->name, 'مقالهٔ جزیرهٔ '.$device->name.' باید به صفحهٔ مادر لینک بدهد', $device->slug, 'high') ? 1 : 0;
                    }

                    // قانون ۲: نامِ برند در متن → لینک به صفحهٔ ترکیبیِ همان برند (متوسط)
                    foreach ($combosByDevice[$device->id] ?? [] as $combo) {
                        $brand = $brandNames[$combo->brand_id] ?? null;
                        if (! $brand) {
                            continue;
                        }
                        $comboPath = '/services/'.$device->slug.'/'.$brand->slug;
                        if (in_array($comboPath, $linked, true) || ! isset($pages[$comboPath])) {
                            continue;
                        }
                        $anchor = trim($device->name.' '.$brand->name);
                        $found = mb_stripos($text, $anchor) !== false ? $anchor
                            : (mb_stripos($text, $brand->name) !== false ? $brand->name : null);
                        if ($found) {
                            $created += $this->push($a, $path, $comboPath, $found, 'اشاره به برند '.$brand->name.' — لینک به صفحهٔ برندِ همین دستگاه', $device->slug, 'medium') ? 1 : 0;
                        }
                    }
                }
            });

        return ['created' => $created, 'skipped' => $skipped];
    }

    /** اعمالِ یک پیشنهادِ تأییدشده: اولین رخدادِ انکر بیرون از تگ‌ها لینک می‌شود. */
    public function applySuggestion(LinkSuggestion $s): bool
    {
        if ($s->entity_type !== 'article' || $s->status !== 'approved') {
            return false;
        }
        $article = Article::query()->find($s->entity_id);
        if (! $article) {
            return false;
        }

        $html = (string) $article->content;
        $new = $this->wrapFirstOccurrence($html, $s->anchor, $s->target_path);
        if ($new === null) {
            return false;
        }

        $article->content = $new;
        $article->save();

        $s->update([
            'status' => 'applied',
            'applied_before' => $html,
            'applied_field' => 'content',
            'applied_at' => now(),
        ]);

        return true;
    }

    public function revertSuggestion(LinkSuggestion $s): bool
    {
        if ($s->status !== 'applied' || $s->applied_before === null) {
            return false;
        }
        $article = Article::query()->find($s->entity_id);
        if (! $article) {
            return false;
        }
        $article->content = $s->applied_before;
        $article->save();
        $s->update(['status' => 'approved', 'reverted_at' => now(), 'applied_at' => null]);

        return true;
    }

    // ─── کمکی‌ها ───────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function page(string $type, string $label, int $id, ?string $island): array
    {
        return ['type' => $type, 'label' => $label, 'entity_id' => $id, 'island' => $island, 'out' => 0, 'in' => 0, 'depth' => null, 'score' => 0];
    }

    /** استخراجِ مسیرهای داخلی از hrefهای یک HTML. @return list<string> */
    private function extractPaths(string $html): array
    {
        if (! preg_match_all('~href=["\']([^"\']+)["\']~iu', $html, $m)) {
            return [];
        }
        $host = ContentLinkFixer::canonicalHost();
        $out = [];
        foreach ($m[1] as $href) {
            $href = html_entity_decode(trim($href));
            if (str_starts_with($href, 'http')) {
                $h = (string) parse_url($href, PHP_URL_HOST);
                if (strcasecmp((string) preg_replace('~^www\.~i', '', $h), $host) !== 0) {
                    continue;
                }
                $href = (string) (parse_url($href, PHP_URL_PATH) ?: '/');
            }
            if (! str_starts_with($href, '/')) {
                continue;
            }
            $out[] = $this->normalizePath($href);
        }

        return $out;
    }

    private function normalizePath(string $path): string
    {
        $path = rawurldecode((string) (parse_url($path, PHP_URL_PATH) ?: $path));

        return '/'.trim($path, '/');
    }

    private function push(Article $a, string $path, string $target, string $anchor, string $reason, string $island, string $priority): bool
    {
        return (bool) LinkSuggestion::query()->firstOrCreate(
            ['entity_type' => 'article', 'entity_id' => $a->id, 'target_path' => $target],
            [
                'page_path' => $path,
                'page_label' => mb_substr((string) $a->title, 0, 300),
                'anchor' => mb_substr($anchor, 0, 300),
                'reason' => mb_substr($reason, 0, 300),
                'island' => $island,
                'priority' => $priority,
                'status' => 'pending',
            ],
        )->wasRecentlyCreated;
    }

    /**
     * پیچیدنِ اولین رخدادِ انکر (بیرون از هر تگ و بیرون از <a> موجود) در لینک.
     * null یعنی انکر در متنِ آزاد پیدا نشد.
     */
    public function wrapFirstOccurrence(string $html, string $anchor, string $target): ?string
    {
        $parts = preg_split('~(<[^>]*>)~u', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return null;
        }

        $inAnchor = 0;
        foreach ($parts as $i => $part) {
            if ($part !== '' && $part[0] === '<') {
                if (preg_match('~^<a\b~i', $part)) {
                    $inAnchor++;
                } elseif (preg_match('~^</a>~i', $part)) {
                    $inAnchor = max(0, $inAnchor - 1);
                }

                continue;
            }
            if ($inAnchor > 0 || $part === '') {
                continue;
            }
            $pos = mb_stripos($part, $anchor);
            if ($pos === false) {
                continue;
            }
            $actual = mb_substr($part, $pos, mb_strlen($anchor));
            $parts[$i] = mb_substr($part, 0, $pos)
                .'<a href="'.e($target).'">'.$actual.'</a>'
                .mb_substr($part, $pos + mb_strlen($anchor));

            return implode('', $parts);
        }

        return null;
    }
}
