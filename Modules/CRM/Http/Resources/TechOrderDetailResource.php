<?php

namespace Modules\CRM\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Order;
use Modules\CRM\Services\TransferReceiptService;

/**
 * جزئیاتِ کاملِ سفارش برای اپِ تکنسین — هم‌ترازِ صفحهٔ order_show پنل.
 *
 * @mixin Order
 */
class TechOrderDetailResource extends JsonResource
{
    /** برچسبِ اکشنِ هر وضعیت (رادیوهای تغییرِ وضعیت). */
    private const ACTION_LABELS = [
        'coordinated' => 'هماهنگ کردن سفارش',
        'repair_started' => 'شروع تعمیر',
        'open' => 'انتقال به تعمیرگاه',
        'awaiting_part' => 'در انتظار قطعه',
        'awaiting_customer_approval' => 'در انتظار تأیید مشتری',
        'suspended' => 'وضعیت نامشخص',
        'completed' => 'پایان سفارش',
        'declined' => 'رد سفارش',
        'transit' => 'فقط ایاب و ذهاب',
        'cancelled' => 'کنسل سفارش',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OrderStatus|null $status */
        $status = $this->status;
        $isFinal = $status?->isFinal() ?? false;

        return [
            'id' => (int) $this->id,
            'order_code' => $this->order_code,
            'order_type' => $this->order_type,
            'subscription' => $this->subscription,

            'status' => $status?->value,
            'status_label' => $status?->label(),
            'status_badge' => $status?->badgeClass(),
            'status_group' => $status?->group(),
            'is_final' => $isFinal,
            'is_returned' => ! is_null($this->return_type),
            'return_type' => $this->return_type ? (int) $this->return_type : null,

            'scheduled_at' => $this->visit_scheduled_at?->utc()->toIso8601String(),
            'scheduled_date' => $this->visit_scheduled_at?->format('Y-m-d'),

            // ─── مهلت تعیین وضعیت (SLA) — مرجعِ قفلِ اپ
            'assigned_at' => $this->assigned_at?->utc()->toIso8601String(),
            'status_changed_at' => $this->status_changed_at?->utc()->toIso8601String(),
            'sla_deadline_at' => \Modules\CRM\Support\SlaPolicy::deadlineFor($this->resource)?->utc()->toIso8601String(),
            'estimated_ready_at' => $this->estimated_ready_at?->format('Y-m-d'),
            'return_review_pending' => (bool) $this->return_review_pending,
            // وضعیتِ کاملِ بررسیِ برگشتی — تا اپ بنر/فرم/نتیجه را بدون
            // حدس‌زدن نشان دهد. تا وقتی pending=true بستنِ سفارش از سرور
            // بلاک است (allowed_transitions هم وضعیت‌های نهایی را ندارد).
            'return_review' => [
                'pending' => (bool) $this->return_review_pending,
                'reviewed_at' => $this->return_reviewed_at?->utc()->toIso8601String(),
                'approved' => $this->return_reviewed_at !== null ? (bool) $this->return_review_approved : null,
                'days' => $this->return_review_days !== null ? (int) $this->return_review_days : null,
            ],
            'max_estimate_date' => \Modules\CRM\Support\SlaPolicy::maxEstimateDate()->format('Y-m-d'),
            // آیا تکنسین همین حالا مجاز است زمانِ مراجعه را تنظیم/تغییر/پاک کند؟
            // (سرور enforce می‌کند — فرانت نباید حدس بزند.)
            'can_schedule' => (bool) $status?->allowsVisitScheduling(),

            'customer' => [
                'name' => $this->customer_name ?: ($this->customer->display_name ?? null),
                // روی سفارشِ نهایی، شماره‌ها ماسک می‌شوند (حریمِ خصوصی).
                'mobile' => $isFinal ? $this->mask($this->customer_mobile) : $this->customer_mobile,
                'phone' => $isFinal ? $this->mask($this->customer_phone) : $this->customer_phone,
                'contact_locked' => $isFinal,
            ],

            'address' => $this->address(),
            'device' => [
                'id' => $this->device?->id,
                'name' => $this->device?->name,
                'slug' => $this->device?->slug,
                'brand' => $this->brand?->name,
                // تصویرِ کاتالوگِ دستگاه (همان /v1/catalog/devices) — آیکن + تصویرِ بندانگشتی.
                'icon' => $this->device?->icon,
                'thumbnail' => \Modules\Site\Support\MediaUrl::resolve($this->device?->thumbnail),
            ],
            'problem' => [
                'title' => $this->problem_title,
                'description' => $this->problem_description,
                // مشکل‌های ساختاریِ انتخاب‌شدهٔ مشتری (۱ تا ۳) — علاوه بر عنوانِ متنی.
                'objections' => $this->whenLoaded('objections', fn () => $this->objections
                    ->map(fn ($o) => ['id' => $o->id, 'title' => $o->name])
                    ->values()),
            ],

            'tech_notes' => array_values(array_filter([
                ['key' => 'description_tech', 'label' => 'یادداشت تکنسین', 'value' => $this->description_tech],
                ['key' => 'description_tech1', 'label' => 'دلیل وضعیت نامشخص', 'value' => $this->description_tech1],
                ['key' => 'description_tech2', 'label' => 'لیست اقلام تحویل‌گرفته‌شده', 'value' => $this->description_tech2],
            ], fn ($n) => filled($n['value']))),

            'my_notes' => $this->myNotes($request),

            'financial' => [
                // «روش دریافت وجه» فاکتورِ فعالِ سفارش — cash|online|null.
                'collection_method' => $collection = \Modules\CRM\Models\Invoice::where('order_id', $this->id)
                    ->value('collection_method'),
                'collection_method_label' => match ($collection) {
                    'cash' => 'نقدی (دریافت در محل)',
                    'online' => 'اعتباری (پرداخت آنلاین)',
                    default => null,
                },
                // وقتی بستانکاریِ تکنسین از شرکت به سقف رسیده، «اعتباری»
                // برای فاکتورهای جدید بسته است — اپ باید گزینه را غیرفعال
                // نشان دهد؛ سرور هم انتخابِ online را با 422 رد می‌کند.
                'online_collection_allowed' => $onlineAllowed = ! ($request->user()?->isOnlineCollectionBlocked() ?? false),
                'online_collection_blocked_reason' => $onlineAllowed
                    ? null
                    : 'اعتبار کیف‌پول شما به سقف مجاز رسیده است؛ فعلاً فقط دریافت نقدی ممکن است.',
                'price_customer' => (int) ($this->price_customer ?? 0) ?: null,
                'cost_price' => (int) ($this->cost_price ?? 0) ?: null,
                'total_invoice' => (int) ($this->total_invoice ?? 0) ?: null,
                'hire' => (int) ($this->hire ?? 0) ?: null,
                'transportation' => (int) ($this->transportation ?? 0) ?: null,
                'discount' => (int) ($this->discount ?? 0) ?: null,
                'final_price' => (int) ($this->final_price ?? 0) ?: null,
                'invoice_description' => $this->invoice_descripotion,
                'pieces' => $this->pieces(),
            ],

            'device_image_url' => $this->device_img1 ? storage_url($this->device_img1) : null,

            'allowed_transitions' => $this->allowedTransitions($status),
            'status_history' => $this->statusHistory(),

            'proformas' => $this->whenLoaded('proformas', fn () => $this->proformas->map(fn ($pf) => [
                'id' => (int) $pf->id,
                'code' => $pf->proforma_code,
                'status' => $pf->status,
                'total' => (int) $pf->total,
                'public_url' => method_exists($pf, 'publicUrl') ? $pf->publicUrl() : null,
                'created_at' => $pf->created_at?->utc()->toIso8601String(),
            ])->values()),

            'transfer_receipts' => $this->whenLoaded('transferReceipts', fn () => $this->transferReceipts->map(fn ($tr) => [
                'code' => $tr->code,
                'description' => $tr->description,
                'print_url' => TransferReceiptService::publicUrl($tr),
                'created_at' => $tr->created_at?->utc()->toIso8601String(),
            ])->values()),

            // return_type داخلِ لاگ‌ها هم مثل فیلدِ اصلی int برمی‌گردد —
            // در JSON قدیمیِ WP رشته ذخیره شده و اپ نباید دو نوع ببیند.
            'return_logs' => collect(is_array($this->wp_return_logs) ? $this->wp_return_logs : [])
                ->map(function ($log) {
                    if (is_array($log) && isset($log['return_type'])) {
                        $log['return_type'] = (int) $log['return_type'];
                    }

                    return $log;
                })->values()->all(),

            'created_at' => $this->created_at?->utc()->toIso8601String(),
            'updated_at' => $this->updated_at?->utc()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function address(): ?array
    {
        $addr = $this->relationLoaded('customerAddress') ? $this->customerAddress : null;
        $text = ($addr && filled($addr->full_address)) ? $addr->full_address : $this->address;
        $hasCoords = $addr && method_exists($addr, 'hasCoordinates') && $addr->hasCoordinates();

        if (! $text && ! $this->province_id && ! $this->city_id && ! $hasCoords) {
            return null;
        }

        return [
            'full_address' => $text,
            'province' => $addr?->province?->name ?? $this->province?->name,
            'city' => $addr?->city?->name ?? $this->city?->name,
            'district' => $addr?->district?->name,
            'postal_code' => $addr?->postal_code ?: $this->postal_code,
            'latitude' => $hasCoords ? (float) $addr->latitude : null,
            'longitude' => $hasCoords ? (float) $addr->longitude : null,
            'has_coordinates' => (bool) $hasCoords,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pieces(): array
    {
        $titles = is_array($this->piece_list) ? $this->piece_list : [];
        $buys = is_array($this->buy_price_list) ? $this->buy_price_list : [];
        $sells = is_array($this->customer_price_list) ? $this->customer_price_list : [];

        $out = [];
        foreach ($titles as $i => $t) {
            $title = is_string($t) ? $t : (string) ($t['title'] ?? '');
            if ($title === '') {
                continue;
            }
            $out[] = [
                'title' => $title,
                'buy_price' => (int) ($buys[$i] ?? 0),
                'customer_price' => (int) ($sells[$i] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * یادداشت‌های همین تکنسین (author == صاحبِ توکن).
     *
     * @return array<int, array<string, mixed>>
     */
    private function myNotes(Request $request): array
    {
        $techId = (int) optional($request->user())->id;
        $notes = is_array($this->wp_notes) ? $this->wp_notes : [];

        return collect($notes)
            ->filter(fn ($n) => isset($n['author']) && (int) $n['author'] === $techId)
            ->map(fn ($n) => ['content' => $n['content'] ?? '', 'date' => $n['date'] ?? null])
            ->values()
            ->all();
    }

    /**
     * گذارهای مجاز برای تکنسین — هم‌سو با Tech\DashboardController::allowedStatusesFor.
     *
     * @return array<int, array<string, mixed>>
     */
    private function allowedTransitions(?OrderStatus $status): array
    {
        // پیش‌نویسِ تکمیل‌شده: تنها گذارِ مجاز، ثبتِ نهاییِ همان «تکمیل شده»
        // است تا فاکتور و بدهی ساخته شود (هم‌سو با allowedStatusesFor).
        if ($status === OrderStatus::Completed && $this->save_as_draft) {
            $canFinalize = CrmSetting::get('tech_panel_readonly') !== '1';

            return $canFinalize ? [[
                'value' => OrderStatus::Completed->value,
                'label' => OrderStatus::Completed->label(),
                'action_label' => 'ثبت نهایی فاکتور (خروج از پیش‌نویس)',
                'badge' => OrderStatus::Completed->badgeClass(),
            ]] : [];
        }

        if (! $status || $status->isFinal()) {
            return [];
        }

        $returnType = (int) ($this->return_type ?? 0);
        if ($this->return_review_pending) {
            // برگشتیِ در انتظارِ بررسی: بستن ممنوع — فقط گذارهای غیرنهایی
            // (هماهنگی/مراجعه) تا تکنسین در محل نتیجهٔ بررسی را ثبت کند.
            $base = array_filter(
                $status->technicianTransitions(),
                fn (OrderStatus $s) => ! $s->isFinal()
            );
        } elseif ($returnType === 1) {
            $base = [OrderStatus::Completed];
        } elseif ($returnType === 2) {
            $base = [OrderStatus::Cancelled, OrderStatus::Completed];
        } else {
            $base = $status->technicianTransitions();
            if (CrmSetting::get('tech_panel_readonly') === '1') {
                $base = array_filter($base, fn (OrderStatus $s) => ! $s->isFinal());
            }
        }

        return collect($base)
            ->filter(fn (OrderStatus $s) => $s !== $status)
            ->map(fn (OrderStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'action_label' => self::ACTION_LABELS[$s->value] ?? $s->label(),
                'badge' => $s->badgeClass(),
            ])
            ->values()
            ->all();
    }

    /**
     * تاریخچهٔ وضعیت — بدونِ نامِ تغییردهنده (طبقِ خواستِ کاربر).
     *
     * @return array<int, array<string, mixed>>
     */
    private function statusHistory(): array
    {
        if (! $this->relationLoaded('statusLogs')) {
            return [];
        }

        return $this->statusLogs->map(function ($log) {
            $to = OrderStatus::tryFrom((string) $log->to_status);

            return [
                'from_label' => $log->fromLabel(),
                'to_label' => $log->toLabel(),
                'to_status' => $log->to_status,
                'badge' => $to?->badgeClass() ?? 'bg-gray-100 text-gray-700',
                'note' => $log->note,
                'created_at' => $log->created_at?->utc()->toIso8601String(),
            ];
        })->values()->all();
    }

    private function mask(?string $number): ?string
    {
        if (! $number) {
            return null;
        }
        $clean = preg_replace('/\s+/', '', $number);
        $len = strlen($clean);
        if ($len <= 7) {
            return str_repeat('*', $len);
        }

        return substr($clean, 0, 4).str_repeat('*', $len - 8).substr($clean, -4);
    }
}
