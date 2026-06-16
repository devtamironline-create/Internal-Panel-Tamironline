<?php

namespace Modules\Seo\Services;

/**
 * منطق تطبیق یک مسیر با قاعدهٔ ریدایرکت. pure و واحد-تست‌پذیر — هم در
 * بک‌اند و هم به‌عنوان مرجع رفتار برای middleware.ts فرانت.
 */
class RedirectMatcher
{
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
}
