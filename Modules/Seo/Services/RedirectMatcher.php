<?php

namespace Modules\Seo\Services;

use Modules\Seo\Models\SeoRedirect;

/**
 * منطق تطبیق یک مسیر با قاعدهٔ ریدایرکت. pure و واحد-تست‌پذیر — هم در
 * بک‌اند و هم به‌عنوان مرجع رفتار برای middleware.ts فرانت.
 */
class RedirectMatcher
{
    /** حداکثر عمق دنبال‌کردن زنجیره برای جلوگیری از حلقهٔ بی‌نهایت. */
    public const MAX_HOPS = 15;

    /**
     * @param  array{source:string,match_type:string}  $rule
     */
    public function matches(array $rule, string $path): bool
    {
        $source = $rule['source'] ?? '';
        $type = $rule['match_type'] ?? 'exact';

        if ($source === '') {
            return false;
        }

        return match ($type) {
            'exact' => $this->norm($path) === $this->norm($source),
            'contains' => str_contains($path, $source),
            'start' => str_starts_with($path, $source),
            'end' => str_ends_with($this->norm($path), $this->norm($source)),
            'regex' => $this->regexMatch($source, $path),
            default => false,
        };
    }

    /**
     * اولین قاعده‌ای که مطابقت دارد (به ترتیب ورودی).
     *
     * @param  iterable<array{source:string,match_type:string,target?:?string,status_code?:int}>  $rules
     * @return array{source:string,match_type:string,target?:?string,status_code?:int}|null
     */
    public function firstMatch(iterable $rules, string $path): ?array
    {
        foreach ($rules as $rule) {
            if ($this->matches($rule, $path)) {
                return $rule;
            }
        }

        return null;
    }

    private function regexMatch(string $pattern, string $path): bool
    {
        // الگو بدون delimiter ذخیره می‌شود؛ با کنترل خطا کامپایل می‌کنیم.
        $delimited = '#'.str_replace('#', '\#', $pattern).'#';

        return @preg_match($delimited, $path) === 1;
    }

    private function norm(string $p): string
    {
        // نرمال‌سازی trailing slash (به‌جز ریشه) برای تطبیق exact/end.
        $p = rtrim($p, '/');

        return $p === '' ? '/' : $p;
    }

    /**
     * آیا افزودن ریدایرکت source→target یک حلقه می‌سازد؟ از target شروع کرده و
     * روی ریدایرکت‌های فعال موجود hop می‌زنیم؛ اگر دوباره به source برسیم یا به
     * یک گرهٔ تکراری، حلقه است. برای قطعیت فقط hopهای exact-source را دنبال می‌کنیم.
     *
     * @param  iterable<array{source:string,match_type:string,target?:?string,status_code?:int}>|null  $rules
     */
    public function wouldLoop(string $source, string $target, ?int $ignoreId = null, ?iterable $rules = null): bool
    {
        $source = $this->norm($source);
        $target = $this->norm($target);

        if ($target === '') {
            return false;
        }

        // مستقیماً به خودش برمی‌گردد.
        if ($target === $source) {
            return true;
        }

        $rules = $rules ?? $this->activeRules($ignoreId);
        // فقط ریدایرکت‌های exact با مقصد را برای دنبال‌کردن قطعی نگه می‌داریم.
        $map = $this->exactTargetMap($rules);

        $current = $target;
        $seen = [];

        for ($i = 0; $i < self::MAX_HOPS; $i++) {
            if (! isset($map[$current])) {
                return false; // زنجیره بدون بازگشت به source تمام شد.
            }
            $next = $map[$current];
            if ($next === $source) {
                return true; // به مبدأ بازگشت → حلقه.
            }
            if (isset($seen[$next])) {
                return true; // حلقهٔ موجود میان ریدایرکت‌های دیگر.
            }
            $seen[$next] = true;
            $current = $next;
        }

        return true; // به سقف عمق رسیدیم → حلقه فرض می‌شود.
    }

    /**
     * زنجیرهٔ ریدایرکت‌ها را از $path دنبال می‌کند تا جایی که تطبیقی نباشد یا حلقه
     * شناسایی شود.
     *
     * @param  iterable<array{source:string,match_type:string,target?:?string,status_code?:int}>|null  $rules
     * @return array{final:string,hops:int,looped:bool}
     */
    public function resolveChain(string $path, int $maxHops = self::MAX_HOPS, ?iterable $rules = null): array
    {
        $rules = $rules ?? $this->activeRules();
        $map = $this->exactTargetMap($rules);

        $current = $this->norm($path);
        $seen = [$current => true];
        $hops = 0;

        for ($i = 0; $i < $maxHops; $i++) {
            if (! isset($map[$current])) {
                return ['final' => $current, 'hops' => $hops, 'looped' => false];
            }
            $next = $map[$current];
            $hops++;
            if (isset($seen[$next])) {
                return ['final' => $next, 'hops' => $hops, 'looped' => true];
            }
            $seen[$next] = true;
            $current = $next;
        }

        return ['final' => $current, 'hops' => $hops, 'looped' => true];
    }

    /**
     * ریدایرکت‌های فعال از دیتابیس (با امکان نادیده‌گرفتن یک id).
     *
     * @return array<int,array{source:string,match_type:string,target:?string,status_code:int}>
     */
    private function activeRules(?int $ignoreId = null): array
    {
        return SeoRedirect::query()
            ->active()
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->get(['source', 'target', 'status_code', 'match_type'])
            ->map(fn ($r) => [
                'source' => (string) $r->source,
                'target' => $r->target,
                'status_code' => (int) $r->status_code,
                'match_type' => (string) $r->match_type,
            ])
            ->all();
    }

    /**
     * نگاشت normalized-source → normalized-target فقط برای قواعد exact با مقصد.
     *
     * @param  iterable<array{source:string,match_type:string,target?:?string,status_code?:int}>  $rules
     * @return array<string,string>
     */
    private function exactTargetMap(iterable $rules): array
    {
        $map = [];
        foreach ($rules as $rule) {
            if (($rule['match_type'] ?? 'exact') !== 'exact') {
                continue;
            }
            $target = $rule['target'] ?? null;
            if ($target === null || $target === '') {
                continue;
            }
            $src = $this->norm((string) ($rule['source'] ?? ''));
            if ($src === '') {
                continue;
            }
            // اولین قاعده برای هر مبدأ برنده است (سازگار با firstMatch).
            if (! isset($map[$src])) {
                $map[$src] = $this->norm((string) $target);
            }
        }

        return $map;
    }
}
