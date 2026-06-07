<?php

namespace Modules\CustomerApp\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;
use Modules\CustomerApp\Support\Money;
use Modules\CustomerApp\Support\OrderStatusMapper;

/**
 * شکل کامل سفارش برای جزئیات.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
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

            'device' => $this->whenLoaded('device', fn () => $this->device ? [
                'id' => (int) $this->device->id,
                'name' => $this->device->name,
                'slug' => $this->device->slug,
                'icon' => $this->device->icon,
            ] : null),
            'brand' => $this->whenLoaded('brand', fn () => $this->brand ? [
                'id' => (int) $this->brand->id,
                'name' => $this->brand->name,
                'slug' => $this->brand->slug,
            ] : null),

            // مشکل/توضیحات
            'problem_title' => $this->problem_title,
            'problem_description' => $this->problem_description,
            'objections' => $this->whenLoaded('objections', fn () => $this->objections->map(fn ($o) => [
                'id' => (int) $o->id,
                'name' => $o->name,
                'slug' => $o->slug,
            ])->values()),

            // زمان‌بندی
            'scheduled_date' => $this->visit_scheduled_at?->format('Y-m-d'),
            'scheduled_slot' => $this->visit_scheduled_slot,
            'scheduled_at' => $this->visit_scheduled_at?->utc()->toIso8601String(),

            // آدرس — اولویت: address (FK) > snapshot
            'address' => $this->resolveAddress(),

            // تکنسین — اگر تخصیص داده شده
            'technician' => $this->whenLoaded('technician', fn () => $this->technician ? [
                'id' => (int) $this->technician->id,
                'name' => $this->technician->display_name ?? $this->technician->bio,
                'mobile' => $this->technician->mobile,
                'rating' => $this->technician->satisfaction_score
                    ? (float) $this->technician->satisfaction_score : null,
            ] : null),

            // مالی — تومان
            'pricing' => [
                'estimated' => Money::rialToToman($this->estimated_price ?: null),
                'final' => Money::rialToToman($this->final_price ?: null),
                'deposit' => Money::rialToToman($this->deposit ?: null),
                'total' => Money::rialToToman($this->total_invoice ?: null),
                'currency' => 'IRT',
            ],

            // لغو
            'cancel' => $status === OrderStatus::Cancelled ? [
                'reason_id' => $this->cancel_reason_id ? (int) $this->cancel_reason_id : null,
                'reason' => $this->cancel_reason,
            ] : null,

            // نظر — placeholder تا Block 3 (Reviews) فعال شود
            'review' => [
                'required' => $status === OrderStatus::Completed,
                'submitted' => false,
                'submitted_at' => null,
                'rating' => null,
            ],

            'created_at' => $this->created_at?->utc()->toIso8601String(),
            'updated_at' => $this->updated_at?->utc()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveAddress(): ?array
    {
        if ($this->relationLoaded('address') && $this->address) {
            return [
                'id' => (int) $this->address->id,
                'label' => $this->address->label,
                'full_address' => $this->address->full_address,
                'state_name' => $this->address->province?->name,
                'city_name' => $this->address->city?->name,
                'postal_code' => $this->address->postal_code,
                'phone' => $this->address->phone,
            ];
        }
        // snapshot fallback
        if ($this->address || $this->province_id || $this->city_id) {
            return [
                'id' => null,
                'label' => null,
                'full_address' => $this->getAttribute('address'),
                'state_name' => $this->province?->name,
                'city_name' => $this->city?->name,
                'postal_code' => $this->postal_code,
                'phone' => $this->customer_phone,
            ];
        }

        return null;
    }
}
