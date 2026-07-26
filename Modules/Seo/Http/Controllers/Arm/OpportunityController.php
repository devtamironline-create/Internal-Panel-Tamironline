<?php

namespace Modules\Seo\Http\Controllers\Arm;

use Illuminate\Http\Request;
use Modules\Seo\Models\SeoChangeLog;
use Modules\Seo\Models\SeoOpportunity;
use Modules\Seo\Support\Arm\ArmEnums;

/** فرصت‌های رشد — لیست + تغییر وضعیت. امتیازها از سرویس محاسبه شده‌اند. */
class OpportunityController extends BaseArmController
{
    public function index(Request $request)
    {
        $project = $this->project();

        $filters = [
            'type' => $request->string('type')->toString(),
            'status' => $request->string('status')->toString() ?: '',
            'quadrant' => $request->string('quadrant')->toString(),
            'q' => trim($request->string('q')->toString()),
        ];

        $opportunities = $project->opportunities()
            ->with(['page:id,title,path,url', 'keyword:id,keyword'])
            ->when($filters['type'], fn ($q, $v) => $q->where('type', $v))
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['q'], fn ($q, $v) => $q->where('title', 'like', "%{$v}%"))
            ->orderByDesc('opportunity_score')
            ->paginate(25)
            ->withQueryString();

        // فیلتر ربع بعد از واکشی (محاسبهٔ ربع مدل‌محور است، نه ستون DB)
        if ($filters['quadrant']) {
            $filtered = $opportunities->getCollection()
                ->filter(fn ($o) => $o->quadrant() === $filters['quadrant'])->values();
            $opportunities->setCollection($filtered);
        }

        return view('seo::arm.opportunities.index', compact('project', 'opportunities', 'filters'));
    }

    public function updateStatus(Request $request, SeoOpportunity $opportunity)
    {
        $validated = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(ArmEnums::OPPORTUNITY_STATUSES)),
        ]);

        $opportunity->update($validated);
        SeoChangeLog::record('opportunity-status', 'بازوی سئو', "«{$opportunity->title}» → ".$opportunity->statusLabel());

        return back()->with('success', 'وضعیت فرصت به‌روزرسانی شد.');
    }
}
