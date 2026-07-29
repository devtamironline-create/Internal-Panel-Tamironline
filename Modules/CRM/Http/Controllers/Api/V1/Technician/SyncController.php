<?php

namespace Modules\CRM\Http\Controllers\Api\V1\Technician;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Announcement;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\TechChatMessage;
use Modules\CRM\Support\SlaPolicy;

/**
 * GET /v1/technician/me/sync
 *
 * نبضِ سبکِ همگام‌سازیِ پس‌زمینه. اپِ تکنسین پوشِ آنی ندارد و برای فهمیدنِ
 * «چیزی عوض شده؟» ناچار بود کلِ لیستِ سفارش‌ها را بگیرد — گران برای
 * باتری و اینترنتِ تکنسین و برای سرور. این اندپوینت همان سؤال را با چند
 * کوئریِ شمارشی جواب می‌دهد تا بازهٔ همگام‌سازی بتواند کوتاه‌تر شود.
 *
 * قرارداد: هیچ رابطهٔ سنگینی لود نمی‌شود. تنها ردیفِ کاملی که برمی‌گردد
 * «آخرین سفارشِ تخصیص‌یافته» است، آن هم فقط چند ستون.
 */
class SyncController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $tech = $request->user();
        $techId = (int) $tech->id;

        // ─── سفارش‌ها ────────────────────────────────────────
        // «قابلِ اقدام» = آنچه هنوز تصمیمِ تکنسین را می‌خواهد. وضعیت‌های
        // نهایی و ردشده کنار می‌روند تا عددِ بج با کارتابلِ اپ یکی باشد.
        $actionable = Order::query()
            ->forTechnician($techId)
            ->whereNotIn('status', $this->settledStatuses())
            ->count();

        $latest = Order::query()
            ->forTechnician($techId)
            ->whereNotNull('assigned_at')
            ->orderByDesc('assigned_at')
            ->with(['customer:id,first_name,last_name', 'device:id,name'])
            ->first(['id', 'order_code', 'customer_id', 'customer_name', 'device_id', 'assigned_at']);

        // ─── نزدیک‌ترین مهلت ─────────────────────────────────
        $nextDeadline = $this->nextDeadline($techId);

        return response()->json([
            'success' => true,
            'data' => [
                'server_time' => now()->utc()->toIso8601String(),
                'orders' => [
                    'actionable_count' => $actionable,
                    'latest_assigned_at' => $latest?->assigned_at?->utc()->toIso8601String(),
                    'latest_order' => $latest ? [
                        'id' => (int) $latest->id,
                        'order_code' => $latest->order_code,
                        'customer_name' => $latest->customer_name ?: ($latest->customer->display_name ?? null),
                        'device_name' => $latest->device?->name,
                    ] : null,
                ],
                'messages' => ['unread' => $this->unreadMessages($techId)],
                'announcements' => ['unread' => $this->unreadAnnouncements($techId)],
                'next_deadline_at' => $nextDeadline?->utc()->toIso8601String(),
            ],
        ]);
    }

    /**
     * نزدیک‌ترین سررسیدِ پیشِ‌رو.
     *
     * محاسبه در PHP انجام می‌شود چون مبنای هر وضعیت فرق می‌کند
     * (assigned_at / status_changed_at / visit_scheduled_at /
     * estimated_ready_at) و SlaPolicy تنها جایی است که این نگاشت را
     * می‌داند. برای اینکه گران نشود فقط روی سفارش‌های مهلت‌دارِ همین
     * تکنسین و با ستون‌های لازم اجرا می‌شود.
     */
    private function nextDeadline(int $techId): ?\Carbon\CarbonImmutable
    {
        $statuses = array_keys(SlaPolicy::DEFAULT_HOURS);
        $statuses[] = OrderStatus::Coordinated->value;

        $orders = Order::query()
            ->forTechnician($techId)
            ->whereIn('status', $statuses)
            ->limit(200)
            ->get(['id', 'status', 'assigned_at', 'status_changed_at', 'visit_scheduled_at', 'estimated_ready_at']);

        $now = \Carbon\CarbonImmutable::now();
        $best = null;

        foreach ($orders as $order) {
            $deadline = SlaPolicy::deadlineFor($order);
            // مهلتِ گذشته «نزدیک‌ترین مهلتِ بعدی» نیست؛ اپ برای آن یادآور
            // زمان‌بندی نمی‌کند.
            if ($deadline === null || $deadline->isBefore($now)) {
                continue;
            }
            if ($best === null || $deadline->isBefore($best)) {
                $best = $deadline;
            }
        }

        return $best;
    }

    private function unreadMessages(int $techId): int
    {
        return TechChatMessage::query()
            ->where('technician_id', $techId)
            ->where('sender_type', TechChatMessage::SENDER_OPERATOR)
            ->whereNull('read_at')
            ->count();
    }

    private function unreadAnnouncements(int $techId): int
    {
        return Announcement::active()
            ->whereNotIn('id', function ($q) use ($techId) {
                $q->select('announcement_id')
                    ->from('crm_announcement_acks')
                    ->where('technician_id', $techId);
            })
            ->count();
    }

    /**
     * وضعیت‌هایی که دیگر کاری از تکنسین نمی‌خواهند.
     *
     * «انتقال به تعمیرگاه» عمداً مستثناست: isFinal() آن را نهایی
     * می‌شمارد (برای قفلِ UI و هدفِ برگشتی) ولی از نظر عملیاتی هنوز
     * تصمیمِ تکنسین را طلب می‌کند و مهلت دارد — همان استثنایی که
     * SlaPolicy هم قائل است.
     *
     * @return array<int, string>
     */
    private function settledStatuses(): array
    {
        return collect(OrderStatus::cases())
            ->filter(fn (OrderStatus $s) => $s->isFinal() && $s !== OrderStatus::Transit)
            ->map(fn (OrderStatus $s) => $s->value)
            ->push(OrderStatus::Declined->value)
            ->unique()
            ->values()
            ->all();
    }
}
