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
        $this->guardNotFrozen($order);

        $request->merge(['description' => trim((string) $request->input('description', ''))]);

        // توضیح فقط برای این وضعیت‌ها الزامی است (Open اختیاری — رسیدِ انتقال).
        // «هماهنگ شده» عمداً توضیح نمی‌خواهد: تکنسین فقط تقویم را می‌بیند و
        // زمان را انتخاب می‌کند (تصمیمِ ۱۴۰۵/۰۵)؛ توضیح اگر بیاید اختیاری است.
        $needsDesc = in_array((string) $request->input('status'), [
            OrderStatus::Suspended->value,
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
            // «روش دریافت وجه» — cash: تکنسین در محل نقد گرفته (درگاه به
            // مشتری نشان داده نمی‌شود)؛ online: مشتری آنلاین می‌پردازد و
            // تکنسین نباید نقدی بگیرد. nullable تا کلاینت‌های قدیمی نشکنند.
            'payment_collection' => 'nullable|in:cash,online',
            'save_as_draft' => 'nullable|boolean',
            // سقف را از خودِ PHP می‌گیریم، نه عددِ ثابت: اگر
            // upload_max_filesize سرور کوچک‌تر باشد، فایل پیش از رسیدن به
            // لاراول دور انداخته می‌شود و پیامِ خطا بی‌فایده می‌شود.
            'device_img1' => \Modules\CRM\Support\UploadLimits::imageRule(),
            // تخمینِ آماده‌شدن دستگاه — اختیاری و فقط برای «در انتظار قطعه»
            // معنا دارد. transit («ایاب و ذهاب») وضعیتِ بستن است و تخمین
            // نمی‌خواهد. سقفِ ۱۴ روز همان قاعدهٔ فریزِ پنل است.
            'estimated_ready_at' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:today',
                'before_or_equal:'.\Modules\CRM\Support\SlaPolicy::maxEstimateDate()->format('Y-m-d'),
            ],
        ], [
            'description.required' => 'برای ثبت تغییر این وضعیت، توضیحات الزامی است.',
            'description.min' => 'توضیحات باید حداقل ۱۵ کاراکتر باشد.',
            'estimated_ready_at.after_or_equal' => 'تاریخ تخمینی نمی‌تواند در گذشته باشد.',
            'estimated_ready_at.before_or_equal' => 'برای رفعِ مشکل حداکثر '.\Modules\CRM\Support\SlaPolicy::MAX_ESTIMATE_DAYS.' روز می‌توانید انتخاب کنید.',
            'device_img1.max' => \Modules\CRM\Support\UploadLimits::tooLargeMessage(),
            'device_img1.uploaded' => \Modules\CRM\Support\UploadLimits::failedMessage(),
            'device_img1.image' => 'فایل انتخابی عکس نیست. یک عکس (JPG یا PNG) انتخاب کنید.',
        ]);

        // مفهومِ «پیش‌نویس» حذف شده است (تصمیمِ ۱۴۰۵/۰۵): تکمیل همیشه
        // نهایی است و همان لحظه فاکتور/بدهی می‌سازد. ورودیِ کلاینت‌های
        // قدیمی عمداً نادیده گرفته می‌شود؛ فقط نهایی‌سازیِ پیش‌نویس‌های
        // موجودِ قدیمی از allowedStatusesFor باز مانده است.
        $validated['save_as_draft'] = false;

        $newStatus = OrderStatus::tryFrom($validated['status']);
        if (! $newStatus) {
            throw ValidationException::withMessages(['status' => 'وضعیت نامعتبر است.']);
        }

        // خطِ قرمزِ برگشتی: تا وقتی نتیجهٔ بررسیِ برگشتی ثبت نشده، بستنِ
        // سفارش (تکمیل/ایاب و ذهاب/کنسل/رد) ممکن نیست — هماهنگی و مراجعه
        // آزاد است. قبل از چکِ allowedStatuses می‌آید تا پیامِ خطا دقیق
        // باشد، نه «مجاز نیست»ِ عمومی.
        if ($order->return_review_pending && $newStatus->isFinal()) {
            throw ValidationException::withMessages([
                'status' => 'این سفارش برگشتی است؛ قبل از بستن، ابتدا نتیجهٔ بررسی برگشتی (تأیید یا رد) را پس از مراجعه در محل ثبت کنید.',
            ]);
        }

        if (! in_array($newStatus, $this->allowedStatusesFor($order), true)) {
            throw ValidationException::withMessages(['status' => 'تغییر به این وضعیت در شرایط فعلی مجاز نیست.']);
        }

        // «هماهنگ شده» بدونِ زمانِ مراجعه معنا ندارد — مسیرِ درست
        // schedule-visit است که خودش وضعیت را هماهنگ می‌کند. ثبتِ مستقیم
        // فقط وقتی مجاز است که زمانِ مراجعه از قبل ثبت شده باشد.
        if ($newStatus === OrderStatus::Coordinated && $order->visit_scheduled_at === null) {
            throw ValidationException::withMessages([
                'status' => 'برای «هماهنگ شده» ابتدا زمان مراجعه را از تقویم ثبت کنید.',
            ]);
        }

        // وقتی بستانکاریِ تکنسین از شرکت به سقف رسیده، دریافتِ اعتباری
        // بسته است — پرداختِ آنلاین بدهیِ شرکت را باز هم بیشتر می‌کند.
        // InvoiceService هم null/online را cash می‌کند (دفاع در عمق)؛
        // این‌جا انتخابِ صریحِ online با پیامِ روشن رد می‌شود.
        if (($validated['payment_collection'] ?? null) === 'online'
            && $tech->isOnlineCollectionBlocked()) {
            throw ValidationException::withMessages([
                'payment_collection' => 'اعتبار کیف‌پول شما به سقف مجاز رسیده است؛ برای این فاکتور فقط دریافت نقدی ممکن است.',
            ]);
        }

        $description = trim($validated['description'] ?? '');
        $updates = ['status' => $newStatus->value];

        // فقط برای وضعیتی که تخمین معنا دارد ذخیره می‌شود؛ روی بقیه
        // اگر اپ اشتباهی فرستاد، نادیده گرفته می‌شود.
        if (! empty($validated['estimated_ready_at']) && $newStatus === OrderStatus::AwaitingPart) {
            $updates['estimated_ready_at'] = $validated['estimated_ready_at'];
        }

        // بستن با «ایاب و ذهاب»: هزینهٔ ایاب و ذهاب (تومان) اختیاری است و
        // اگر بیاید روی خودِ سفارش می‌نشیند. فاکتور/بدهی ساخته نمی‌شود.
        if ($newStatus === OrderStatus::Transit
            && array_key_exists('transportation', $validated) && $validated['transportation'] !== null) {
            $updates['transportation'] = (int) $validated['transportation'];
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
        $this->clearForceReview($order);

        // توضیحاتِ فاکتوری که تکنسین وارد می‌کند هم باید در تاریخچهٔ لاگ‌ها
        // بماند (۱۴۰۵/۰۶/۰۳) — نه فقط روی فیلدِ سفارش که با ویرایشِ بعدی
        // بازنویسی می‌شود.
        $note = $description !== '' ? $description : null;
        $invoiceDesc = trim((string) ($updates['invoice_descripotion'] ?? ''));
        if ($invoiceDesc !== '') {
            $note = ($note !== null ? $note."\n" : '').'توضیحات فاکتور: '.$invoiceDesc;
        }

        OrderStatusLog::create([
            'order_id' => $order->id,
            'from_status' => $previous,
            'to_status' => $newStatus->value,
            'note' => $note,
            'changed_by' => $tech->user_id,
            ...OrderStatusLog::technicianActor($tech),
            'created_at' => now(),
        ]);

        if ($newStatus === OrderStatus::Completed && empty($updates['save_as_draft'])) {
            // تصمیمِ بازصدور تنها در InvoiceService است: ادامهٔ **رایگانِ**
            // برگشتیِ تأییدشده فاکتورِ قبلی را دست نمی‌زند، ولی اگر همان
            // برگشتی مبلغ داشته باشد فاکتور بازصادر می‌شود (وگرنه بدهیِ
            // تکنسین روی عددِ قدیمی می‌ماند).
            $order->refresh();
            $this->invoiceService->generateForOrder(
                $order,
                $tech->user_id,
                $this->invoiceService->completionInvoiceMode($order),
                $validated['payment_collection'] ?? null
            );
        }

        // SMS خودکارِ وضعیت. نهایی‌سازیِ پیش‌نویس (Completed→Completed) پیامک
        // ندارد — مشتری موقعِ تکمیلِ اول خبردار شده.
        if ($trigger = SmsTrigger::fromOrderStatus($newStatus)) {
            $skip = $newStatus === OrderStatus::Completed
                && (! is_null($order->return_type) || $previous === OrderStatus::Completed->value);
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
        $this->guardNotFrozen($order);

        // در همهٔ وضعیت‌های غیرنهایی مجاز است — پس از بستنِ سفارش قفل می‌شود.
        if (! $order->status->allowsVisitScheduling()) {
            throw ValidationException::withMessages([
                'status' => 'تنظیم زمان مراجعه پس از بسته‌شدن سفارش ممکن نیست.',
            ]);
        }

        // پاک‌کردن
        if ($request->boolean('clear')) {
            $order->update(['visit_scheduled_at' => null]);
            OrderStatusLog::create([
                'order_id' => $order->id, 'from_status' => $order->status->value,
                'to_status' => $order->status->value, 'note' => 'پاک کردن زمان مراجعه',
                'changed_by' => $tech->user_id,
                ...OrderStatusLog::technicianActor($tech), 'created_at' => now(),
            ]);

            return response()->json(['success' => true, 'message' => 'زمان مراجعه پاک شد.']);
        }

        // سقفِ انتخابِ روز: سفارشِ بازگشتی ۳ روز، عادی ۵ روز.
        $isReturn = (bool) $order->return_review_pending || $order->return_type !== null;
        $maxVisit = \Modules\CRM\Support\SlaPolicy::maxVisitDate($isReturn);
        $maxDays = $isReturn
            ? \Modules\CRM\Support\SlaPolicy::MAX_RETURN_VISIT_DAYS
            : \Modules\CRM\Support\SlaPolicy::MAX_VISIT_DAYS;

        $validated = $request->validate([
            'visit_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.$maxVisit->format('Y-m-d')],
            'visit_slot' => ['required', 'integer', 'in:1,2,3,4'],
        ], [
            'visit_date.after_or_equal' => 'زمانِ مراجعه نمی‌تواند در گذشته باشد.',
            'visit_date.before_or_equal' => 'زمانِ مراجعه حداکثر می‌تواند تا '.$maxDays.' روزِ آینده باشد.',
            'visit_date.date_format' => 'قالبِ تاریخِ مراجعه نامعتبر است.',
            'visit_slot.required' => 'بازهٔ ساعتِ مراجعه را انتخاب کنید.',
            'visit_slot.in' => 'بازهٔ ساعتِ مراجعه نامعتبر است.',
        ]);

        $slot = OrderWizard::VISIT_SLOTS[$validated['visit_slot']];
        $datetime = $validated['visit_date'].' '.$slot['start'];

        $order->refresh();

        // محدودیتِ تغییرِ زمانِ مراجعه: بارِ اولِ ثبت شمرده نمی‌شود؛ هر تغییرِ
        // بعدی +۱ می‌شود و پس از سقفِ مجاز، تکنسین قفل می‌شود تا ادمین شمارنده
        // را صفر کند. (پاک‌کردن هم شمارنده را نگه می‌دارد تا دور زده نشود.)
        // فقط وقتی ستونِ شمارنده در DB موجود باشد اعمال می‌شود — تا در پنجرهٔ
        // دیپلوی (کد پیش از migrate) این مسیر ۵۰۰ ندهد.
        $tracksReschedule = Order::supportsVisitRescheduleCount();
        $newRescheduleCount = (int) ($order->visit_reschedule_count ?? 0);

        if ($tracksReschedule) {
            $hasScheduledBefore = $order->visit_scheduled_at !== null || $newRescheduleCount > 0;
            if ($hasScheduledBefore) {
                if ($newRescheduleCount >= Order::VISIT_RESCHEDULE_LIMIT) {
                    return response()->json([
                        'success' => false,
                        'message' => 'زمانِ مراجعه حداکثر '.Order::VISIT_RESCHEDULE_LIMIT
                            .' بار قابلِ تغییر است. برای تغییرِ بیشتر با پشتیبانی/ادمین هماهنگ کنید.',
                    ], 423);
                }
                $newRescheduleCount++;
            }
        }

        $previous = $order->status;
        $autoCoordinated = $previous !== OrderStatus::Coordinated
            && in_array(OrderStatus::Coordinated, $previous->technicianTransitions(), true);

        $updates = ['visit_scheduled_at' => $datetime];
        if ($tracksReschedule) {
            $updates['visit_reschedule_count'] = $newRescheduleCount;
        }
        if ($autoCoordinated) {
            $updates['status'] = OrderStatus::Coordinated->value;
        }
        $order->update($updates);
        $this->clearForceReview($order);

        $jalali = \Morilog\Jalali\Jalalian::fromDateTime($datetime)->format('Y/m/d');
        OrderStatusLog::create([
            'order_id' => $order->id, 'from_status' => $previous->value,
            'to_status' => ($autoCoordinated ? OrderStatus::Coordinated : $previous)->value,
            'note' => ($autoCoordinated ? 'هماهنگی با مشتری: ' : 'به‌روزرسانی زمان مراجعه: ').$jalali.' — '.$slot['label'],
            'changed_by' => $tech->user_id,
            ...OrderStatusLog::technicianActor($tech), 'created_at' => now(),
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
        $this->guardNotFrozen($order);

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
        $this->guardNotFrozen($order);

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
                ...OrderStatusLog::technicianActor($tech),
                'created_at' => now(),
            ]);

            $this->clearForceReview($order);

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
            ...OrderStatusLog::technicianActor($tech),
            'created_at' => now(),
        ]);

        $this->clearForceReview($order);

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
        $this->guardNotFrozen($order);

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
     * POST /v1/technician/orders/{id}/return-review — تصمیمِ تکنسین دربارهٔ
     * سفارشِ برگشتی: «ایراد از خدماتِ قبلی بود» (تأیید → ادامهٔ رایگان،
     * return_type=1) یا «ایرادِ جدید است» (رد → سفارش مثل سفارشِ عادی با
     * قیمت‌گذاریِ معمول ادامه می‌یابد).
     *
     * یک UPDATE اتمی و بدونِ کارِ جانبیِ سنگین. idempotent: ثبتِ دوباره روی
     * سفارشِ قبلاً بررسی‌شده، بدونِ اثرِ مجدد، همان وضعیتِ فعلی را با 200
     * برمی‌گرداند — چون کلاینت بعد از خطای شبکه دوباره تلاش می‌کند.
     */
    public function returnReview(Request $request, int $id): JsonResponse
    {
        $tech = $request->user();
        $order = Order::query()->whereKey($id)->firstOrFail();
        $this->authorizeOwnership($order, $tech);
        $this->guardNotFrozen($order);

        // قبلاً بررسی شده → پاسخِ تمیزِ idempotent، بدونِ دست‌زدن به چیزی.
        if (! $order->return_review_pending && $order->return_reviewed_at !== null) {
            return response()->json([
                'success' => true,
                'message' => 'بررسی برگشتی قبلاً ثبت شده بود.',
                'data' => [
                    'return_review_pending' => false,
                    'approved' => (bool) $order->return_review_approved,
                    'days' => $order->return_review_days !== null ? (int) $order->return_review_days : null,
                    'reviewed_at' => $order->return_reviewed_at?->utc()->toIso8601String(),
                ],
            ]);
        }

        if (! $order->return_review_pending) {
            throw ValidationException::withMessages([
                'order' => 'این سفارش در انتظار بررسی برگشتی نیست.',
            ]);
        }

        $validated = $request->validate([
            'approved' => 'required|boolean',
            // تخمینِ انجامِ کار فقط برای تأیید معنا دارد — سقف همان قاعدهٔ رفعِ مشکل.
            'days' => 'required_if:approved,1,true|nullable|integer|min:1|max:'.\Modules\CRM\Support\SlaPolicy::MAX_ESTIMATE_DAYS,
            'note' => 'nullable|string|max:1000',
        ], [
            'approved.required' => 'نتیجهٔ بررسی (تأیید یا رد) را مشخص کنید.',
            'days.required_if' => 'برای تأییدِ برگشتی، زمان تخمینی انجام کار (روز) الزامی است.',
            'days.max' => 'تخمین حداکثر می‌تواند '.\Modules\CRM\Support\SlaPolicy::MAX_ESTIMATE_DAYS.' روز باشد.',
        ]);

        $approved = filter_var($validated['approved'], FILTER_VALIDATE_BOOLEAN);

        // فیلدهای قیمت/فاکتور صفر می‌شوند تا فاکتورِ تکمیلِ مجدد پیش‌فرضِ ۰
        // داشته باشد (گارانتی → می‌تواند ۰ بماند؛ غیرگارانتی → تکنسین عددِ
        // جدید وارد می‌کند). فاکتورِ قبلی دست‌نخورده و فعال می‌ماند.
        $order->update([
            'return_review_pending' => false,
            'return_reviewed_at' => now(),
            'return_review_approved' => $approved,
            'return_review_days' => $approved ? (int) $validated['days'] : null,
            // تأیید = ایراد از خدماتِ قبلی → ادامهٔ رایگان (فقط تکمیل).
            // رد = ایرادِ جدید → سفارشِ عادی، بدونِ مسیرِ برگشتی.
            'return_type' => $approved ? 1 : null,
        ] + Order::reworkPriceResetFields());

        $note = trim((string) ($validated['note'] ?? ''));
        OrderStatusLog::create([
            'order_id' => $order->id,
            'from_status' => $order->status?->value ?? '',
            'to_status' => $order->status?->value ?? '',
            'note' => 'بررسی برگشتی: '.($approved
                ? 'تأیید — ایراد از خدمات قبلی، ادامهٔ رایگان ('.(int) $validated['days'].' روز)'
                : 'رد — ایراد جدید، ادامه مانند سفارش عادی')
                .($note !== '' ? ' — '.$note : ''),
            'changed_by' => $tech->user_id,
            ...OrderStatusLog::technicianActor($tech),
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $approved
                ? 'برگشتی تأیید شد — خدمات جدید بدون هزینه برای مشتری ثبت می‌شود.'
                : 'برگشتی رد شد — سفارش مانند سفارش جدید ادامه پیدا می‌کند.',
            'data' => [
                'return_review_pending' => false,
                'approved' => $approved,
                'days' => $approved ? (int) $validated['days'] : null,
                'reviewed_at' => $order->fresh()->return_reviewed_at?->utc()->toIso8601String(),
            ],
        ]);
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
        // عکسِ دستگاه برای بستنِ فاکتور همیشه اجباری است. سفارشِ بازگشتی
        // باید عکسِ *جدید* داشته باشد (عکسِ سرویسِ قبلی کافی نیست)؛ سفارشِ
        // عادی با عکسِ موجود هم بسته می‌شود (تصمیمِ ۱۴۰۵/۰۶/۱۰).
        $photoOk = $isReturned ? $hasNewImage : ($hasNewImage || $hasExistingImage);
        if (! $isDraft && ! $photoOk) {
            $errors['device_img1'] = $isReturned
                ? 'برای بستنِ سفارشِ بازگشتی، آپلودِ عکسِ جدیدِ دستگاه اجباری است.'
                : 'برای بستن سفارش، آپلود عکس دستگاه پس از تعمیر اجباری است.';
        }
        // توضیحاتِ فاکتور برای «بستنِ» سفارش همیشه اجباری است — حتی
        // برگشتیِ رایگان (تصمیمِ ۱۴۰۵/۰۵/۲۷): این متن سندِ کارِ انجام‌شده است.
        $invDesc = trim((string) ($validated['invoice_descripotion'] ?? ''));
        if (! $isDraft && $invDesc === '') {
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
        // پیش‌نویسِ تکمیل‌شده هنوز فاکتور و بدهی ندارد — تنها گذارِ مجاز،
        // ثبتِ نهاییِ همان «تکمیل شده» (بدون save_as_draft) است؛ وگرنه
        // isFinal پایین راهِ نهایی‌سازی را برای همیشه می‌بست.
        if ($order->status === OrderStatus::Completed && $order->save_as_draft) {
            return CrmSetting::get('tech_panel_readonly') === '1' ? [] : [OrderStatus::Completed];
        }

        if ($order->status->isFinal()) {
            return [];
        }

        // برگشتیِ در انتظارِ بررسی: وضعیت‌های بستن اصلاً در لیست نمی‌آیند
        // تا اپ آن‌ها را نشان ندهد — گیتِ updateStatus هم پشتش ایستاده.
        if ($order->return_review_pending) {
            return array_values(array_filter(
                $order->status->technicianTransitions(),
                fn (OrderStatus $s) => ! $s->isFinal() && $s !== $order->status
            ));
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

    /**
     * پاک‌کردنِ «اجبار به تعیینِ وضعیت» — به‌محضِ اینکه تکنسین روی سفارش
     * اقدامی برای تعیینِ وضعیت انجام دهد، قفلِ تمام‌صفحهٔ اپ برداشته می‌شود.
     * فقط وقتی پرچم روشن است می‌نویسد (اگر ستون نباشد force_review نال است).
     */
    private function clearForceReview(Order $order): void
    {
        if ($order->force_review) {
            $order->forceFill([
                'force_review' => false,
                'force_review_at' => null,
                'force_review_by' => null,
            ])->save();
        }
    }

    /**
     * اگر سفارش توسطِ ادمین «فریز/قفل» شده باشد، هر تغییرِ سمتِ تکنسین با
     * ۴۲۳ مسدود می‌شود (خواندن آزاد است). ادمین باید قفل را باز کند.
     */
    private function guardNotFrozen(Order $order): void
    {
        abort_if(
            (bool) $order->is_locked,
            423,
            'این سفارش توسطِ پشتیبانی قفل شده است و فعلاً قابلِ تغییر نیست. لطفاً با دفتر هماهنگ کنید.'
        );
    }
}
