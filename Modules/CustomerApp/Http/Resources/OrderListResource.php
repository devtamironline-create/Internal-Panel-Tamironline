<?php

namespace Modules\CustomerApp\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;
use Modules\CustomerApp\Support\Money;
use Modules\CustomerApp\Support\OrderStatusMapper;

/**
 * شکل سبک سفارش برای لیست — بدون items/objections/...
 *
 * @mixin Order
 */
class OrderListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OrderStatus|null $status */
        $status = $this->status;

        return [
            'id' => (int) $this->id,
            'tracking_code' => $this->order_code,
            'status' => $status ? OrderStatusMapper::toMobileString($status) : 'pending',
            'status_int' => $status ? OrderStatusMapper::toMobileInt($status) : 0,
            'status_label' => $status?->label() ?? 'نامشخص',
            'is_terminal' => $status ? OrderStatusMapper::isTerminal($status) : false,
            'order_type' => $this->order_type,
            'device' => $this->whenLoaded('device', fn () => [
                'id' => (int) $this->device->id,
                'name' => $this->device->name,
                'slug' => $this->device->slug,
                'icon' => $this->device->icon,
            ]),
            'brand' => $this->whenLoaded('brand', fn () => $this->brand ? [
                'id' => (int) $this->brand->id,
                'name' => $this->brand->name,
                'slug' => $this->brand->slug,
            ] : null),
            'scheduled_date' => $this->visit_scheduled_at?->format('Y-m-d'),
            'scheduled_slot' => $this->visit_scheduled_slot,
            'total_amount' => Money::rialToToman($this->total_invoice ?: $this->estimated_price ?: null),
            'created_at' => $this->created_at?->utc()->toIso8601String(),
            'updated_at' => $this->updated_at?->utc()->toIso8601String(),
        ];
    }
}
