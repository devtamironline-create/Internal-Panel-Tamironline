<?php

namespace Modules\Seo\Http\Controllers\Arm;

use Illuminate\Http\Request;
use Modules\Seo\Models\SeoChangeLog;
use Modules\Seo\Models\SeoLinkItem;
use Modules\Seo\Support\Arm\ArmEnums;

/** فضای کاری لینک‌سازی (داخلی/خارجی/PR/سایتیشن). */
class LinkController extends BaseArmController
{
    public function index(Request $request)
    {
        $project = $this->project();

        $filters = [
            'link_type' => $request->string('link_type')->toString(),
            'status' => $request->string('status')->toString(),
            'q' => trim($request->string('q')->toString()),
        ];

        $links = $project->linkItems()
            ->with('destinationPage:id,title,path')
            ->when($filters['link_type'], fn ($q, $v) => $q->where('link_type', $v))
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['q'], fn ($q, $v) => $q->where(function ($qq) use ($v) {
                $qq->where('anchor_text', 'like', "%{$v}%")
                    ->orWhere('target_url', 'like', "%{$v}%")
                    ->orWhere('domain', 'like', "%{$v}%");
            }))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'internal_planned' => $project->linkItems()->where('link_type', 'internal')->where('status', 'planned')->count(),
            'external_active' => $project->linkItems()->where('link_type', '!=', 'internal')->whereIn('status', ['outreach', 'negotiation', 'placed'])->count(),
            'live' => $project->linkItems()->where('status', 'live')->count(),
        ];

        return view('seo::arm.links.index', compact('project', 'links', 'filters', 'stats'));
    }

    public function store(Request $request)
    {
        $project = $this->project();

        $validated = $request->validate([
            'link_type' => 'required|in:'.implode(',', array_keys(ArmEnums::LINK_TYPES)),
            'source_url' => 'nullable|string|max:700',
            'target_url' => 'required|string|max:700',
            'anchor_text' => 'nullable|string|max:300',
            'domain' => 'nullable|string|max:190',
            'follow_type' => 'nullable|in:follow,nofollow,sponsored,ugc',
            'planned_at' => 'nullable|date',
        ], [], ['target_url' => 'آدرس مقصد']);

        $link = $project->linkItems()->create($validated + ['status' => 'planned']);
        SeoChangeLog::record('link-created', 'بازوی سئو', 'لینک جدید: '.$link->target_url);

        return back()->with('success', 'آیتم لینک‌سازی ثبت شد.');
    }

    public function update(Request $request, SeoLinkItem $link)
    {
        $validated = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(ArmEnums::LINK_STATUSES)),
            'contact_status' => 'nullable|string|max:30',
        ]);

        if (in_array($validated['status'], ['placed', 'live'], true) && ! $link->published_at) {
            $link->published_at = now();
        }
        $link->fill($validated)->save();

        SeoChangeLog::record('link-status', 'بازوی سئو', ($link->anchor_text ?: $link->target_url).' → '.$link->statusLabel());

        return back()->with('success', 'وضعیت لینک به‌روزرسانی شد.');
    }
}
