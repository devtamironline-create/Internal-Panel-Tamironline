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
use Modules\CRM\Support\TechImageStorage;

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
        // تخمینِ زمان فقط برای انتقال به تعمیرگاه اجباری است.
        $needsEstimate = (string) $request->input('status') === OrderStatus::Transit->value;

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
            'device_img1' => 'nullable|image|max:30720',
            // تخمینِ آماده‌شدن دستگاه — برای «انتقال به تعمیرگاه» الزامی،
            // برای «در انتظار قطعه» اختیاری. سقفِ ۱۴ روز همان قاعدهٔ فریزِ
            // پنل است و سرور هم آن را enforce می‌کند، نه فقط اپ.
            'estimated_ready_at' => [
                $needsEstimate ? 'required' : 'nullable',
                'date_format:Y-m-d',
                'after_or_equal:today',
                'before_or_equal:'.\Modules\CRM\Support\SlaPolicy::maxEstimateDate()->format('Y-m-d'),
            ],
        ], [
            'description.required' => 'برای ثبت تغییر این وضعیت، توضیحات الزامی است.',
            'description.min' => 'توضیحات باید حداقل ۱۵ کاراکتر باشد.',
            'estimated_ready_at.required' => 'تاریخ تخمینی آماده‌شدن دستگاه را انتخاب کنید.',
            'estimated_ready_at.after_or_equal' => 'تاریخ تخمینی نمی‌تواند در گذشته باشد.',
            'estimated_ready_at.before_or_equal' => 'تاریخ تخمینی حداکثر می‌تواند ۱۴ روز آینده باشد.',
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

        // فقط برای وضعیت‌هایی که تخمین معنا دارد ذخیره می‌شود؛ روی بقیه
        // اگر اپ اشتباهی فرستاد، نادیده گرفته می‌شود.
        if (! empty($validated['estimated_ready_at'])
            && in_array($newStatus, [OrderStatus::Transit, OrderStatus::AwaitingPart], true)) {
            $updates['estimated_ready_at'] = $validated['estimated_ready_at'];
        }
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

        // رسیدِ انتقال هنگامِ Open ساخته می‌شود — بدونِ پیامکِ خودکار. ارسالِ
        // پیامک دستی و فقط یک‌بار توسطِ تکنسین انجام می‌شود.
        if ($newStatus === OrderStatus::Open && TransferReceiptService::enabled()) {
            try {
                app(TransferReceiptService::class)
                    ->create($order->refresh(), $description !== '' ? $description : null, $tech->user_id, $tech->id);
            } catch (\Throwable $e) {
            }
        }

        $order->load([
            'customer', 'brand', 'device', 'province', 'city',
            'customerAddress.province', 'customerAddress.city', 'customerAddress.district',
            'items', 'statusLogs', 'transferReceipts', 'objections',
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

        // فقط در فازِ هماهنگی — پس از خروج (باز/شروع تعمیر/…) قفل است (enforce سمتِ سرور).
        if (! $order->status->allowsVisitScheduling()) {
            throw ValidationException::withMessages([
                'status' => 'تنظیم زمان مراجعه فقط در فازِ هماهنگی (جدید/هماهنگ‌شده) ممکن است.',
            ]);
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
        $autoCoordinated = $previous !== OrderStatus::Coordinated
            && in_array(OrderStatus::Coordinated, $previous->technicianTransitions(), true);

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

    /**
     * POST /v1/technician/orders/{id}/call-result — نتیجهٔ تماسِ تلفنی با مشتری.
     * result: coordinated (→ اپ باید فرمِ زمانِ مراجعه را باز کند) | no_answer
     * (→ در فازِ هماهنگی، وضعیت «مشتری پاسخگو نیست» می‌شود؛ وگرنه فقط لاگ).
     */
    public function callResult(Request $request, int $id): JsonResponse
    {
        $tech = $request->user();
        $order = Order::query()->whereKey($id)->firstOrFail();
        $this->authorizeOwnership($order, $tech);

        $validated = $request->validate([
            'result' => 'required|in:coordinated,no_answer',
            'reason' => 'required_if:result,no_answer|nullable|string|min:3|max:1000',
        ], [
            'reason.required_if' => 'لطفاً دلیل عدم پاسخگویی را بنویسید.',
        ]);

        if ($order->status->isFinal()) {
            throw ValidationException::withMessages(['result' => 'این سفارش نهایی شده است.']);
        }

        $order->refresh();
        $previous = $order->status;
        $coordinationPhase = in_array($previous, [
            OrderStatus::New, OrderStatus::AwaitingCoordination, OrderStatus::NoAnswer,
        ], true);

        if ($validated['result'] === 'no_answer') {
            if ($coordinationPhase && $previous !== OrderStatus::NoAnswer) {
                $order->update(['status' => OrderStatus::NoAnswer->value]);
            }
            $reason = trim((string) ($validated['reason'] ?? ''));
            OrderStatusLog::create([
                'order_id' => $order->id,
                'from_status' => $previous->value,
                'to_status' => $coordinationPhase ? OrderStatus::NoAnswer->value : $previous->value,
                'note' => 'نتیجهٔ تماس تلفنی: مشتری پاسخگو نبود'.($reason !== '' ? ' — '.$reason : '').'.',
                'changed_by' => $tech->user_id,
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'نتیجهٔ تماس ثبت شد: مشتری پاسخگو نیست.',
                'data' => ['status' => $order->fresh()->status?->value, 'next_action' => null],
            ]);
        }

        OrderStatusLog::create([
            'order_id' => $order->id,
            'from_status' => $previous->value,
            'to_status' => $previous->value,
            'note' => 'نتیجهٔ تماس تلفنی: با مشتری هماهنگ شد — در انتظار ثبت زمان مراجعه.',
            'changed_by' => $tech->user_id,
            'created_at' => now(),
        ]);

        $hasDefaultTime = $order->visit_scheduled_at !== null;

        return response()->json([
            'success' => true,
            'message' => $hasDefaultTime
                ? 'زمانِ پیشنهادیِ مشتری پیش‌پر است — تأیید کنید تا «هماهنگ شده» شود.'
                : 'حالا زمانِ مراجعه را انتخاب و ثبت کنید تا وضعیت «هماهنگ شده» شود.',
            'data' => ['status' => $previous->value, 'next_action' => 'schedule_visit', 'has_default_time' => $hasDefaultTime],
        ]);
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
        if (! $isDraft && ! $isReturned && $priceCustomer <= 0) {
            throw ValidationException::withMessages([
                'price_customer' => 'برای بستن سفارش، مبلغ کل فاکتور باید بیشتر از صفر باشد.',
            ]);
        }
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
            // بهینه‌سازیِ سخت؛ فایلِ اصلیِ تکنسین ذخیره نمی‌شود.
            $updates['device_img1'] = TechImageStorage::store($request->file('device_img1'), "crm/orders/{$order->id}");
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

        // گذارِ مرحله‌ای ∩ اجازهٔ تکنسین — هم‌ارزِ PWA.
        $base = $order->status->technicianTransitions();
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
