<?php

namespace Modules\CRM\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;

/**
 * سفارش در لیستِ اپِ تکنسین — فشرده.
 *
 * @mixin Order
 */
class TechOrderListResource extends JsonResource
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
            'order_code' => $this->order_code,
            'customer_name' => $this->customer_name ?: ($this->customer->display_name ?? null),
            'status' => $status?->value,
            'status_label' => $status?->label(),
            'status_badge' => $status?->badgeClass(),
            'status_group' => $status?->group(),
            'is_final' => $status?->isFinal() ?? false,
            'is_returned' => ! is_null($this->return_type),
            'device_name' => $this->device?->name,
            'brand_name' => $this->brand?->name,
            'scheduled_at' => $this->visit_scheduled_at?->utc()->toIso8601String(),
            'created_at' => $this->created_at?->utc()->toIso8601String(),
        ];
    }
}
