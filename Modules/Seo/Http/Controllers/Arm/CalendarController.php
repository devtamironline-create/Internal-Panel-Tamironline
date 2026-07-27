<?php

namespace Modules\Seo\Http\Controllers\Arm;

use Illuminate\Http\Request;
use Modules\Seo\Models\SeoChangeLog;
use Modules\Seo\Models\SeoContentItem;
use Modules\Seo\Support\Arm\ArmEnums;
use Morilog\Jalali\Jalalian;

/**
 * تقویم محتوا — نماهای ماهانه (جلالی) / هفتگی / لیست / کانبان.
 * drag&drop عمداً نیست (استک فعلی Blade+Alpine است)؛ به‌جای آن اکشن صریح
 * «تغییر زمان‌بندی» وجود دارد. تاریخ‌ها در DB میلادی‌اند و در نمایش جلالی.
 */
class CalendarController extends BaseArmController
{
    public function index(Request $request)
    {
        $project = $this->project();
        $view = in_array($request->string('view')->toString(), ['month', 'week', 'list', 'kanban'], true)
            ? $request->string('view')->toString() : 'month';

        $filters = [
            'status' => $request->string('status')->toString(),
            'content_type' => $request->string('content_type')->toString(),
            'cluster' => $request->string('cluster')->toString(),
            'priority' => $request->string('priority')->toString(),
            'owner' => $request->string('owner')->toString(),
            'q' => trim($request->string('q')->toString()),
        ];

        // ماهِ جلالیِ انتخابی (پیش‌فرض: ماه جاری) — پارامتر: jy/jm
        $jy = (int) ($request->input('jy') ?: Jalalian::now()->getYear());
        $jm = (int) ($request->input('jm') ?: Jalalian::now()->getMonth());
        $jm = max(1, min(12, $jm));
        $monthStart = (new Jalalian($jy, $jm, 1))->toCarbon()->startOfDay();
        $monthDays = (new Jalalian($jy, $jm, 1))->getMonthDays();
        $monthEnd = (new Jalalian($jy, $jm, $monthDays))->toCarbon()->endOfDay();

        $base = $project->contentItems()
            ->with(['author:id,first_name,last_name', 'reviewer:id,first_name,last_name', 'page:id,title,path,url'])
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['content_type'], fn ($q, $v) => $q->where('content_type', $v))
            ->when($filters['cluster'], fn ($q, $v) => $q->where('cluster', $v))
            ->when($filters['priority'], fn ($q, $v) => $q->where('priority', $v))
            ->when($filters['owner'], fn ($q, $v) => $q->where('author_id', (int) $v))
            ->when($filters['q'], fn ($q, $v) => $q->where(function ($qq) use ($v) {
                $qq->where('title', 'like', "%{$v}%")->orWhere('primary_keyword', 'like', "%{$v}%");
            }));

        $items = $view === 'list'
            ? $base->clone()->orderBy('planned_at')->paginate(25)->withQueryString()
            : $base->clone()
                ->when($view === 'month', fn ($q) => $q->whereBetween('planned_at', [$monthStart, $monthEnd]))
                ->when($view === 'week', fn ($q) => $q->whereBetween('planned_at', [now()->startOfWeek(\Carbon\CarbonInterface::SATURDAY), now()->endOfWeek(\Carbon\CarbonInterface::FRIDAY)]))
                ->orderBy('planned_at')
                ->get();

        $clusters = $project->contentItems()->whereNotNull('cluster')->distinct()->pluck('cluster');
        $owners = \App\Models\User::whereIn('id', $project->contentItems()->whereNotNull('author_id')->distinct()->pluck('author_id'))->get(['id', 'first_name', 'last_name']);

        return view('seo::arm.calendar.index', compact('project', 'view', 'filters', 'items', 'jy', 'jm', 'monthStart', 'monthDays', 'clusters', 'owners'));
    }

    public function updateStatus(Request $request, SeoContentItem $item)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:'.implode(',', array_keys(ArmEnums::CONTENT_STATUSES)),
        ]);

        $item->transitionStatusTo($validated['status']);
        if ($validated['status'] === 'published' && ! $item->published_at) {
            $item->published_at = now();
        }
        $item->save();

        SeoChangeLog::record('content-status', 'بازوی سئو', "«{$item->title}» → ".$item->statusLabel());

        return back()->with('success', 'وضعیت محتوا به‌روزرسانی شد.');
    }

    /** تغییر زمان‌بندی صریح (به‌جای drag&drop). */
    public function reschedule(Request $request, SeoContentItem $item)
    {
        $validated = $request->validate([
            'planned_at' => 'required|date',
            'due_at' => 'nullable|date|after_or_equal:planned_at',
        ], [], ['planned_at' => 'تاریخ برنامه', 'due_at' => 'مهلت']);

        $item->update($validated);
        SeoChangeLog::record('content-reschedule', 'بازوی سئو', "«{$item->title}» → ".$item->planned_at?->format('Y-m-d'));

        return back()->with('success', 'زمان‌بندی محتوا تغییر کرد.');
    }
}
