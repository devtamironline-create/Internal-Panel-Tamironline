<?php

namespace Modules\CRM\Http\Controllers\Api\V1\Technician;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Announcement;

/**
 * اعلاناتِ تکنسین — نسخهٔ API اپِ موبایل (هم‌ارزِ Tech\AnnouncementController وب).
 * پاپ‌آپِ تأییدنشده‌ها + ثبتِ «متوجه شدم» (idempotent).
 */
class AnnouncementController extends Controller
{
    /** GET /v1/technician/announcements — همهٔ اعلان‌های فعال + وضعیتِ تأییدِ همین تکنسین. */
    public function index(Request $request): JsonResponse
    {
        $tech = $request->user();

        $announcements = Announcement::active()->latest()->get(['id', 'title', 'body', 'created_at']);
        $ackedAt = DB::table('crm_announcement_acks')
            ->where('technician_id', $tech->id)
            ->pluck('acknowledged_at', 'announcement_id');

        return response()->json([
            'success' => true,
            'data' => $announcements->map(fn (Announcement $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'created_at' => $a->created_at?->utc()->toIso8601String(),
                'acknowledged' => $ackedAt->has($a->id),
                'acknowledged_at' => $ackedAt->get($a->id)
                    ? \Illuminate\Support\Carbon::parse($ackedAt->get($a->id))->utc()->toIso8601String()
                    : null,
            ])->values(),
        ]);
    }

    /** GET /v1/technician/announcements/unacked — تأییدنشده‌ها (قدیمی‌ترین اول، حداکثر ۱۰) برای پاپ‌آپ. */
    public function unacked(Request $request): JsonResponse
    {
        $tech = $request->user();

        $items = Announcement::active()
            ->whereNotIn('id', function ($q) use ($tech) {
                $q->select('announcement_id')
                    ->from('crm_announcement_acks')
                    ->where('technician_id', $tech->id);
            })
            ->orderBy('created_at')
            ->limit(10)
            ->get(['id', 'title', 'body', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => [
                'count' => $items->count(),
                'items' => $items->map(fn (Announcement $a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'body' => $a->body,
                    'created_at' => $a->created_at?->utc()->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    /** POST /v1/technician/announcements/{id}/ack — ثبتِ «متوجه شدم» (idempotent). */
    public function ack(Request $request, int $id): JsonResponse
    {
        $tech = $request->user();

        $announcement = Announcement::findOrFail($id);
        abort_unless($announcement->is_active, 404);

        DB::table('crm_announcement_acks')->insertOrIgnore([
            'announcement_id' => $announcement->id,
            'technician_id' => $tech->id,
            'acknowledged_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'ثبت شد.']);
    }
}
