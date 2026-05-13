<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\SyncLog;

/**
 * نمایش/پاکسازی لاگ سینک — برای دیباگ فعالیت پلاگین WP و سرویس
 * push لاراولی.
 */
class SyncLogController extends Controller
{
    public function index(Request $request)
    {
        $query = SyncLog::query()->latest('id');

        if ($d = $request->string('direction')->trim()->toString()) {
            if (in_array($d, ['inbound', 'outbound'], true)) {
                $query->where('direction', $d);
            }
        }

        if ($e = $request->string('entity_type')->trim()->toString()) {
            $query->where('entity_type', $e);
        }

        if ($s = $request->string('status')->trim()->toString()) {
            if (in_array($s, ['success', 'failed', 'skipped'], true)) {
                $query->where('status', $s);
            }
        }

        if ($q = $request->string('q')->trim()->toString()) {
            $query->where(function ($w) use ($q) {
                $w->where('endpoint', 'like', "%{$q}%")
                  ->orWhere('error_message', 'like', "%{$q}%")
                  ->orWhere('wp_id', $q)
                  ->orWhere('entity_id', $q);
            });
        }

        $logs = $query->paginate(50)->withQueryString();

        $counts = [
            'total' => SyncLog::count(),
            'success' => SyncLog::where('status', 'success')->count(),
            'failed' => SyncLog::where('status', 'failed')->count(),
            'skipped' => SyncLog::where('status', 'skipped')->count(),
        ];

        return view('crm::sync-logs.index', compact('logs', 'counts'));
    }

    public function show(int $id)
    {
        $log = SyncLog::findOrFail($id);
        return view('crm::sync-logs.show', compact('log'));
    }

    public function destroy(Request $request)
    {
        $request->validate(['confirm' => 'required|in:yes']);
        SyncLog::truncate();
        return redirect()->route('crm.sync-logs.index')->with('success', 'تمام لاگ‌های سینک پاک شد.');
    }
}
