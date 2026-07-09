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
                // تصویرِ کاتالوگِ دستگاه (همان تصویر /v1/catalog/devices) — برای
                // نمایش روی صفحه‌ی سفارش حتی وقتی عکسِ تعمیر وجود ندارد.
                'thumbnail' => \Modules\Site\Support\MediaUrl::resolve($this->device->thumbnail ?? null),
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
                'name' => $this->technician->display_name,
                'mobile' => $this->technician->mobile,
                'rating' => $this->technician->satisfaction_score
                    ? (float) $this->technician->satisfaction_score : null,
            ] : null),

            // مالی — تومان
            'pricing' => [
                'estimated' => Money::rialToToman($this->estimated_price ?: null),
                'final' => Money::rialToToman($this->final_price ?: null),
                'deposit' => Money::rialToToman($this->deposit ?: null),
                // جمع کلِ مشتری = price_customer (تومان، بدون ÷۱۰) — همان مبنای
                // فاکتور؛ total_invoice مبلغِ ماندهٔ داخلی است نه قابل‌پرداختِ مشتری.
                'total' => ($t = (int) ($this->price_customer ?: 0)) > 0 ? $t : null,
                'currency' => 'IRT',
            ],

            // لغو
            'cancel' => $status === OrderStatus::Cancelled ? [
                'reason_id' => $this->cancel_reason_id ? (int) $this->cancel_reason_id : null,
                'reason' => $this->cancel_reason,
            ] : null,

            // پیش‌فاکتورها (برآوردِ قیمت) — فقط مواردِ ارسال‌شده به مشتری.
            // مبالغ به تومان (integer) هستند؛ token/public_url برای بازکردنِ رسید.
            'proformas' => $this->whenLoaded('proformas', fn () => $this->proformas->map(function ($pf) {
                // پیش‌گیری از N+1: relationِ order روی همین سفارش ست می‌شود.
                $pf->setRelation('order', $this->resource);

                return app(\Modules\CRM\Services\ProformaService::class)->shape($pf);
            })->values()),

            // نظر — وضعیت واقعی review
            'review' => $this->reviewPayload($status),

            // عکس‌های سفارش (عکسِ پس‌از‌تعمیرِ تکنسین و عکسِ legacy). خالی اگر
            // سفارش عکسی نداشته باشد → فرانت fallback آیکن دسته را نشان می‌دهد.
            'attachments' => \Modules\CustomerApp\Support\OrderAttachments::for($this->resource),

            'created_at' => $this->created_at?->utc()->toIso8601String(),
            'updated_at' => $this->updated_at?->utc()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewPayload(?OrderStatus $status): array
    {
        $isCompleted = $status === OrderStatus::Completed;
        $review = $this->relationLoaded('review') ? $this->review : null;
        if ($review === null && method_exists($this->resource, 'review')) {
            // fallback اگر eager load نشده بود
            $review = $this->resource->review()->first();
        }

        return [
            'required' => $isCompleted && ! $review,
            'submitted' => (bool) $review,
            'submitted_at' => $review?->created_at?->utc()->toIso8601String(),
            'rating' => $review ? (int) $review->rating : null,
            'status' => $review?->status,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveAddress(): ?array
    {
        // ⚠️ ستون `address` (متن snapshot) با نام relation تصادم دارد، پس
        // relation با نام `customerAddress` تعریف شده. $this->customerAddress
        // مدل CustomerAddress یا null برمی‌گرداند.
        $rel = $this->relationLoaded('customerAddress') ? $this->customerAddress : null;
        if ($rel) {
            return [
                'id' => (int) $rel->id,
                'label' => $rel->label,
                'full_address' => $rel->full_address,
                'state_name' => $rel->province?->name,
                'city_name' => $rel->city?->name,
                'district_name' => $rel->district?->name,
                'postal_code' => $rel->postal_code,
                'phone' => $rel->phone,
            ];
        }
        // snapshot fallback — از ستون‌های روی خود order
        $snapshotText = $this->getAttribute('address');
        if ($snapshotText || $this->province_id || $this->city_id) {
            return [
                'id' => null,
                'label' => null,
                'full_address' => $snapshotText,
                'state_name' => $this->province?->name,
                'city_name' => $this->city?->name,
                'district_name' => $this->district?->name,
                'postal_code' => $this->postal_code,
                'phone' => $this->customer_phone,
            ];
        }

        return null;
    }
}
