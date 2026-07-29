<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * خلاصهٔ لاگِ درخواست‌های کند — کدام صفحه، چقدر، و به‌خاطرِ چه کوئری‌ای.
 *
 * لاگِ خام برای چشم خوانا نیست؛ این دستور بر اساسِ مسیر گروه‌بندی می‌کند
 * و بدترین‌ها را جلو می‌آورد، چون معمولاً دو-سه مسیر کلِ مشکل‌اند.
 */
class ShowSlowRequests extends Command
{
    protected $signature = 'perf:slow-requests
                            {--top=10 : چند مسیرِ بدتر نشان داده شود}
                            {--path= : فقط مسیرهایی که شاملِ این رشته‌اند}
                            {--detail : ریزِ کندترین نمونهٔ هر مسیر}';

    protected $description = 'خلاصهٔ درخواست‌های کند از storage/logs/slow-requests-*.log';

    public function handle(): int
    {
        $entries = $this->entries();

        if (empty($entries)) {
            $this->warn('چیزی ثبت نشده.');
            $this->newLine();
            $this->line('برای فعال‌کردن، این را در .env بگذارید و config:clear بزنید:');
            $this->line('  <fg=cyan>SLOW_REQUEST_MS=800</>');
            $this->line('بعد چند دقیقه با پنل کار کنید و دوباره این دستور را بزنید.');

            return self::SUCCESS;
        }

        $filter = (string) $this->option('path');
        if ($filter !== '') {
            $entries = array_values(array_filter(
                $entries,
                fn ($e) => str_contains((string) ($e['path'] ?? ''), $filter)
            ));
        }

        $groups = [];
        foreach ($entries as $entry) {
            $key = ($entry['method'] ?? '?').' '.($entry['path'] ?? '?');
            $groups[$key][] = $entry;
        }

        // بدترین = بیشترین زمانِ بیشینه. میانگین می‌تواند یک موردِ فاجعه را
        // پشتِ ده موردِ سالم پنهان کند.
        uasort($groups, fn ($a, $b) => max(array_column($b, 'total_ms')) <=> max(array_column($a, 'total_ms')));
        $groups = array_slice($groups, 0, max(1, (int) $this->option('top')), true);

        $this->newLine();
        $this->line('<fg=cyan>── کندترین مسیرها ('.count($entries).' درخواستِ ثبت‌شده) ──</>');
        $this->table(
            ['مسیر', 'تعداد', 'بیشینه', 'میانگین', 'کوئری (میانگین)', 'زمانِ کوئری', 'زمانِ PHP'],
            array_map(function ($key, $rows) {
                $totals = array_column($rows, 'total_ms');

                return [
                    $key,
                    count($rows),
                    round(max($totals)).'ms',
                    round(array_sum($totals) / count($rows)).'ms',
                    round($this->avg($rows, 'query_count')),
                    round($this->avg($rows, 'query_ms')).'ms',
                    round($this->avg($rows, 'php_ms')).'ms',
                ];
            }, array_keys($groups), $groups)
        );

        $this->newLine();
        $this->line('  «زمانِ PHP» بزرگ‌تر از «زمانِ کوئری» یعنی مشکل در دیتابیس نیست — رندر یا حلقهٔ PHP است.');

        if ($this->option('detail')) {
            $this->detail($groups);
        } else {
            $this->line('  برای دیدنِ کوئری‌ها: <fg=cyan>php artisan perf:slow-requests --detail</>');
        }

        return self::SUCCESS;
    }

    /** @param array<string, array<int, array<string, mixed>>> $groups */
    private function detail(array $groups): void
    {
        foreach ($groups as $key => $rows) {
            usort($rows, fn ($a, $b) => ($b['total_ms'] ?? 0) <=> ($a['total_ms'] ?? 0));
            $worst = $rows[0];

            $this->newLine();
            $this->line('<fg=cyan>── '.$key.' — بدترین نمونه ('.round($worst['total_ms'] ?? 0).'ms) ──</>');

            foreach ((array) ($worst['slowest_queries'] ?? []) as $q) {
                $this->line('  • '.$q);
            }

            $repeated = (array) ($worst['most_repeated'] ?? []);
            $noisy = array_filter($repeated, fn ($count) => $count >= 5);
            if ($noisy) {
                $this->newLine();
                $this->warn('  کوئری‌های تکراری (نشانهٔ N+1):');
                foreach ($noisy as $sql => $count) {
                    $this->line('  • '.$count.' بار — '.$sql);
                }
            }
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function entries(): array
    {
        $out = [];

        foreach (glob(storage_path('logs/slow-requests*.log')) ?: [] as $file) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                // خطِ استانداردِ Monolog: «… slow request {json}»
                $start = strpos($line, '{');
                if ($start === false) {
                    continue;
                }

                $decoded = json_decode(substr($line, $start), true);
                if (is_array($decoded) && isset($decoded['total_ms'])) {
                    $out[] = $decoded;
                }
            }
        }

        return $out;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function avg(array $rows, string $key): float
    {
        $values = array_map(fn ($r) => (float) ($r[$key] ?? 0), $rows);

        return $values ? array_sum($values) / count($values) : 0.0;
    }
}
