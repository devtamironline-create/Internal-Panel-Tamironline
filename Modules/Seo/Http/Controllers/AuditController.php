<?php

namespace Modules\Seo\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Modules\Seo\Jobs\CrawlSiteJob;
use Modules\Seo\Models\SeoAudit;
use Modules\Seo\Models\SeoAuditRun;

/**
 * داشبورد گزارش‌های مانیتورینگ سئو.
 */
class AuditController extends Controller
{
    public function index(Request $request)
    {
        $runs = SeoAuditRun::query()->latest('id')->limit(15)->get();
        $latest = $runs->first();

        // روند امتیاز سلامت (قدیمی → جدید) برای نمودار.
        $trend = $runs->sortBy('id')->map(fn ($r) => [
            'id' => $r->id,
            'avg_score' => $r->avg_score,
            'date' => optional($r->finished_at ?? $r->created_at)->format('m/d'),
        ])->values();

        $severity = (string) $request->query('severity', '');
        $audits = $latest
            ? $latest->audits()
                ->when(in_array($severity, ['good', 'notice', 'warning', 'critical'], true),
                    fn ($q) => $q->where('severity', $severity))
                ->orderByRaw("FIELD(severity,'critical','warning','notice','good')")
                ->orderBy('score')
                ->paginate(40)
                ->withQueryString()
            : null;

        return view('seo::audit.index', compact('runs', 'latest', 'trend', 'audits', 'severity'));
    }

    public function run(Request $request)
    {
        $limit = $request->integer('limit') ?: null;
        CrawlSiteJob::dispatch('manual', $limit, $request->boolean('cwv'));

        return back()->with('success', 'کرال در صف اجرا قرار گرفت. نتایج پس از پایان نمایش داده می‌شود (نیازمند queue worker).');
    }

    public function show(Request $request, SeoAuditRun $run)
    {
        $severity = (string) $request->query('severity', '');
        $audits = $run->audits()
            ->when(in_array($severity, ['good', 'notice', 'warning', 'critical'], true),
                fn ($q) => $q->where('severity', $severity))
            ->orderByRaw("FIELD(severity,'critical','warning','notice','good')")
            ->orderBy('score')
            ->paginate(40)
            ->withQueryString();

        return view('seo::audit.show', compact('run', 'audits', 'severity'));
    }

    public function export(SeoAuditRun $run, string $format): StreamedResponse
    {
        abort_unless(in_array($format, ['csv', 'json'], true), 404);

        $filename = "seo-audit-run-{$run->id}.{$format}";

        return response()->streamDownload(function () use ($run, $format) {
            $rows = $run->audits()->orderBy('id')->cursor();

            if ($format === 'json') {
                echo $run->audits()->get()->toJson(JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

                return;
            }

            $out = fopen('php://output', 'w');
            fputcsv($out, ['url', 'status_code', 'severity', 'score', 'title_length', 'description_length', 'h1_count', 'is_noindex', 'images_without_alt', 'internal_links', 'word_count']);
            foreach ($rows as $a) {
                fputcsv($out, [
                    $a->url, $a->status_code, $a->severity, $a->score, $a->title_length,
                    $a->description_length, $a->h1_count, $a->is_noindex ? 1 : 0,
                    $a->images_without_alt, $a->internal_links, $a->word_count,
                ]);
            }
            fclose($out);
        }, $filename);
    }
}
