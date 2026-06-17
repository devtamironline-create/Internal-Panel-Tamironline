<?php

namespace Modules\Seo\Services;

/**
 * تحلیل و امتیازدهیِ محتوا به سبک Rank Math (۰ تا ۱۰۰ + چک‌لیست رنگی).
 * کاملاً pure است (بدون DB/config) تا واحد-تست‌پذیر بماند؛ caller محتوا و
 * متادیتا را به‌صورت آرایه پاس می‌دهد.
 *
 * status هر تست: good | ok | bad.
 */
class SeoScorer
{
    private const GOOD = 'good';

    private const OK = 'ok';

    private const BAD = 'bad';

    /**
     * @param  array{keyword?:string,title?:string,description?:string,url?:string,content?:string,images?:array<int,array{alt?:string}>,base_host?:string}  $input
     * @return array{score:int,checks:list<array{id:string,label:string,status:string,weight:int}>,stats:array<string,mixed>}
     */
    public function analyze(array $input): array
    {
        $keyword = trim((string) ($input['keyword'] ?? ''));
        $title = (string) ($input['title'] ?? '');
        $description = (string) ($input['description'] ?? '');
        $url = (string) ($input['url'] ?? '');
        $html = (string) ($input['content'] ?? '');
        $images = (array) ($input['images'] ?? []);
        $baseHost = (string) ($input['base_host'] ?? '');

        $text = $this->plain($html);
        $words = $this->wordCount($text);
        $kw = $this->lower($keyword);
        $headings = $this->headings($html);
        [$internal, $external] = $this->links($html, $baseHost);
        $firstChunk = $this->firstChunk($text, 0.1);
        $occurrences = $kw !== '' ? mb_substr_count($this->lower($text), $kw) : 0;
        $density = $words > 0 && $kw !== '' ? round($occurrences / $words * 100, 2) : 0.0;

        $checks = [];
        $add = function (string $id, string $label, string $status, int $weight = 2) use (&$checks) {
            $checks[] = compact('id', 'label', 'status', 'weight');
        };

        // ── کلمهٔ کلیدی ──
        $add('focus_keyword', 'تعیین کلمهٔ کلیدی کانونی', $kw !== '' ? self::GOOD : self::BAD, 3);

        if ($kw !== '') {
            $add('kw_in_title', 'کلمهٔ کلیدی در عنوان سئو', str_contains($this->lower($title), $kw) ? self::GOOD : self::BAD);
            $add('kw_title_start', 'کلمهٔ کلیدی نزدیک ابتدای عنوان', $this->near($this->lower($title), $kw) ? self::GOOD : self::OK, 1);
            $add('kw_in_description', 'کلمهٔ کلیدی در توضیحات متا', str_contains($this->lower($description), $kw) ? self::GOOD : self::BAD);
            $add('kw_in_url', 'کلمهٔ کلیدی در URL', str_contains($this->lower($url), $kw) || str_contains($this->lower($url), $this->slugify($kw)) ? self::GOOD : self::OK);
            $add('kw_in_first', 'کلمهٔ کلیدی در ابتدای محتوا (۱۰٪ اول)', str_contains($this->lower($firstChunk), $kw) ? self::GOOD : self::BAD);
            $add('kw_in_subheading', 'کلمهٔ کلیدی در حداقل یک زیرتیتر', $this->anyContains($headings['sub'], $kw) ? self::GOOD : self::OK);
            $add('kw_in_content', 'استفاده از کلمهٔ کلیدی در محتوا', $occurrences > 0 ? self::GOOD : self::BAD);
            $add('kw_density', "تراکم کلمهٔ کلیدی ({$density}%)", $this->densityStatus($density));
            $add('kw_in_alt', 'کلمهٔ کلیدی در alt تصویر', $this->keywordInAlt($images, $kw));
        }

        // ── طول‌ها و ساختار ──
        $titleLen = mb_strlen($title);
        $add('title_length', "طول عنوان ({$titleLen})", $titleLen >= 15 && $titleLen <= 60 ? self::GOOD : ($titleLen > 0 ? self::OK : self::BAD));
        $descLen = mb_strlen($description);
        $add('description_length', "طول توضیحات ({$descLen})", $descLen >= 50 && $descLen <= 160 ? self::GOOD : ($descLen > 0 ? self::OK : self::BAD));
        $add('content_length', "طول محتوا ({$words} کلمه)", $words >= 600 ? self::GOOD : ($words >= 300 ? self::OK : self::BAD));

        // ── لینک‌ها و رسانه ──
        $add('has_internal_link', 'وجود لینک داخلی', $internal > 0 ? self::GOOD : self::BAD);
        $add('has_external_link', 'وجود لینک خارجی', $external > 0 ? self::GOOD : self::OK);
        $add('has_images', 'وجود تصویر در محتوا', count($images) > 0 ? self::GOOD : self::OK);

        // ── خوانایی ──
        $add('has_subheadings', 'استفاده از زیرتیتر', count($headings['sub']) > 0 ? self::GOOD : ($words < 300 ? self::OK : self::BAD), 1);
        $add('short_paragraphs', 'پاراگراف‌های کوتاه', $this->paragraphStatus($html), 1);

        return [
            'score' => $this->score($checks),
            'checks' => $checks,
            'stats' => [
                'words' => $words,
                'density' => $density,
                'occurrences' => $occurrences,
                'internal_links' => $internal,
                'external_links' => $external,
                'images' => count($images),
                'subheadings' => count($headings['sub']),
            ],
        ];
    }

    /** @param list<array{status:string,weight:int}> $checks */
    private function score(array $checks): int
    {
        $val = ['good' => 1.0, 'ok' => 0.5, 'bad' => 0.0];
        $sum = 0.0;
        $max = 0.0;
        foreach ($checks as $c) {
            $sum += ($val[$c['status']] ?? 0) * $c['weight'];
            $max += $c['weight'];
        }

        return $max > 0 ? (int) round($sum / $max * 100) : 0;
    }

    private function densityStatus(float $d): string
    {
        if ($d >= 0.5 && $d <= 2.5) {
            return self::GOOD;
        }
        if ($d > 0 && $d <= 3.5) {
            return self::OK;
        }

        return self::BAD;
    }

    /** @param array<int,array{alt?:string}> $images */
    private function keywordInAlt(array $images, string $kw): string
    {
        if ($images === []) {
            return self::BAD;
        }
        foreach ($images as $img) {
            if (str_contains($this->lower((string) ($img['alt'] ?? '')), $kw)) {
                return self::GOOD;
            }
        }

        return self::OK;
    }

    private function paragraphStatus(string $html): string
    {
        if (! preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $m)) {
            return self::OK;
        }
        $long = 0;
        foreach ($m[1] as $p) {
            if ($this->wordCount($this->plain($p)) > 120) {
                $long++;
            }
        }

        return $long === 0 ? self::GOOD : ($long <= 2 ? self::OK : self::BAD);
    }

    /** @return array{all:list<string>,sub:list<string>} */
    private function headings(string $html): array
    {
        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $html, $m, PREG_SET_ORDER);
        $all = [];
        $sub = [];
        foreach ($m as $h) {
            $txt = $this->plain($h[2]);
            $all[] = $txt;
            if (in_array($h[1], ['2', '3'], true)) {
                $sub[] = $txt;
            }
        }

        return ['all' => $all, 'sub' => $sub];
    }

    /** @return array{0:int,1:int} [internal, external] */
    private function links(string $html, string $baseHost): array
    {
        preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\']/i', $html, $m);
        $internal = 0;
        $external = 0;
        foreach ($m[1] as $href) {
            $href = trim($href);
            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                continue;
            }
            $host = parse_url($href, PHP_URL_HOST);
            if ($host === null || $host === '' || ($baseHost !== '' && $host === $baseHost)) {
                $internal++;
            } else {
                $external++;
            }
        }

        return [$internal, $external];
    }

    /** @param list<string> $items */
    private function anyContains(array $items, string $needle): bool
    {
        foreach ($items as $i) {
            if (str_contains($this->lower($i), $needle)) {
                return true;
            }
        }

        return false;
    }

    private function near(string $haystack, string $needle): bool
    {
        $pos = mb_strpos($haystack, $needle);

        return $pos !== false && $pos <= max(10, (int) (mb_strlen($haystack) * 0.3));
    }

    private function firstChunk(string $text, float $ratio): string
    {
        $len = (int) max(120, mb_strlen($text) * $ratio);

        return mb_substr($text, 0, $len);
    }

    private function wordCount(string $text): int
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        return $text === '' ? 0 : count(explode(' ', $text));
    }

    private function plain(string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags($html)));
    }

    private function lower(string $s): string
    {
        return mb_strtolower($s, 'UTF-8');
    }

    private function slugify(string $s): string
    {
        return trim(preg_replace('/\s+/u', '-', $s));
    }
}
