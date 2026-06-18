<?php

namespace Modules\Seo\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Seo\Models\SeoAudit;
use Modules\Seo\Models\SeoAuditRun;
use Modules\Seo\Models\SeoMeta;
use Modules\Seo\Models\SeoNotFound;
use Modules\Seo\Models\SeoRedirect;
use Modules\Seo\Services\SeoableRegistry;

/**
 * داشبورد سئو: یک نمای کلی از سلامت سئوی سایت با کارت‌های قابل‌کلیک.
 * شمارش‌های مبتنی بر کرال از آخرین run تمام‌شده (done) خوانده می‌شوند.
 */
class DashboardController extends Controller
{
    public function __construct(private SeoableRegistry $registry)
    {
    }

    public function index()
    {
        // ── شمارش صفحات قابل‌ایندکس (مجموع رکوردهای هر نوع منهای noindex) ──
        $indexable = 0;
        foreach ($this->registry->all() as $cfg) {
            // نوع‌های مجازی (resolverدار مثل brand_device) جدولِ واقعی ندارند.
            if (! empty($cfg['resolver'])) {
                continue;
            }
            $modelClass = $cfg['model'] ?? null;
            if (! $modelClass || ! class_exists($modelClass)) {
                continue;
            }
            $total = $modelClass::query()->count();
            $noindexForType = SeoMeta::query()
                ->where('seoable_type', $modelClass)
                ->where('robots_noindex', true)
                ->count();
            $indexable += max(0, $total - $noindexForType);
        }

        // کل صفحاتی که به‌صورت دستی noindex شده‌اند.
        $noindexCount = SeoMeta::query()->where('robots_noindex', true)->count();

        // ── وضعیت ۴۰۴ و ریدایرکت‌ها ──
        $notFoundCount = SeoNotFound::query()->count();
        $notFoundHits = (int) SeoNotFound::query()->sum('hits');
        $activeRedirects = SeoRedirect::query()->active()->count();

        // ── آخرین کرال تمام‌شده (منبع شمارش‌های مبتنی بر آدیت) ──
        $latestRun = SeoAuditRun::query()
            ->where('status', 'done')
            ->latest('id')
            ->first();

        // مقادیر پیش‌فرض وقتی هنوز کرالی اجرا نشده است.
        $missingTitle = $missingDescription = $missingCanonical = 0;
        $missingJsonld = $orphanPages = 0;
        $lastAuditAt = null;

        if ($latestRun) {
            $rid = $latestRun->id;

            // عنوان خالی (null یا رشتهٔ تهی).
            $missingTitle = SeoAudit::query()->where('run_id', $rid)
                ->where(fn ($q) => $q->whereNull('title')->orWhere('title', ''))
                ->count();

            // توضیحات متا خالی.
            $missingDescription = SeoAudit::query()->where('run_id', $rid)
                ->where(fn ($q) => $q->whereNull('meta_description')->orWhere('meta_description', ''))
                ->count();

            // canonical خالی یا تهی.
            $missingCanonical = SeoAudit::query()->where('run_id', $rid)
                ->where(fn ($q) => $q->whereNull('canonical')->orWhere('canonical', ''))
                ->count();

            // بدون JSON-LD، فقط روی صفحات قابل‌ایندکس (noindex را نادیده بگیر).
            $missingJsonld = SeoAudit::query()->where('run_id', $rid)
                ->where('is_noindex', false)
                ->where('jsonld_present', false)
                ->count();

            // صفحات یتیم: بدون لینک داخلی ورودی/خروجی.
            $orphanPages = SeoAudit::query()->where('run_id', $rid)
                ->where('internal_links', 0)
                ->count();

            $lastAuditAt = $latestRun->finished_at ?? $latestRun->created_at;
        }

        // آخرین به‌روزرسانی سایت‌مپ: زمان پایان آخرین کرال یا اکنون.
        $sitemapLastMod = $lastAuditAt ?? now();

        return view('seo::dashboard.index', [
            'indexable' => $indexable,
            'noindexCount' => $noindexCount,
            'missingTitle' => $missingTitle,
            'missingDescription' => $missingDescription,
            'missingCanonical' => $missingCanonical,
            'missingJsonld' => $missingJsonld,
            'orphanPages' => $orphanPages,
            'notFoundCount' => $notFoundCount,
            'notFoundHits' => $notFoundHits,
            'activeRedirects' => $activeRedirects,
            'latestRun' => $latestRun,
            'lastAuditAt' => $lastAuditAt,
            'sitemapLastMod' => $sitemapLastMod,
        ]);
    }
}
