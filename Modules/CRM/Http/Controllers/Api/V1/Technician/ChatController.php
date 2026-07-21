<?php

namespace Modules\CRM\Http\Controllers\Api\V1\Technician;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\TechChatMessage;

/**
 * چتِ تکنسین با اپراتورِ تخصیص‌داده‌شده — نسخهٔ API اپِ موبایل
 * (هم‌ارزِ Tech\ChatController وب). قالبِ پاسخ استاندارد { success, data|message }.
 *
 * صفحه‌بندی: بدونِ پارامتر → آخرین صفحه؛ `after_id` → پیام‌های جدیدتر (polling)؛
 * `before_id` → پیام‌های قدیمی‌تر (بارگذاریِ تدریجی). `limit` (۱..۱۰۰، پیش‌فرض ۵۰).
 */
class ChatController extends Controller
{
    /** GET /v1/technician/messages?after_id=&before_id=&limit= — لیست + خوانده‌کردنِ پیام‌های اپراتور. */
    public function index(Request $request): JsonResponse
    {
        $tech = $request->user();
        $tech->loadMissing('operators:id,first_name,last_name');

        $afterId = (int) $request->query('after_id', 0);
        $beforeId = (int) $request->query('before_id', 0);
        $limit = min(max((int) $request->query('limit', 50), 1), 100);

        $base = TechChatMessage::where('technician_id', $tech->id);

        if ($afterId > 0) {
            // polling: پیام‌های بعد از یک id، به‌ترتیبِ زمانی
            $messages = $base->where('id', '>', $afterId)->orderBy('id')->get();
            $hasMoreOlder = false;
        } elseif ($beforeId > 0) {
            // بارگذاریِ تدریجیِ قدیمی‌ترها
            $rows = $base->where('id', '<', $beforeId)->orderByDesc('id')->limit($limit + 1)->get();
            $hasMoreOlder = $rows->count() > $limit;
            $messages = $rows->take($limit)->sortBy('id')->values();
        } else {
            // آخرین صفحه
            $rows = $base->orderByDesc('id')->limit($limit + 1)->get();
            $hasMoreOlder = $rows->count() > $limit;
            $messages = $rows->take($limit)->sortBy('id')->values();
        }

        // پیام‌های اپراتور را «خوانده» ثبت کن (هم‌رفتار با وب) تا بَجِ نخوانده صفر شود.
        $this->markOperatorRead((int) $tech->id);

        return response()->json([
            'success' => true,
            'data' => [
                'operators' => $tech->operators->map(fn ($o) => [
                    'id' => $o->id,
                    'name' => trim(($o->first_name ?? '').' '.($o->last_name ?? '')),
                ])->values(),
                'messages' => $messages->map(fn (TechChatMessage $m) => $this->shape($m))->values(),
                'has_more_older' => $hasMoreOlder,
            ],
        ]);
    }

    /** POST /v1/technician/messages — ارسالِ پیام (اگر اپراتوری تخصیص نیافته → 422). */
    public function send(Request $request): JsonResponse
    {
        $tech = $request->user();

        if ($tech->operators()->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'هنوز کارشناسی برای شما تخصیص داده نشده است.',
            ], 422);
        }

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ], [
            'body.required' => 'متن پیام الزامی است.',
        ]);

        $msg = TechChatMessage::create([
            'technician_id' => $tech->id,
            'sender_type' => TechChatMessage::SENDER_TECH,
            'sender_id' => $tech->id,
            'body' => $validated['body'],
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => ['message' => $this->shape($msg)],
        ], 201);
    }

    /** GET /v1/technician/messages/unread — شمارِ پیام‌های نخواندهٔ اپراتور (بَج). */
    public function unread(Request $request): JsonResponse
    {
        $tech = $request->user();

        $count = TechChatMessage::where('technician_id', $tech->id)
            ->where('sender_type', TechChatMessage::SENDER_OPERATOR)
            ->whereNull('read_at')
            ->count();

        return response()->json(['success' => true, 'data' => ['unread' => $count]]);
    }

    /** POST /v1/technician/messages/read — علامتِ صریحِ «خوانده شد» برای همهٔ پیام‌های اپراتور. */
    public function markRead(Request $request): JsonResponse
    {
        $tech = $request->user();
        $n = $this->markOperatorRead((int) $tech->id);

        return response()->json(['success' => true, 'data' => ['marked' => $n]]);
    }

    private function markOperatorRead(int $techId): int
    {
        return TechChatMessage::where('technician_id', $techId)
            ->where('sender_type', TechChatMessage::SENDER_OPERATOR)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /** @return array<string, mixed> */
    private function shape(TechChatMessage $m): array
    {
        return [
            'id' => $m->id,
            'sender_type' => $m->sender_type,
            'body' => $m->body,
            'created_at' => $m->created_at?->utc()->toIso8601String(),
        ];
    }
}
