<?php

namespace Modules\CRM\Http\Controllers\Api\V1\Technician;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Livewire\OrderWizard;
use Modules\CRM\Models\Order;

/**
 * داشبورد/تقویمِ کاریِ اپِ تکنسین — ۷ روزِ پیشِ‌رو، هر روز تفکیک‌شده به بازه‌های
 * ساعتی. هم‌ترازِ Tech\DashboardController::index.
 */
class DashboardController extends Controller
{
    /** GET /v1/technician/dashboard — تقویمِ ۷ روزه + خلاصه. */
    public function calendar(Request $request): JsonResponse
    {
        $tech = $request->user();

        $start = now()->startOfDay();
        $end = $start->copy()->addDays(7)->endOfDay();
        $active = [
            OrderStatus::New->value, OrderStatus::Coordinated->value,
            OrderStatus::Open->value, OrderStatus::Suspended->value,
        ];

        $scheduled = Order::query()->forTechnician($tech->id)
            ->whereIn('status', $active)
            ->whereBetween('visit_scheduled_at', [$start, $end])
            ->orderBy('visit_scheduled_at')
            ->get(['id', 'order_code', 'customer_name', 'customer_mobile', 'visit_scheduled_at', 'status', 'created_at']);

        $unscheduled = Order::query()->forTechnician($tech->id)
            ->whereNull('visit_scheduled_at')->whereIn('status', $active)
            ->latest('created_at')
            ->get(['id', 'order_code', 'customer_name', 'customer_mobile', 'visit_scheduled_at', 'status', 'created_at']);

        $todayKey = $start->toDateString();
        $scheduledByDay = $scheduled->groupBy(fn (Order $o) => $o->visit_scheduled_at?->toDateString());
        $unscheduledByDay = $unscheduled->groupBy(function (Order $o) use ($start, $end, $todayKey) {
            $c = $o->created_at?->copy() ?? now();

            return ($c->lt($start) || $c->gt($end)) ? $todayKey : $c->toDateString();
        });

        $slots = OrderWizard::VISIT_SLOTS;
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $start->copy()->addDays($i);
            $dayOrders = $scheduledByDay->get($d->toDateString(), collect());
            $dayUnscheduled = $unscheduledByDay->get($d->toDateString(), collect());

            $buckets = [1 => collect(), 2 => collect(), 3 => collect(), 4 => collect(), 'other' => collect()];
            foreach ($dayOrders as $o) {
                $h = (int) $o->visit_scheduled_at?->format('H');
                $key = match (true) {
                    $h >= 9 && $h < 12 => 1,
                    $h >= 12 && $h < 15 => 2,
                    $h >= 15 && $h < 18 => 3,
                    $h >= 18 && $h < 21 => 4,
                    default => 'other',
                };
                $buckets[$key]->push($o);
            }

            $days[] = [
                'date' => $d->toDateString(),
                'count' => $dayOrders->count() + $dayUnscheduled->count(),
                'slots' => collect([1, 2, 3, 4])->map(fn ($k) => [
                    'key' => $k,
                    'label' => $slots[$k]['label'],
                    'orders' => $this->shape($buckets[$k]),
                ])->all(),
                'off_slot' => $this->shape($buckets['other']),
                'unscheduled' => $this->shape($dayUnscheduled),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'days' => $days,
                'invoice_debt' => (int) ($tech->invoice_debt ?? 0),
            ],
        ]);
    }

    private function shape($orders): array
    {
        return $orders->map(fn (Order $o) => [
            'id' => (int) $o->id,
            'order_code' => $o->order_code,
            'customer_name' => $o->customer_name,
            'status' => $o->status?->value,
            'status_label' => $o->status?->label(),
            'scheduled_at' => $o->visit_scheduled_at?->utc()->toIso8601String(),
        ])->values()->all();
    }
}
