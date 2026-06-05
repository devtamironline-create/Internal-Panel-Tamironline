<?php

namespace Modules\Site\Http\Controllers\Admin\Forum;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Site\Models\Forum\ActivityLog;
use Modules\Site\Models\Forum\Answer;
use Modules\Site\Models\Forum\Question;

/**
 * داشبورد آماری و audit log انجمن.
 */
class DashboardController extends Controller
{
    private function checkView(): void
    {
        $u = auth()->user();
        if (! $u || (! $u->can('view-forum') && ! $u->can('manage-forum-questions') && ! $u->can('manage-site') && ! $u->can('manage-permissions'))) {
            abort(403);
        }
    }

    public function index(): View
    {
        $this->checkView();

        $stats = [
            'total' => Question::count(),
            'pending' => Question::where('status', Question::STATUS_PENDING)->count(),
            'approved' => Question::where('status', Question::STATUS_APPROVED)->count(),
            'rejected' => Question::where('status', Question::STATUS_REJECTED)->count(),
            'spam' => Question::where('status', Question::STATUS_SPAM)->count(),
            'unanswered' => Question::where('status', Question::STATUS_APPROVED)->where('resolution_status', Question::RES_UNANSWERED)->count(),
            'today' => Question::whereDate('created_at', today())->count(),
            'this_week' => Question::where('created_at', '>=', now()->subWeek())->count(),
            'answers_total' => Answer::count(),
            'answers_pending' => Answer::where('status', Answer::STATUS_PENDING)->count(),
        ];

        // ۷ روز اخیر — برای نمودار خطی
        $daily = collect(range(6, 0))->map(function ($d) {
            $date = today()->subDays($d);

            return [
                'date' => $date->format('Y-m-d'),
                'label' => \Morilog\Jalali\Jalalian::fromCarbon($date)->format('m/d'),
                'count' => Question::whereDate('created_at', $date)->count(),
            ];
        });

        // top devices/brands بر اساس تعداد سوال
        $topDevices = DB::table('site_forum_questions')
            ->join('crm_devices', 'site_forum_questions.device_id', '=', 'crm_devices.id')
            ->where('site_forum_questions.status', Question::STATUS_APPROVED)
            ->select('crm_devices.name', 'crm_devices.slug', DB::raw('COUNT(*) as cnt'))
            ->groupBy('crm_devices.id', 'crm_devices.name', 'crm_devices.slug')
            ->orderByDesc('cnt')
            ->limit(8)
            ->get();

        $topBrands = DB::table('site_forum_questions')
            ->join('crm_brands', 'site_forum_questions.brand_id', '=', 'crm_brands.id')
            ->where('site_forum_questions.status', Question::STATUS_APPROVED)
            ->select('crm_brands.name', 'crm_brands.slug', DB::raw('COUNT(*) as cnt'))
            ->groupBy('crm_brands.id', 'crm_brands.name', 'crm_brands.slug')
            ->orderByDesc('cnt')
            ->limit(8)
            ->get();

        $recentActivity = ActivityLog::with(['user:id,name', 'question:id,slug,title'])
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        $recentPending = Question::with(['device:id,name', 'brand:id,name'])
            ->where('status', Question::STATUS_PENDING)
            ->latest()
            ->limit(10)
            ->get();

        return view('site::admin.forum.dashboard', compact(
            'stats', 'daily', 'topDevices', 'topBrands', 'recentActivity', 'recentPending'
        ));
    }

    public function activity(Request $request): View
    {
        $this->checkView();

        $query = ActivityLog::with(['user:id,name', 'question:id,slug,title'])
            ->orderByDesc('created_at');

        if ($action = trim((string) $request->query('action', ''))) {
            $query->where('action', 'like', "{$action}%");
        }
        if ($userId = (int) $request->query('user_id', 0)) {
            $query->where('user_id', $userId);
        }
        if ($questionId = (int) $request->query('question_id', 0)) {
            $query->where('question_id', $questionId);
        }

        $logs = $query->paginate(50)->withQueryString();

        // یونیک کردن action ها برای فیلتر dropdown
        $actions = ActivityLog::query()->distinct()->orderBy('action')->pluck('action');

        return view('site::admin.forum.activity', compact('logs', 'actions'));
    }
}
