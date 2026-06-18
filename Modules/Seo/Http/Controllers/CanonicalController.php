<?php

namespace Modules\Seo\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Seo\Services\CanonicalReport;

/**
 * گزارش canonical: شمارش مشکلات canonical آخرین کرال + گروه‌های canonical تکراری.
 */
class CanonicalController extends Controller
{
    public function index(CanonicalReport $report)
    {
        $run = $report->latestRun();
        $counts = $report->issueCounts($run);
        $duplicates = $report->duplicateGroups($run);

        // برچسب فارسی هر کد برای نمایش.
        $labels = [
            'canonical_empty' => 'بدون canonical (صفحات قابل‌ایندکس)',
            'canonical_to_redirect' => 'canonical به مسیر ریدایرکت‌شونده',
            'canonical_to_noindex' => 'canonical به صفحهٔ noindex',
            'canonical_to_404' => 'canonical به صفحهٔ خطادار',
            'canonical_offsite' => 'canonical خارج از دامنه',
        ];

        return view('seo::canonical.index', compact('run', 'counts', 'duplicates', 'labels'));
    }
}
