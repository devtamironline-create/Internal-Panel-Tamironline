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
            // تصویرِ کاتالوگِ دستگاه برای thumbnailِ کارتِ لیست.
            'device_icon' => $this->device?->icon,
            'device_thumbnail' => \Modules\Site\Support\MediaUrl::resolve($this->device?->thumbnail),
            'scheduled_at' => $this->visit_scheduled_at?->utc()->toIso8601String(),
            'created_at' => $this->created_at?->utc()->toIso8601String(),

            // ─── مهلت تعیین وضعیت (SLA) — مرجعِ قفلِ اپ
            // assigned_at مبنای تشخیصِ «سفارشِ جدید» در اپ است: سفارشی که
            // قبلاً دیده شده ممکن است دوباره تخصیص یافته باشد، پس created_at
            // برای این کار کافی نیست.
            'assigned_at' => $this->assigned_at?->utc()->toIso8601String(),
            'status_changed_at' => $this->status_changed_at?->utc()->toIso8601String(),
            'sla_deadline_at' => \Modules\CRM\Support\SlaPolicy::deadlineFor($this->resource)?->utc()->toIso8601String(),
            'return_review_pending' => (bool) $this->return_review_pending,
            // دکمهٔ «پیش‌فاکتور» روی کارتِ لیست — هم‌مرجع با جزئیات و با
            // گیتِ ۴۲۲ سرور (OrderStatus::allowsProforma).
            'can_create_proforma' => \Modules\CRM\Models\Proforma::techEnabled()
                && (bool) $status?->allowsProforma(),
        ];
    }
}
