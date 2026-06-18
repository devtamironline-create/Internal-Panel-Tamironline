<?php

namespace Modules\Seo\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Seo\Services\CannibalizationReport;

/**
 * گزارش هم‌نوع‌خواری کلمات کلیدی + محتوای تکراری: تداخل focus keyword و عنوان‌های تکراری.
 */
class CannibalizationController extends Controller
{
    public function index(CannibalizationReport $report)
    {
        $keywordConflicts = $report->focusKeywordConflicts();
        $duplicateTitles = $report->duplicateTitles();

        // پیشنهاد رفع به‌ازای هر گروه تداخل.
        $suggestion = 'یک صفحه را canonical اصلی کنید و بقیه را به آن canonical/noindex کنید.';

        return view('seo::cannibalization.index', compact('keywordConflicts', 'duplicateTitles', 'suggestion'));
    }
}
