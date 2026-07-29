<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * «کدام صفحه کند است و چرا؟» — ثبتِ درخواست‌های کندتر از یک آستانه، همراه
 * با ریزِ کوئری‌ها.
 *
 * چرا لازم شد: وقتی گزارش می‌رسد «پنل کند شده»، حدس‌زدنِ علت از روی کد
 * اتلافِ وقت است — یک‌بار همین اتفاق افتاد و حدس (کوئری‌های چت) با دادهٔ
 * واقعی رد شد. این میان‌افزار به‌جای حدس، اندازه می‌گیرد.
 *
 * پیش‌فرض *خاموش* است: تا وقتی SLOW_REQUEST_MS در env نباشد، هیچ
 * listenerی روی DB بسته نمی‌شود و هزینه‌اش صفر است. برای عیب‌یابی
 * روشنش می‌کنید، مشکل را می‌بینید، خاموشش می‌کنید.
 */
class LogSlowRequests
{
    /** چند کوئریِ کندِ برتر در گزارش بیاید. */
    private const TOP_QUERIES = 5;

    /** طولِ SQL در گزارش — کوئریِ کامل لاگ را غیرقابل‌خواندن می‌کند. */
    private const SQL_LENGTH = 300;

    public function handle(Request $request, Closure $next): Response
    {
        // از config و نه env(): بعد از `config:cache` تابعِ env() روی
        // پروداکشن null می‌دهد و پروفایلر بی‌صدا خاموش می‌شد.
        $threshold = (int) config('logging.slow_request_ms', 0);

        if ($threshold <= 0) {
            return $next($request);
        }

        $started = microtime(true);

        // زمانِ بوتِ فریم‌ورک: از لحظهٔ ورود به index.php تا رسیدن به این
        // میان‌افزار. اگر این عدد بزرگ باشد، مشکل در منطقِ صفحه نیست —
        // بوت (config/route بدونِ کش، OPcache خاموش) یا کمبودِ CPU است.
        $bootMs = defined('LARAVEL_START') ? ($started - LARAVEL_START) * 1000 : null;

        $queries = [];

        DB::listen(function ($query) use (&$queries) {
            $queries[] = ['sql' => $query->sql, 'time' => (float) $query->time];
        });

        $response = $next($request);

        $elapsed = (microtime(true) - $started) * 1000;
        if ($elapsed < $threshold) {
            return $response;
        }

        $this->report($request, $response, $elapsed, $queries, $bootMs);

        return $response;
    }

    /**
     * @param  array<int, array{sql:string, time:float}>  $queries
     */
    private function report(
        Request $request,
        Response $response,
        float $elapsed,
        array $queries,
        ?float $bootMs
    ): void {
        $queryMs = array_sum(array_column($queries, 'time'));

        // کندترین‌ها اول — معمولاً یکی‌دو تا کلِ داستان‌اند.
        usort($queries, fn ($a, $b) => $b['time'] <=> $a['time']);
        $slowest = array_map(
            fn ($q) => round($q['time'], 1).'ms · '.mb_substr(preg_replace('/\s+/', ' ', $q['sql']), 0, self::SQL_LENGTH),
            array_slice($queries, 0, self::TOP_QUERIES)
        );

        // تکراری‌ترین الگو — علامتِ کلاسیکِ N+1.
        $shapes = [];
        foreach ($queries as $q) {
            $shape = mb_substr(preg_replace('/\s+/', ' ', $q['sql']), 0, 120);
            $shapes[$shape] = ($shapes[$shape] ?? 0) + 1;
        }
        arsort($shapes);
        $repeated = array_slice($shapes, 0, 3, true);

        try {
            Log::channel('slow')->warning('slow request', [
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
                'route' => optional($request->route())->getName(),
                'user_id' => optional($request->user())->id,
                'status' => $response->getStatusCode(),
                'boot_ms' => $bootMs === null ? null : round($bootMs, 1),
                // درایورِ session: با درایورِ file، لاراول برای خواندن
                // LOCK_SH و برای نوشتن LOCK_EX می‌گیرد و هر دو مسدودکننده‌اند،
                // پس درخواست‌های همزمانِ یک مرورگر پشتِ هم صف می‌کشند.
                'session_driver' => config('session.driver'),
                'total_ms' => round($elapsed, 1),
                'query_ms' => round($queryMs, 1),
                // اختلافِ این دو یعنی وقت صرفِ PHP/رندر شده، نه دیتابیس.
                'php_ms' => round($elapsed - $queryMs, 1),
                'query_count' => count($queries),
                'memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
                'slowest_queries' => $slowest,
                'most_repeated' => $repeated,
            ]);
        } catch (\Throwable) {
            // لاگِ عیب‌یابی هرگز نباید خودِ درخواست را بشکند.
        }
    }
}
