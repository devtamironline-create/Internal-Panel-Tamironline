<?php

namespace Modules\CRM\Http\Controllers\Api\V1\Technician;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Enums\SmsTrigger;
use Modules\CRM\Http\Resources\TechOrderDetailResource;
use Modules\CRM\Livewire\OrderWizard;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\OrderStatusLog;
use Modules\CRM\Services\InvoiceService;
use Modules\CRM\Services\OrderSmsNotifier;
use Modules\CRM\Services\TransferReceiptService;

/**
 * اکشن‌های سفارشِ اپِ تکنسین — تغییرِ وضعیت (+بلاکِ فاکتور)، هماهنگیِ زمانِ
 * مراجعه، یادداشت. منطق هم‌ترازِ Tech\DashboardController است، خروجی JSON.
 */
class OrderActionController extends Controller
{
    public function __construct(
        private OrderSmsNotifier $smsNotifier,
        private InvoiceService $invoiceService,
    ) {}

    /** POST /v1/technician/orders/{id}/status */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $tech = $request->user();
        $order = Order::query()->whereKey($id)->firstOrFail();
        $this->authorizeOwnership($order, $tech);

        $request->merge(['description' => trim((string) $request->input('description', ''))]);

        // توضیح فقط برای این وضعیت‌ها الزامی است (Open اختیاری — رسیدِ انتقال).
        $needsDesc = in_array((string) $request->input('status'), [
            OrderStatus::Coordinated->value, OrderStatus::Suspended->value,
            OrderStatus::Declined->value, OrderStatus::Transit->value,
        ], true);

        $validated = $request->validate([
            'status' => 'required|string',
            'description' => $needsDesc ? 'required|string|min:15|max:2000' : 'nullable|string|max:2000',
            'price_customer' => 'nullable|integer|min:0',
            'hire' => 'nullable|integer|min:0',
            'transportation' => 'nullable|integer|min:0',
            'discount' => 'nullable|integer|min:0',
            'pieces' => 'nullable|array',
            'pieces.*.title' => 'nullable|string|max:255',
            'pieces.*.buy_price' => 'nullable|integer|min:0',
            'pieces.*.customer_price' => 'nullable|integer|min:0',
            'invoice_descripotion' => 'nullable|string|max:2000',
            'save_as_draft' => 'nullable|boolean',
            'device_img1' => 'nullable|image|max:10240',
        ], [
            'description.required' => 'برای ثبت تغییر این وضعیت، توضیحات الزامی است.',
            'description.min' => 'توضیحات باید حداقل ۱۵ کاراکتر باشد.',
        ]);

        $newStatus = OrderStatus::tryFrom($validated['status']);
        if (! $newStatus) {
            throw ValidationException::withMessages(['status' => 'وضعیت نامعتبر است.']);
        }
        if (! in_array($newStatus, $this->allowedStatusesFor($order), true)) {
            throw ValidationException::withMessages(['status' => 'تغییر به این وضعیت در شرایط فعلی مجاز نیست.']);
        }

        $description = trim($validated['description'] ?? '');
        $updates = ['status' => $newStatus->value];
        if ($description !== '') {
            $updates += match ($newStatus) {
                OrderStatus::Coordinated => ['description_tech' => $description],
                OrderStatus::Suspended => ['description_tech1' => $description],
                OrderStatus::Open => ['description_tech2' => $description],
                OrderStatus::Declined => ['cancel_reason' => $description],
                OrderStatus::Transit => ['return_description' => $description],
                default => [],
            };
        }

        // ─── بلاکِ فاکتور هنگام Completed (هم‌ارز پنل) ───
        if ($newStatus === OrderStatus::Completed) {
            $updates = $this->applyCompletionBlock($request, $order, $validated, $updates);
        }

        $order->refresh();
        $previous = $order->status?->value ?? '';
        $order->update($updates);

        OrderStatusLog::create([
            'order_id' => $order->id,
            'from_status' => $previous,
            'to_status' => $newStatus->value,
            'note' => $description !== '' ? $description : null,
            'changed_by' => $tech->user_id,
            'created_at' => now(),
        ]);

        if ($newStatus === OrderStatus::Completed && empty($updates['save_as_draft'])) {
            $this->invoiceService->generateForOrder($order->refresh(), $tech->user_id, true);
        }

        // SMS خودکارِ وضعیت.
        if ($trigger = SmsTrigger::fromOrderStatus($newStatus)) {
            $skip = $newStatus === OrderStatus::Completed && ! is_null($order->return_type);
            if (! $skip) {
                try {
                    $this->smsNotifier->notify($order->refresh(), $trigger, $tech->user_id);
                } catch (\Throwable $e) {
                }
            }
        }

        // رسیدِ انتقالِ خودکار هنگامِ Open (اگر فعال).
        if ($newStatus === OrderStatus::Open && TransferReceiptService::enabled()) {
            try {
                app(TransferReceiptService::class)
                    ->createAndNotify($order->refresh(), $description !== '' ? $description : null, $tech->user_id, $tech->id);
            } catch (\Throwable $e) {
            }
        }

        $order->load([
            'customer', 'brand', 'device', 'province', 'city',
            'customerAddress.province', 'customerAddress.city', 'customerAddress.district',
            'items', 'statusLogs', 'transferReceipts',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'وضعیت سفارش به «'.$newStatus->label().'» تغییر کرد.',
            'data' => new TechOrderDetailResource($order),
        ]);
    }

    /** POST /v1/technician/orders/{id}/schedule-visit */
    public function scheduleVisit(Request $request, int $id): JsonResponse
    {
        $tech = $request->user();
        $order = Order::query()->whereKey($id)->firstOrFail();
        $this->authorizeOwnership($order, $tech);

        if ($order->status->isFinal()) {
            throw ValidationException::withMessages(['status' => 'تنظیم زمان مراجعه روی سفارش‌های نهایی مجاز نیست.']);
        }

        // پاک‌کردن
        if ($request->boolean('clear')) {
            $order->update(['visit_scheduled_at' => null]);
            OrderStatusLog::create([
                'order_id' => $order->id, 'from_status' => $order->status->value,
                'to_status' => $order->status->value, 'note' => 'پاک کردن زمان مراجعه',
                'changed_by' => $tech->user_id, 'created_at' => now(),
            ]);

            return response()->json(['success' => true, 'message' => 'زمان مراجعه پاک شد.']);
        }

        $validated = $request->validate([
            'visit_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'visit_slot' => ['required', 'integer', 'in:1,2,3,4'],
        ]);

        $slot = OrderWizard::VISIT_SLOTS[$validated['visit_slot']];
        $datetime = $validated['visit_date'].' '.$slot['start'];

        $order->refresh();
        $previous = $order->status;
        $autoCoordinated = $previous === OrderStatus::New;

        $order->update(array_filter([
            'visit_scheduled_at' => $datetime,
            'status' => $autoCoordinated ? OrderStatus::Coordinated->value : null,
        ]));

        $jalali = \Morilog\Jalali\Jalalian::fromDateTime($datetime)->format('Y/m/d');
        OrderStatusLog::create([
            'order_id' => $order->id, 'from_status' => $previous->value,
            'to_status' => ($autoCoordinated ? OrderStatus::Coordinated : $previous)->value,
            'note' => ($autoCoordinated ? 'هماهنگی با مشتری: ' : 'به‌روزرسانی زمان مراجعه: ').$jalali.' — '.$slot['label'],
            'changed_by' => $tech->user_id, 'created_at' => now(),
        ]);

        if ($autoCoordinated && ($trigger = SmsTrigger::fromOrderStatus(OrderStatus::Coordinated))) {
            try {
                $this->smsNotifier->notify($order->refresh(), $trigger, $tech->user_id);
            } catch (\Throwable $e) {
            }
        }

        return response()->json([
            'success' => true,
            'message' => $autoCoordinated ? 'زمان مراجعه ثبت و سفارش «هماهنگ شده» شد.' : 'زمان مراجعه به‌روزرسانی شد.',
        ]);
    }

    /** POST /v1/technician/orders/{id}/notes */
    public function addNote(Request $request, int $id): JsonResponse
    {
        $tech = $request->user();
        $order = Order::query()->whereKey($id)->firstOrFail();
        $this->authorizeOwnership($order, $tech);

        if ($order->status->isFinal()) {
            throw ValidationException::withMessages(['note' => 'ثبت یادداشت روی سفارش‌های نهایی مجاز نیست.']);
        }

        $validated = $request->validate(['note' => 'required|string|max:2000']);

        $existing = $order->wp_notes;
        $existing[] = [
            'subject' => 'یادداشت تکنسین',
            'content' => trim($validated['note']),
            'author' => (int) $tech->id,
            'date' => now()->toDateTimeString(),
        ];
        $order->update(['order_note_content' => json_encode($existing, JSON_UNESCAPED_UNICODE)]);

        return response()->json(['success' => true, 'message' => 'یادداشت ثبت شد.']);
    }

    /** POST /v1/technician/orders/{id}/deliver-sms — پیامکِ «آماده تحویل» (اگر مجاز). */
    public function sendDeliverSms(Request $request, int $id): JsonResponse
    {
        $tech = $request->user();
        $order = Order::query()->whereKey($id)->firstOrFail();
        $this->authorizeOwnership($order, $tech);

        if (! $tech->ready_for_delivery) {
            abort(403, 'شما مجاز به ارسال پیامک آماده تحویل نیستید.');
        }
        if ($order->status !== OrderStatus::Completed) {
            throw ValidationException::withMessages(['status' => 'این پیامک فقط برای سفارش‌های تکمیل‌شده ارسال می‌شود.']);
        }

        $this->smsNotifier->notify($order, SmsTrigger::OrderDelivered, $tech->user_id);

        return response()->json(['success' => true, 'message' => 'پیامک آماده تحویل برای مشتری ارسال شد.']);
    }

    /**
     * بلاکِ فاکتورِ Completed — اعتبارسنجی + محاسبهٔ total_invoice + قطعات + عکس.
     *
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $updates
     * @return array<string, mixed>
     */
    private function applyCompletionBlock(Request $request, Order $order, array $validated, array $updates): array
    {
        $isDraft = (bool) ($validated['save_as_draft'] ?? false);
        $isReturned = ! is_null($order->return_type);
        $hasNewImage = $request->hasFile('device_img1');
        $hasExistingImage = ! empty($order->device_img1);

        $errors = [];
        if (! $isDraft && ! $isReturned && ! $hasNewImage && ! $hasExistingImage) {
            $errors['device_img1'] = 'برای بستن سفارش، آپلود عکس دستگاه پس از تعمیر اجباری است.';
        }
        $invDesc = trim((string) ($validated['invoice_descripotion'] ?? ''));
        if (! $isDraft && ! $isReturned && $invDesc === '') {
            $errors['invoice_descripotion'] = 'توضیحات فاکتور اجباری است.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $updates['completed_at'] = now();

        $pieces = collect($validated['pieces'] ?? [])->filter(fn ($p) => filled($p['title'] ?? null))->values();
        if ($pieces->isNotEmpty()) {
            $updates['piece_list'] = $pieces->pluck('title')->all();
            $updates['buy_price_list'] = $pieces->map(fn ($p) => (int) ($p['buy_price'] ?? 0))->all();
            $updates['customer_price_list'] = $pieces->map(fn ($p) => (int) ($p['customer_price'] ?? 0))->all();
            $updates['cost_price'] = (int) $pieces->sum(fn ($p) => (int) ($p['buy_price'] ?? 0));
        } else {
            $updates['cost_price'] = 0;
        }

        foreach (['price_customer', 'hire', 'transportation', 'discount'] as $field) {
            if (array_key_exists($field, $validated) && $validated[$field] !== null) {
                $updates[$field] = (int) $validated[$field];
            }
        }

        $priceCustomer = (int) ($updates['price_customer'] ?? $order->price_customer ?? 0);
        $costPrice = (int) ($updates['cost_price'] ?? $order->cost_price ?? 0);
        if (! $isDraft && ! $isReturned && $priceCustomer < $costPrice) {
            throw ValidationException::withMessages([
                'price_customer' => 'جمع کل مبلغ فاکتور نمی‌تواند کمتر از جمع هزینهٔ قطعات باشد.',
            ]);
        }
        $updates['total_invoice'] = max(0, $priceCustomer - $costPrice);

        if (filled($validated['invoice_descripotion'] ?? null)) {
            $updates['invoice_descripotion'] = $validated['invoice_descripotion'];
        }
        $updates['save_as_draft'] = $isDraft;

        if ($request->hasFile('device_img1')) {
            $updates['device_img1'] = $request->file('device_img1')->store("crm/orders/{$order->id}", 'public');
        }

        return $updates;
    }

    /**
     * @return array<int, OrderStatus>
     */
    private function allowedStatusesFor(Order $order): array
    {
        if ($order->status->isFinal()) {
            return [];
        }
        $returnType = (int) ($order->return_type ?? 0);
        if ($returnType === 1) {
            return [OrderStatus::Completed];
        }
        if ($returnType === 2) {
            return [OrderStatus::Cancelled, OrderStatus::Completed];
        }

        $base = [
            OrderStatus::Coordinated, OrderStatus::RepairStarted, OrderStatus::Open,
            OrderStatus::AwaitingPart, OrderStatus::AwaitingCustomerApproval,
            OrderStatus::Suspended, OrderStatus::Completed, OrderStatus::Declined, OrderStatus::Transit,
        ];
        if (CrmSetting::get('tech_panel_readonly') === '1') {
            $base = array_filter($base, fn (OrderStatus $s) => ! $s->isFinal());
        }

        return array_values(array_filter($base, fn (OrderStatus $s) => $s !== $order->status));
    }

    private function authorizeOwnership(Order $order, $tech): void
    {
        abort_unless((int) $order->technician_id === (int) $tech->id, 403, 'این سفارش به شما تخصیص داده نشده است.');
    }
}
