<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;

/**
 * فعالیت‌های زنده برای صفحه‌ی Home — از روی سفارش‌های فعال CRM.
 *
 * هیچ کش جداگانه‌ای ندارد؛ مستقیم از منبع حقیقت می‌خواند و در سطح
 * HTTP با Cache-Control کش می‌شود (CDN/Next BFF).
 */
class ActivityController extends Controller
{
    public function recent(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 10);
        $limit = max(1, min($limit, 50));

        $now = now();
        $statuses = [
            OrderStatus::Completed->value,
            OrderStatus::Open->value,
            OrderStatus::Coordinated->value,
            OrderStatus::Transit->value,
        ];

        $orders = Order::query()
            ->with(['device:id,name', 'city:id,name'])
            ->whereIn('status', $statuses)
            ->where('created_at', '>=', $now->copy()->subDays(2))
            ->latest('created_at')
            ->limit($limit)
            ->get(['id', 'device_id', 'city_id', 'status', 'created_at']);

        $data = $orders->map(function (Order $o) use ($now) {
            $statusValue = $o->status instanceof OrderStatus ? $o->status->value : (string) $o->status;
            $publicStatus = $statusValue === OrderStatus::Completed->value ? 'completed' : 'in_progress';
            $deviceLabel = optional($o->device)->name ?? 'دستگاه';

            return [
                'device_slug'  => str(\Illuminate\Support\Str::slug($deviceLabel, '-'))->limit(50, '')->value(),
                'device_label' => $deviceLabel,
                'area'         => optional($o->city)->name ?? '—',
                'status'       => $publicStatus,
                'minutes_ago'  => (int) $o->created_at->diffInMinutes($now),
            ];
        })->values();

        return response()
            ->json(['data' => $data])
            ->header('Cache-Control', 'public, max-age=60, s-maxage=60');
    }
}
