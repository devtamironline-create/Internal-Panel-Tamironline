<?php

namespace Modules\CRM\Http\Controllers\Tech;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Announcement;
use Morilog\Jalali\Jalalian;

/**
 * اعلانات در پنل تکنسین:
 * - لیست اعلان‌های فعال با وضعیت خوانده/نخوانده
 * - endpoint پاپ‌آپ: اعلان‌های تأییدنشده (polling از layout)
 * - ثبت تأیید «متوجه شدم»
 */
class AnnouncementController extends Controller
{
    public function index()
    {
        $tech = Auth::guard('tech')->user();

        $announcements = Announcement::active()->latest()->get();

        // announcement_id => acknowledged_at برای تکنسین جاری
        $ackedAt = DB::table('crm_announcement_acks')
            ->where('technician_id', $tech->id)
            ->pluck('acknowledged_at', 'announcement_id');

        return view('crm::tech-panel.announcements', compact('announcements', 'ackedAt'));
    }

    /** اعلان‌های فعالِ هنوز تأییدنشده — برای پاپ‌آپ (JSON). */
    public function unacked()
    {
        $tech = Auth::guard('tech')->user();

        $items = Announcement::active()
            ->whereNotIn('id', function ($q) use ($tech) {
                $q->select('announcement_id')
                    ->from('crm_announcement_acks')
                    ->where('technician_id', $tech->id);
            })
            ->orderBy('created_at') // قدیمی‌ترین اول تا ترتیب اعلان‌ها حفظ شود
            ->limit(10)
            ->get(['id', 'title', 'body', 'created_at']);

        return response()->json([
            'count' => $items->count(),
            'items' => $items->map(fn (Announcement $a) => [
                'id'    => $a->id,
                'title' => $a->title,
                'body'  => $a->body,
                'date'  => Jalalian::fromDateTime($a->created_at)->format('Y/m/d H:i'),
            ])->values(),
        ]);
    }

    /** ثبت «متوجه شدم» تکنسین برای یک اعلان. */
    public function ack(Announcement $announcement)
    {
        $tech = Auth::guard('tech')->user();

        abort_unless($announcement->is_active, 404);

        DB::table('crm_announcement_acks')->insertOrIgnore([
            'announcement_id' => $announcement->id,
            'technician_id'   => $tech->id,
            'acknowledged_at' => now(),
        ]);

        if (request()->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'اعلان تأیید شد.');
    }
}
