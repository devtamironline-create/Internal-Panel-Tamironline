<?php

namespace App\Support\ActivityLog;

use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * خواندن و فیلترِ گزارشِ فعالیت از فایل‌های روزانهٔ JSON-Lines.
 *
 * فایل‌ها با چرخشِ Monolog ساخته می‌شوند:
 *   storage/logs/activity/activity-YYYY-MM-DD.log
 *
 * برای امنیتِ حافظه، هر جستجو حداکثر config('activity-log.max_scan') ردیفِ
 * منطبق را جمع می‌کند و در صورتِ رسیدن به سقف، پرچمِ truncated برمی‌گرداند
 * تا UI به کاربر بگوید فیلتر را محدودتر کند.
 */
class ActivityLogReader
{
    public function __construct(private readonly ?string $directory = null) {}

    public function directory(): string
    {
        return $this->directory ?: storage_path('logs/activity');
    }

    /**
     * تاریخ‌هایی که فایل لاگ دارند (نزولی).
     *
     * @return list<string> فرمت Y-m-d
     */
    public function availableDates(): array
    {
        $dates = [];
        foreach (glob($this->directory().'/activity-*.log') ?: [] as $file) {
            if (preg_match('/activity-(\d{4}-\d{2}-\d{2})\.log$/', $file, $m)) {
                $dates[] = $m[1];
            }
        }
        rsort($dates);

        return $dates;
    }

    /**
     * جستجوی فیلترشده با صفحه‌بندی.
     *
     * @param  array{from?:string,to?:string,action?:string,entity?:string,user_id?:string|int,q?:string,ip?:string}  $filters
     * @return array{items: LengthAwarePaginator, stats: array<string,int>, truncated: bool, scanned: int}
     */
    public function search(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $maxScan = max(100, (int) config('activity-log.max_scan', 5000));

        $from = $this->date($filters['from'] ?? null);
        $to = $this->date($filters['to'] ?? null);

        $matches = [];
        $stats = array_fill_keys(array_keys(ActivityRegistry::ACTIONS), 0);
        $truncated = false;

        foreach ($this->availableDates() as $date) {
            if ($from && $date < $from->format('Y-m-d')) {
                continue;
            }
            if ($to && $date > $to->format('Y-m-d')) {
                continue;
            }

            $dayMatches = [];
            foreach ($this->readLines($date) as $entry) {
                if (! $this->passes($entry, $filters)) {
                    continue;
                }
                $action = $entry['action'] ?? 'unknown';
                $stats[$action] = ($stats[$action] ?? 0) + 1;
                $dayMatches[] = $entry;

                if (count($matches) + count($dayMatches) >= $maxScan) {
                    $truncated = true;
                    break;
                }
            }

            // داخلِ هر فایل ترتیب صعودی است؛ برای نمایشِ «جدیدترین اول» معکوس می‌کنیم.
            $matches = array_merge($matches, array_reverse($dayMatches));

            if ($truncated) {
                break;
            }
        }

        $total = count($matches);
        $page = max(1, $page);
        $slice = array_slice($matches, ($page - 1) * $perPage, $perPage);

        $paginator = new LengthAwarePaginator($slice, $total, $perPage, $page, [
            'path' => request()?->url() ?? '',
            'query' => request()?->query() ?? [],
        ]);

        return [
            'items' => $paginator,
            'stats' => array_filter($stats),
            'truncated' => $truncated,
            'scanned' => $total,
        ];
    }

    /**
     * خواندنِ ردیف‌های یک روز به‌صورتِ generator (بدونِ بارگذاریِ کلِ فایل).
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function readLines(string $date): \Generator
    {
        $path = $this->directory().'/activity-'.$date.'.log';
        if (! is_file($path) || ! is_readable($path)) {
            return;
        }

        $handle = @fopen($path, 'rb');
        if (! $handle) {
            return;
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $decoded = json_decode($line, true);
                if (! is_array($decoded)) {
                    continue; // خطِ خراب/ناقص نادیده گرفته می‌شود
                }
                $entry = $this->normalize($decoded);
                if ($entry !== null) {
                    yield $entry;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * ساختارِ خروجیِ Monolog را به رکوردِ تختِ قابلِ نمایش تبدیل می‌کند.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>|null
     */
    private function normalize(array $raw): ?array
    {
        $context = $raw['context'] ?? [];
        if (! is_array($context) || ! isset($context['action'])) {
            return null;
        }

        $datetime = $raw['datetime'] ?? null;
        try {
            $at = $datetime ? CarbonImmutable::parse(is_array($datetime) ? ($datetime['date'] ?? 'now') : $datetime) : null;
        } catch (\Throwable) {
            $at = null;
        }

        return [
            'at' => $at,
            'action' => (string) $context['action'],
            'description' => $context['description'] ?? null,
            'entity' => $context['entity'] ?? null,
            'entity_label' => $context['entity_label'] ?? ActivityRegistry::label($context['entity'] ?? null),
            'entity_id' => $context['entity_id'] ?? null,
            'entity_title' => $context['entity_title'] ?? null,
            'changes' => is_array($context['changes'] ?? null) ? $context['changes'] : [],
            'user_id' => $context['user_id'] ?? null,
            'user_name' => $context['user_name'] ?? '—',
            'user_type' => $context['user_type'] ?? null,
            'ip' => $context['ip'] ?? null,
            'method' => $context['method'] ?? null,
            'path' => $context['path'] ?? null,
            'meta' => is_array($context['meta'] ?? null) ? $context['meta'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $filters
     */
    private function passes(array $entry, array $filters): bool
    {
        if (! empty($filters['action']) && $entry['action'] !== $filters['action']) {
            return false;
        }
        if (! empty($filters['entity']) && ($entry['entity'] ?? null) !== $filters['entity']) {
            return false;
        }
        if (! empty($filters['user_id']) && (string) ($entry['user_id'] ?? '') !== (string) $filters['user_id']) {
            return false;
        }
        if (! empty($filters['ip']) && ! str_contains((string) ($entry['ip'] ?? ''), (string) $filters['ip'])) {
            return false;
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $haystack = implode(' ', array_filter([
                $entry['entity_title'] ?? null,
                $entry['entity_label'] ?? null,
                $entry['description'] ?? null,
                $entry['user_name'] ?? null,
                $entry['path'] ?? null,
                (string) ($entry['entity_id'] ?? ''),
                json_encode($entry['changes'] ?? [], JSON_UNESCAPED_UNICODE),
            ], fn ($v) => $v !== null && $v !== ''));

            if (! Str::contains(mb_strtolower($haystack), mb_strtolower($q))) {
                return false;
            }
        }

        return true;
    }

    private function date(?string $value): ?CarbonImmutable
    {
        if (! $value) {
            return null;
        }
        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * کاربرانی که در بازهٔ فایل‌های موجود فعالیت داشته‌اند (برای dropdown فیلتر).
     *
     * @return Collection<int, array{id: mixed, name: string}>
     */
    public function knownUsers(int $maxDays = 15): Collection
    {
        $users = [];
        foreach (array_slice($this->availableDates(), 0, $maxDays) as $date) {
            foreach ($this->readLines($date) as $entry) {
                $id = $entry['user_id'];
                if ($id !== null && ! isset($users[$id])) {
                    $users[$id] = ['id' => $id, 'name' => $entry['user_name'] ?: (string) $id];
                }
            }
        }

        return collect($users)->sortBy('name')->values();
    }

    /** مجموعِ حجمِ فایل‌های لاگ (بایت) — برای نمایش در پنل. */
    public function totalSize(): int
    {
        $size = 0;
        foreach (glob($this->directory().'/activity-*.log') ?: [] as $file) {
            $size += (int) @filesize($file);
        }

        return $size;
    }
}
