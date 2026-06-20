<?php

namespace Modules\CustomerApp\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;
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
            'device' => $this->whenLoaded('device', fn () => $this->device ? [
                'id' => (int) $this->device->id,
                'name' => $this->device->name,
                'slug' => $this->device->slug,
                'icon' => $this->device->icon,
                // تصویرِ کاتالوگِ دستگاه (همان تصویر /v1/catalog/devices) — برای
                // نمایش روی کارتِ سفارش حتی وقتی عکسِ تعمیر وجود ندارد.
                'thumbnail' => \Modules\Site\Support\MediaUrl::resolve($this->device->thumbnail ?? null),
            ] : null),
            'brand' => $this->whenLoaded('brand', fn () => $this->brand ? [
                'id' => (int) $this->brand->id,
                'name' => $this->brand->name,
                'slug' => $this->brand->slug,
            ] : null),
            'scheduled_date' => $this->visit_scheduled_at?->format('Y-m-d'),
            'scheduled_slot' => $this->visit_scheduled_slot,
            // عکس‌های سفارش (برای thumbnailِ کارتِ لیست — فرانت می‌تواند اولین
            // مورد را به‌عنوان thumbnail استفاده کند). خالی = بدون عکس.
            'attachments' => \Modules\CustomerApp\Support\OrderAttachments::for($this->resource),
            // مبلغِ نهاییِ مشتری = price_customer (جمع کل) — همان مبنای فاکتور و
            // همان عددی که مشتری می‌پردازد. واحدِ DB «تومان» است و نباید ÷۱۰
            // شود؛ total_invoice (= price_customer − هزینه) فقط ماندهٔ داخلی است.
            'total_amount' => $this->customerTotalToman(),
            'created_at' => $this->created_at?->utc()->toIso8601String(),
            'updated_at' => $this->updated_at?->utc()->toIso8601String(),
        ];
    }

    /**
     * جمع کلِ قابل‌پرداختِ مشتری به تومان (بدون تبدیلِ واحد). صفر → null.
     */
    private function customerTotalToman(): ?int
    {
        $toman = (int) ($this->price_customer ?: $this->estimated_price ?: 0);

        return $toman > 0 ? $toman : null;
    }
}
