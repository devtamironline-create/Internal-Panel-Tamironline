<?php

namespace Modules\Seo\Http\Controllers\Arm;

use Illuminate\Http\Request;
use Modules\Seo\Models\SeoChangeLog;
use Modules\Seo\Models\SeoKeyword;
use Modules\Seo\Models\SeoPage;
use Modules\Seo\Support\Arm\ArmEnums;

/** صفحات و کلمات — نگاشت صفحه↔کلمه + کنترل هم‌نوع‌خواری primary. */
class PagesController extends BaseArmController
{
    public function index(Request $request)
    {
        $project = $this->project();

        $filters = [
            'page_type' => $request->string('page_type')->toString(),
            'priority' => $request->string('priority')->toString(),
            'workflow_status' => $request->string('workflow_status')->toString(),
            'cluster' => $request->string('cluster')->toString(),
            'q' => trim($request->string('q')->toString()),
            'sort' => $request->string('sort')->toString() ?: '-impressions',
        ];

        $sortable = ['impressions', 'clicks', 'ctr', 'current_position', 'business_value', 'priority'];
        $sort = ltrim($filters['sort'], '-');
        $dir = str_starts_with($filters['sort'], '-') ? 'desc' : 'asc';
        if (! in_array($sort, $sortable, true)) {
            [$sort, $dir] = ['impressions', 'desc'];
        }

        $pages = $project->pages()
            ->withCount('keywords')
            ->when($filters['page_type'], fn ($q, $v) => $q->where('page_type', $v))
            ->when($filters['priority'], fn ($q, $v) => $q->where('priority', $v))
            ->when($filters['workflow_status'], fn ($q, $v) => $q->where('workflow_status', $v))
            ->when($filters['cluster'], fn ($q, $v) => $q->where('cluster', $v))
            ->when($filters['q'], fn ($q, $v) => $q->where(function ($qq) use ($v) {
                $qq->where('title', 'like', "%{$v}%")
                    ->orWhere('path', 'like', "%{$v}%")
                    ->orWhere('primary_keyword', 'like', "%{$v}%");
            }))
            ->orderBy($sort, $dir)
            ->paginate(25)
            ->withQueryString();

        $clusters = $project->pages()->whereNotNull('cluster')->distinct()->pluck('cluster');
        $keywords = $project->keywords()
            ->with('page:id,title,path')
            ->orderByDesc('opportunity_score')
            ->limit(200)
            ->get();

        return view('seo::arm.pages.index', compact('project', 'pages', 'filters', 'clusters', 'keywords'));
    }

    public function update(Request $request, SeoPage $page)
    {
        $validated = $request->validate([
            'priority' => 'required|in:'.implode(',', array_keys(ArmEnums::PRIORITIES)),
            'workflow_status' => 'required|in:'.implode(',', array_keys(ArmEnums::PAGE_WORKFLOW)),
            'primary_keyword' => 'nullable|string|max:190',
            'target_position' => 'nullable|numeric|min:1|max:100',
            'url' => 'nullable|string|max:700',
            'next_review_at' => 'nullable|date',
        ]);

        // کنترل هم‌نوع‌خواری: کلمهٔ primary نباید هدفِ فعالِ صفحهٔ دیگری باشد.
        if (filled($validated['primary_keyword'] ?? null)) {
            $normalized = SeoKeyword::normalize($validated['primary_keyword']);
            $conflict = SeoKeyword::conflictingPrimary($page->seo_project_id, $normalized);
            if ($conflict && (int) $conflict->seo_page_id !== (int) $page->id) {
                return back()->withErrors([
                    'primary_keyword' => 'این کلمه هم‌اکنون هدفِ اصلی صفحهٔ «'.($conflict->page?->title ?? '#'.$conflict->seo_page_id).'» است — ابتدا تکلیف هم‌نوع‌خواری را روشن کنید.',
                ])->withInput();
            }
        }

        $page->update($validated);
        SeoChangeLog::record('page-updated', 'بازوی سئو', "صفحهٔ «{$page->title}» به‌روزرسانی شد.");

        return back()->with('success', 'صفحه به‌روزرسانی شد.');
    }
}
