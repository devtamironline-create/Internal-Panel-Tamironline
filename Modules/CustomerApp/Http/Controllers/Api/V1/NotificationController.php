<?php

namespace Modules\CustomerApp\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Customer;

/**
 * Notifications برای اپ موبایل — از روی Laravel notifications table استاندارد.
 *
 *   GET  /v1/customer/notifications              — لیست paginated + unread_count
 *   POST /v1/customer/notifications/{id}/read    — تک خوانده‌شده
 *   POST /v1/customer/notifications/read-all     — همه خوانده‌شده
 *
 * Customer از trait Illuminate\Notifications\Notifiable استفاده می‌کند، پس
 * $customer->notifications همان رابطه‌ی standard DatabaseNotification است.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $perPage = max(1, min((int) $request->query('per_page', 20), 50));

        $paginator = $customer->notifications()->paginate($perPage);
        $unread = $customer->unreadNotifications()->count();

        $data = collect($paginator->items())->map(function ($n) {
            return [
                'id' => (string) $n->id,
                'type' => $this->shortType($n->type),
                'title' => $n->data['title'] ?? null,
                'body' => $n->data['body'] ?? null,
                'data' => $n->data['payload'] ?? null,
                'read_at' => $n->read_at?->utc()->toIso8601String(),
                'created_at' => $n->created_at?->utc()->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'unread_count' => $unread,
            ],
        ])->header('Cache-Control', 'no-store');
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $customer = $this->customer($request);
        $n = $customer->notifications()->where('id', $id)->first();
        if (! $n) {
            abort(404, 'پیام یافت نشد.');
        }
        if (! $n->read_at) {
            $n->markAsRead();
        }

        return response()->json([
            'message' => 'علامت‌گذاری شد.',
            'data' => ['unread_count' => $customer->unreadNotifications()->count()],
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $customer->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'همه‌ی پیام‌ها خوانده‌شده شدند.',
            'data' => ['unread_count' => 0],
        ]);
    }

    private function customer(Request $request): Customer
    {
        $user = $request->user();
        if (! $user instanceof Customer) {
            abort(401, 'احراز هویت مشتری لازم است.');
        }

        return $user;
    }

    /**
     * تبدیل FQCN type به یک slug کوتاه قابل‌خواندن برای فرانت.
     * "Modules\CRM\Notifications\OrderStatusChanged" → "order_status_changed"
     */
    private function shortType(string $fqcn): string
    {
        $base = class_basename($fqcn);

        // CamelCase → snake_case
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $base) ?? $base);
    }
}
