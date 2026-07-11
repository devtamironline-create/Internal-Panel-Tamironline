<?php

namespace Modules\Seo\Services;

use Illuminate\Support\Facades\DB;
use Modules\Seo\Models\SeoAuditRun;
use Modules\Seo\Models\SeoSetting;

/**
 * پاسِ پس از کرال: مقصدهای یکتای گرافِ لینک را چک می‌کند و «لینکِ خراب» را
 * علامت می‌زند. برای مقصدهایی که خودشان در همین کرال به‌عنوان صفحه آدیت شده‌اند،
 * وضعیت بازاستفاده می‌شود (بدونِ درخواستِ HTTP اضافه). بقیه با LinkChecker.
 */
class LinkGraphAnalyzer
{
    public function __construct(private readonly LinkChecker $checker) {}

    public function analyze(SeoAuditRun $run): void
    {
        $runId = (int) $run->id;

        $checkExternal = SeoSetting::get('check_external_links', '0') === '1';
        $maxChecks = (int) SeoSetting::get('link_check_max', '3000');

        // نقشهٔ مسیر→وضعیت از صفحاتِ آدیت‌شدهٔ همین کرال (بازاستفاده بدونِ HTTP).
        $auditMap = [];
        foreach (DB::table('seo_audits')->where('run_id', $runId)
            ->select('url', 'status_code', 'final_url', 'redirect_count')->cursor() as $a) {
            $p = $this->pathOf((string) $a->url);
            if ($p !== null && ! isset($auditMap[$p])) {
                $auditMap[$p] = [
                    'status' => (int) $a->status_code,
                    'final' => $a->final_url,
                    'redirects' => (int) $a->redirect_count,
                ];
            }
        }

        // مقصدهای یکتا (target_url) — برای هر URL فقط یک ردیف.
        $targets = DB::table('seo_links')->where('run_id', $runId)
            ->select('target_url', 'target_path', 'is_internal')
            ->distinct()->get();

        $now = now();
        $httpChecks = 0;
        $buffer = [];
        $seen = [];

        foreach ($targets as $t) {
            $url = (string) $t->target_url;
            if ($url === '') {
                continue;
            }
            $hash = sha1($url);
            if (isset($seen[$hash])) {
                continue;
            }
            $seen[$hash] = true;

            $isInternal = (bool) $t->is_internal;
            $path = $t->target_path;

            // خارجی و چکِ خارجی خاموش → ثبتِ سبک بدونِ درخواست.
            if (! $isInternal && ! $checkExternal) {
                $buffer[] = $this->row($runId, $url, $hash, $path, false, [
                    'status_code' => 0, 'final_url' => null, 'redirect_count' => 0,
                    'redirect_chain' => [], 'is_broken' => false, 'is_redirect' => false, 'error' => null,
                ], $now, checked: false);

                continue;
            }

            // بازاستفاده از آدیتِ همین کرال اگر مقصد یک صفحهٔ کرال‌شده است.
            $reused = $isInternal && $path !== null ? ($auditMap[$path] ?? null) : null;
            if ($reused !== null) {
                $status = $reused['status'];
                $res = [
                    'status_code' => $status,
                    'final_url' => $reused['final'],
                    'redirect_count' => $reused['redirects'],
                    'redirect_chain' => [],
                    'is_broken' => $status === 0 || $status >= 400,
                    'is_redirect' => $reused['redirects'] > 0,
                    'error' => null,
                ];
                $buffer[] = $this->row($runId, $url, $hash, $path, $isInternal, $res, $now, checked: true);

                continue;
            }

            // مقصدِ خارج از sitemap → چکِ HTTP (با سقف).
            if ($httpChecks >= $maxChecks) {
                $buffer[] = $this->row($runId, $url, $hash, $path, $isInternal, [
                    'status_code' => 0, 'final_url' => null, 'redirect_count' => 0,
                    'redirect_chain' => [], 'is_broken' => false, 'is_redirect' => false,
                    'error' => 'skipped: link_check_max reached',
                ], $now, checked: false);

                continue;
            }

            $res = $this->checker->check($url);
            $httpChecks++;
            $buffer[] = $this->row($runId, $url, $hash, $path, $isInternal, $res, $now, checked: true);

            if (count($buffer) >= 200) {
                $this->flush($buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            $this->flush($buffer);
        }
    }

    /**
     * @param  array<string, mixed>  $res
     * @return array<string, mixed>
     */
    private function row(int $runId, string $url, string $hash, ?string $path, bool $isInternal, array $res, $now, bool $checked): array
    {
        return [
            'run_id' => $runId,
            'url' => mb_substr($url, 0, 700),
            'url_hash' => $hash,
            'path' => $path,
            'is_internal' => $isInternal,
            'status_code' => (int) $res['status_code'],
            'final_url' => $res['final_url'] ? mb_substr((string) $res['final_url'], 0, 700) : null,
            'redirect_count' => (int) $res['redirect_count'],
            'redirect_chain' => ! empty($res['redirect_chain']) ? json_encode($res['redirect_chain'], JSON_UNESCAPED_UNICODE) : null,
            'is_broken' => (bool) $res['is_broken'],
            'is_redirect' => (bool) $res['is_redirect'],
            'error' => $res['error'] ?? null,
            'checked_at' => $checked ? $now : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function flush(array $rows): void
    {
        // upsert روی یکتاییِ (run_id, url_hash) — اجرای دوباره امن است.
        DB::table('seo_link_targets')->upsert(
            $rows,
            ['run_id', 'url_hash'],
            ['status_code', 'final_url', 'redirect_count', 'redirect_chain', 'is_broken', 'is_redirect', 'error', 'checked_at']
        );
    }

    private function pathOf(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if ($path === null || $path === false || $path === '') {
            return '/';
        }
        $path = rtrim((string) $path, '/');

        return $path === '' ? '/' : $path;
    }
}
