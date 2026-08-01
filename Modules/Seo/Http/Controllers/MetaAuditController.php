<?php

namespace Modules\Seo\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Seo\Models\SeoMetaAuditRow;
use Modules\Seo\Models\SeoSetting;
use Modules\Seo\Support\MetaLength;

/**
 * صفحهٔ بازبینیِ متا — **فقط نمایش**.
 *
 * هیچ مسیری در این کنترلر `meta_title`/`meta_description` را نمی‌نویسد. تنها
 * اکشنِ نوشتنی `refresh()` است که snapshot را بازمی‌سازد؛ آن جدول را هیچ کدِ
 * زنده‌ای نمی‌خواند، پس اثرش روی سایت صفر است.
 *
 * صفحه فقط از snapshot می‌خواند: بدونِ آن، هر بارگذاری باید برای هر ردیف
 * قالب‌ها را می‌خواند و متغیرها را رندر می‌کرد — نه صفحه‌بندی‌پذیر بود نه
 * ایندکس‌پذیر.
 */
class MetaAuditController extends Controller
{
    public function index(Request $request): View
    {
        return view('seo::meta-audit.index', $this->viewData($request));
    }

    /**
     * دادهٔ ویو جدا نگه داشته می‌شود تا تست بتواند صفحه را با snapshotِ واقعی
     * رندر کند بدونِ برپاکردنِ کاربر و مجوزها — خطاهای Blade فقط سرِ رندرِ
     * واقعی خودشان را نشان می‌دهند.
     *
     * @return array<string, mixed>
     */
    public function viewData(Request $request): array
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'type' => (string) $request->query('type', ''),
            'flag' => (string) $request->query('flag', ''),
            'verdict' => (string) $request->query('verdict', ''),
        ];

        $query = SeoMetaAuditRow::query();

        if ($filters['q'] !== '') {
            $needle = '%'.$filters['q'].'%';
            $query->where(fn ($q) => $q->where('url', 'like', $needle)->orWhere('label', 'like', $needle));
        }
        if (isset(SeoMetaAuditRow::PAGE_TYPE_LABELS[$filters['type']])) {
            $query->where('page_type', $filters['type']);
        }
        if (isset(SeoMetaAuditRow::FLAG_LABELS[$filters['flag']])) {
            $query->withFlag($filters['flag']);
        }
        if (isset(SeoMetaAuditRow::VERDICT_LABELS[$filters['verdict']])) {
            $query->where('verdict', $filters['verdict']);
        }

        $rows = $query->orderBy('page_type')->orderBy('url')
            ->paginate(50)->withQueryString();

        return [
            'rows' => $rows,
            'filters' => $filters,
            'summary' => $this->summary(),
            'duplicates' => $this->duplicateGroups($rows),
            'generatedAt' => SeoMetaAuditRow::query()->max('generated_at'),
            'titleMax' => MetaLength::TITLE_MAX,
            'descriptionMax' => MetaLength::DESCRIPTION_MAX,
            'siteUrl' => SeoSetting::siteUrl(),
        ];
    }

    /** بازسازیِ snapshot — تنها نوشتنِ این ابزار. */
    public function refresh(): RedirectResponse
    {
        Artisan::call('seo:meta-audit:refresh');

        return redirect()
            ->route('seo.admin.meta-audit.index')
            ->with('success', 'بازبینی به‌روزرسانی شد.');
    }

    /**
     * شمارشِ کارت‌های بالای صفحه.
     *
     * چهار موردِ اول از ستون‌های ایندکس‌دار می‌آیند (نه اسکنِ JSON) و دو موردِ
     * تکراری از یک GROUP BY روی هشِ ایندکس‌شده.
     *
     * @return array<string, int>
     */
    private function summary(): array
    {
        $base = fn () => SeoMetaAuditRow::query();

        return [
            'total' => $base()->count(),
            'title_long' => $base()->where('live_title_len', '>', MetaLength::TITLE_MAX)->count(),
            'description_long' => $base()->where('live_description_len', '>', MetaLength::DESCRIPTION_MAX)->count(),
            'title_empty' => $base()->where('live_title_len', 0)->count(),
            'description_empty' => $base()->where('live_description_len', 0)->count(),
            'title_duplicate' => $this->duplicateCount('title_hash'),
            'description_duplicate' => $this->duplicateCount('description_hash'),
            'channels_diverge' => $base()->where('channels_diverge', true)->count(),
        ];
    }

    /** تعدادِ *صفحاتی* که در یک گروهِ تکراری‌اند (نه تعدادِ گروه‌ها). */
    private function duplicateCount(string $column): int
    {
        $sub = DB::table('seo_meta_audit_rows')
            ->select($column)
            ->whereNotNull($column)
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1');

        return (int) DB::table('seo_meta_audit_rows')
            ->whereIn($column, $sub->pluck($column))
            ->count();
    }

    /**
     * هم‌گروهی‌های هر ردیفِ همین صفحه — تا «تکراری» فقط یک برچسب نباشد و
     * معلوم شود دقیقاً با کدام صفحات تکراری است.
     *
     * فقط برای ۵۰ ردیفِ نمایش‌داده‌شده اجرا می‌شود، نه کلِ جدول.
     *
     * @return array<string, array<int, object>>
     */
    private function duplicateGroups($rows): array
    {
        $hashes = collect($rows->items())->pluck('title_hash')->filter()->unique()->values();
        if ($hashes->isEmpty()) {
            return [];
        }

        return DB::table('seo_meta_audit_rows')
            ->select('title_hash', 'url', 'label')
            ->whereIn('title_hash', $hashes)
            ->orderBy('url')
            ->get()
            ->groupBy('title_hash')
            ->filter(fn ($group) => $group->count() > 1)
            ->map(fn ($group) => $group->take(8)->all())
            ->all();
    }
}
