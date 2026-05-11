<?php

namespace Modules\CRM\Http\Controllers\Tech;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Enums\SmsTrigger;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\OrderStatusLog;
use Modules\CRM\Models\TrainingCategory;
use Modules\CRM\Models\TrainingVideo;
use Modules\CRM\Models\Payment;
use Modules\CRM\Models\WalletTransaction;
use Modules\CRM\Services\InvoiceService;
use Modules\CRM\Services\OrderSmsNotifier;
use Modules\CRM\Services\ZibalService;

/**
 * کنترلر داشبورد + صفحات اصلی پنل تکنسین.
 *
 * فاز ۳: سفارش‌ها از placeholder خارج شد. کیف‌پول/فاکتور/پروفایل
 * هنوز placeholder هستند و در فازهای بعدی فعال می‌شوند.
 */
class DashboardController extends Controller
{
    public function __construct(
        protected OrderSmsNotifier $smsNotifier,
        protected InvoiceService $invoiceService,
    ) {
    }

    public function index()
    {
        $tech = Auth::guard('tech')->user();

        // برای دکوریشن مالی هدر/باکس‌ها لازم است sum سهم شرکت روی technician
        // cache شود تا accessor invoice_debt یک query اضافه نزند.
        $tech->loadSum('invoices', 'company_share');

        // تقویم داشبورد — ۷ روز آینده با تفکیک سفارش‌ها بر اساس بازهٔ
        // ساعتی (۹–۱۲، ۱۲–۱۵، ۱۵–۱۸، ۱۸–۲۱). همان VISIT_SLOTS که در
        // ویزارد و فرم هماهنگی تکنسین استفاده می‌شود.
        // سفارش‌های بدون visit_scheduled_at (معمولاً sync‌شده از WP) را
        // هم بر اساس created_at داخل همان روز در یک بخش «بدون زمان مشخص»
        // نمایش می‌دهیم تا تکنسین آن‌ها را دور بنماند.
        $calendarStart = now()->startOfDay();
        $calendarEnd = $calendarStart->copy()->addDays(7)->endOfDay();
        $activeStatuses = [
            OrderStatus::New->value,
            OrderStatus::Coordinated->value,
            OrderStatus::Open->value,
            OrderStatus::Suspended->value,
        ];

        // ۱) سفارش‌های دارای زمان مراجعه (در بازهٔ ۷ روز پیشِ‌رو) —
        //    فقط سفارش‌هایی که هنوز نهایی نشده‌اند. سفارش رد/کنسل/
        //    تکمیل/ایاب‌و‌ذهاب دیگر در تقویم هماهنگی نباید بمانند.
        $scheduledOrders = Order::query()
            ->forTechnician($tech->id)
            ->whereIn('status', $activeStatuses)
            ->whereBetween('visit_scheduled_at', [$calendarStart, $calendarEnd])
            ->with('customer:id,first_name,mobile')
            ->orderBy('visit_scheduled_at')
            ->get(['id', 'order_code', 'customer_id', 'customer_name', 'customer_mobile', 'visit_scheduled_at', 'status', 'created_at']);

        // ۲) سفارش‌های فعالِ بدون زمان مراجعه — همه را داخل تقویم
        // بر اساس created_at توزیع می‌کنیم. اگر created_at قبل از امروز
        // باشد، در روز «امروز» قرار می‌گیرد تا دور نماند.
        $unscheduledOrders = Order::query()
            ->forTechnician($tech->id)
            ->whereNull('visit_scheduled_at')
            ->whereIn('status', $activeStatuses)
            ->with('customer:id,first_name,mobile')
            ->latest('created_at')
            ->get(['id', 'order_code', 'customer_id', 'customer_name', 'customer_mobile', 'visit_scheduled_at', 'status', 'created_at']);

        $scheduledByDay = $scheduledOrders->groupBy(fn (Order $o) => $o->visit_scheduled_at?->toDateString());

        // unscheduled را بر اساس created_at به روزها نگاشت می‌کنیم؛ اگر
        // created_at قدیمی‌تر از امروز است، آن را امروز در نظر می‌گیریم
        // تا تکنسین فوراً ببیند و هماهنگی کند.
        $todayKey = $calendarStart->toDateString();
        $unscheduledByDay = $unscheduledOrders->groupBy(function (Order $o) use ($calendarStart, $calendarEnd, $todayKey) {
            $created = $o->created_at?->copy() ?? now();
            if ($created->lt($calendarStart) || $created->gt($calendarEnd)) {
                return $todayKey; // قدیمی یا خیلی دور — به امروز bucket کن
            }
            return $created->toDateString();
        });

        $calendarDays = [];
        $slots = \Modules\CRM\Livewire\OrderWizard::VISIT_SLOTS;
        for ($i = 0; $i < 7; $i++) {
            $d = $calendarStart->copy()->addDays($i);
            $dayOrders = $scheduledByDay->get($d->toDateString(), collect());
            $dayUnscheduled = $unscheduledByDay->get($d->toDateString(), collect());

            // تفکیک سفارش‌های هر روز در ۴ بازه
            $slotBuckets = [1 => collect(), 2 => collect(), 3 => collect(), 4 => collect(), 'other' => collect()];
            foreach ($dayOrders as $o) {
                $h = (int) $o->visit_scheduled_at?->format('H');
                $key = match (true) {
                    $h >= 9 && $h < 12 => 1,
                    $h >= 12 && $h < 15 => 2,
                    $h >= 15 && $h < 18 => 3,
                    $h >= 18 && $h < 21 => 4,
                    default => 'other',
                };
                $slotBuckets[$key]->push($o);
            }

            $calendarDays[] = [
                'date'    => $d,
                'count'   => $dayOrders->count() + $dayUnscheduled->count(),
                'scheduledCount' => $dayOrders->count(),
                'unscheduledCount' => $dayUnscheduled->count(),
                'slots'   => [
                    ['key' => 1, 'label' => $slots[1]['label'], 'orders' => $slotBuckets[1]],
                    ['key' => 2, 'label' => $slots[2]['label'], 'orders' => $slotBuckets[2]],
                    ['key' => 3, 'label' => $slots[3]['label'], 'orders' => $slotBuckets[3]],
                    ['key' => 4, 'label' => $slots[4]['label'], 'orders' => $slotBuckets[4]],
                ],
                'offSlot' => $slotBuckets['other'],
                'unscheduled' => $dayUnscheduled,
            ];
        }

        return view('crm::tech-panel.dashboard', [
            'technician' => $tech,
            'calendarDays' => $calendarDays,
        ]);
    }

    /**
     * صفحه تقویم کاری — ۷ روز آینده هرکدام با لیست کامل سفارش‌ها.
     * بر اساس visit_scheduled_at سفارش (تاریخ هماهنگی با مشتری).
     */
    public function calendar()
    {
        $tech = Auth::guard('tech')->user();

        $start = now()->startOfDay();
        $end = $start->copy()->addDays(7)->endOfDay();

        // فقط سفارش‌های فعال — نهایی‌شده‌ها از تقویم خارج می‌شوند.
        $orders = Order::query()
            ->forTechnician($tech->id)
            ->whereIn('status', [
                OrderStatus::New->value,
                OrderStatus::Coordinated->value,
                OrderStatus::Open->value,
                OrderStatus::Suspended->value,
            ])
            ->whereBetween('visit_scheduled_at', [$start, $end])
            ->with(['customer', 'brand', 'device'])
            ->orderBy('visit_scheduled_at')
            ->get();

        // گروه‌بندی بر اساس تاریخ (Y-m-d میلادی) برای صفحه نمایش.
        $byDay = $orders->groupBy(fn (Order $o) => $o->visit_scheduled_at?->toDateString());

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $start->copy()->addDays($i);
            $key = $d->toDateString();
            $days[] = [
                'date'   => $d,
                'orders' => $byDay->get($key, collect()),
            ];
        }

        return view('crm::tech-panel.calendar', [
            'technician' => $tech,
            'days' => $days,
        ]);
    }

    public function orders(Request $request)
    {
        $tech = Auth::guard('tech')->user();

        $statusFilter = $request->query('status');
        $search = $request->query('q');

        $base = Order::query()->forTechnician($tech->id);

        // آمار وضعیت‌های مهم برای تکنسین (همیشه روی کل سفارش‌های تکنسین).
        $stats = [
            'total' => (clone $base)->count(),
            'coordinated' => (clone $base)->ofStatus(OrderStatus::Coordinated)->count(),
            'open' => (clone $base)->ofStatus(OrderStatus::Open)->count(),
            'completed' => (clone $base)->ofStatus(OrderStatus::Completed)->count(),
        ];

        $query = (clone $base)
            ->with(['customer', 'brand', 'device'])
            ->search($search);

        if ($statusFilter && OrderStatus::tryFrom($statusFilter)) {
            $query->ofStatus($statusFilter);
        }

        $orders = $query->latest()->paginate(15)->withQueryString();

        return view('crm::tech-panel.orders', [
            'technician' => $tech,
            'orders' => $orders,
            'stats' => $stats,
            'statusFilter' => $statusFilter,
            'search' => $search,
        ]);
    }

    public function showOrder(Order $order)
    {
        $tech = Auth::guard('tech')->user();
        $this->ensureOwnership($order, $tech);

        $order->load([
            'customer', 'brand', 'device', 'province', 'city',
            'items', 'statusLogs.changer',
        ]);

        return view('crm::tech-panel.order_show', [
            'technician' => $tech,
            'order' => $order,
            'allowedStatuses' => $this->allowedStatusesFor($order),
        ]);
    }

    public function updateOrderStatus(Request $request, Order $order)
    {
        $tech = Auth::guard('tech')->user();
        $this->ensureOwnership($order, $tech);

        $validated = $request->validate([
            'status' => 'required|string',
            'description' => 'nullable|string|max:2000',

            // فیلدهای فاکتور — فقط زمانی استفاده می‌شوند که وضعیت = Completed.
            'price_customer' => 'nullable|integer|min:0',
            'total_invoice' => 'nullable|integer|min:0',
            'hire' => 'nullable|integer|min:0',
            'transportation' => 'nullable|integer|min:0',
            'discount' => 'nullable|integer|min:0',
            'pieces' => 'nullable|array',
            'pieces.*.title' => 'nullable|string|max:255',
            'pieces.*.buy_price' => 'nullable|integer|min:0',
            'pieces.*.customer_price' => 'nullable|integer|min:0',
            'invoice_descripotion' => 'nullable|string|max:2000',
            'save_as_draft' => 'nullable|boolean',
            'device_img1' => 'nullable|image|max:5120',
        ]);

        $newStatus = OrderStatus::tryFrom($validated['status']);
        if (! $newStatus) {
            return back()->with('error', 'وضعیت نامعتبر است.');
        }

        $allowed = $this->allowedStatusesFor($order);
        if (! in_array($newStatus, $allowed, true)) {
            return back()->with('error', 'تغییر به این وضعیت در شرایط فعلی مجاز نیست.');
        }

        $description = trim($validated['description'] ?? '');

        // نگاشت توضیح هر وضعیت روی فیلد متناظر — هم‌سو با پنل WP:
        // 1=description_tech, 3=description_tech1, 2=description_tech2, 100=cancel_reason.
        $updates = ['status' => $newStatus->value];
        if ($description !== '') {
            $updates += match ($newStatus) {
                OrderStatus::Coordinated => ['description_tech' => $description],
                OrderStatus::Suspended   => ['description_tech1' => $description],
                OrderStatus::Open        => ['description_tech2' => $description],
                OrderStatus::Declined    => ['cancel_reason' => $description],
                default                  => [],
            };
        }

        // ─── بلاک فاکتور — هم‌ارز invoice block پنل WP وقتی status=5 ───
        if ($newStatus === OrderStatus::Completed) {
            $updates['completed_at'] = now();

            // قطعات: ورودی به‌صورت آرایه‌ای از {title,buy_price,customer_price}
            // به سه آرایهٔ موازی WP تبدیل می‌شود.
            $pieces = collect($validated['pieces'] ?? [])
                ->filter(fn($p) => filled($p['title'] ?? null))
                ->values();

            if ($pieces->isNotEmpty()) {
                $updates['piece_list'] = $pieces->pluck('title')->all();
                $updates['buy_price_list'] = $pieces->map(fn($p) => (int) ($p['buy_price'] ?? 0))->all();
                $updates['customer_price_list'] = $pieces->map(fn($p) => (int) ($p['customer_price'] ?? 0))->all();
                $updates['cost_price'] = (int) $pieces->sum(fn($p) => (int) ($p['buy_price'] ?? 0));
            } else {
                $updates['cost_price'] = 0;
            }

            // فیلدهای ساده — total_invoice را اینجا قبول نمی‌کنیم؛ پایین
            // به‌صورت خودکار = price_customer - cost_price محاسبه می‌شود
            // (هم‌ارز tech_show_order.php پنل WP).
            foreach (['price_customer', 'hire', 'transportation', 'discount'] as $field) {
                if (array_key_exists($field, $validated) && $validated[$field] !== null) {
                    $updates[$field] = (int) $validated[$field];
                }
            }

            // total_invoice = max(0, price_customer - cost_price). نباید
            // به ورودی کاربر اعتماد کنیم چون مبنای محاسبه سهم تکنسین است.
            $effectivePriceCustomer = (int) ($updates['price_customer'] ?? $order->price_customer ?? 0);
            $effectiveCostPrice = (int) ($updates['cost_price'] ?? $order->cost_price ?? 0);
            $updates['total_invoice'] = max(0, $effectivePriceCustomer - $effectiveCostPrice);

            if (filled($validated['invoice_descripotion'] ?? null)) {
                $updates['invoice_descripotion'] = $validated['invoice_descripotion'];
            }

            $updates['save_as_draft'] = (bool) ($validated['save_as_draft'] ?? false);

            // آپلود تصویر دستگاه
            if ($request->hasFile('device_img1')) {
                $path = $request->file('device_img1')->store("crm/orders/{$order->id}", 'public');
                $updates['device_img1'] = $path;
            }
        }

        // وضعیت قبلی را از DB می‌خوانیم — refresh() مدل را از DB بارگذاری
        // می‌کند تا instance route-bound از داده‌ی stale در امان بماند.
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

        // تولید فاکتور + ثبت کمیسیون در کیف‌پول — هم‌ارز رفتار
        // TechDashboardController قدیمی (admin). idempotent است؛ اگر سفارش
        // قبلاً فاکتور داشته باشد، چیزی ساخته نمی‌شود.
        // پیش‌نویس‌ها فاکتور صادر نمی‌کنند — تکنسین می‌تواند بعداً دوباره ثبت
        // کند بدون save_as_draft تا فاکتور نهایی بخورد.
        if ($newStatus === OrderStatus::Completed && empty($updates['save_as_draft'])) {
            $this->invoiceService->generateForOrder($order->refresh(), $tech->user_id);
        }

        // SMS خودکار طبق وضعیت — هم‌ارز TechDashboardController قدیمی.
        if ($trigger = SmsTrigger::fromOrderStatus($newStatus)) {
            $this->smsNotifier->notify($order->refresh(), $trigger, $tech->user_id);
        }

        return redirect()
            ->route('tech.orders.show', $order)
            ->with('success', 'وضعیت سفارش به «' . $newStatus->label() . '» تغییر کرد.');
    }

    /**
     * ارسال دستی پیامک «آماده تحویل» به مشتری — هم‌ارز دکمهٔ
     * SendSMSForDeliverCustomer در پنل WP. فقط برای تکنسین‌هایی که
     * ready_for_delivery=true دارند، آن‌هم روی سفارش‌های تکمیل‌شده.
     */
    public function sendDeliverSms(Order $order)
    {
        $tech = Auth::guard('tech')->user();
        $this->ensureOwnership($order, $tech);

        if (! $tech->ready_for_delivery) {
            abort(403, 'شما مجاز به ارسال پیامک آماده تحویل نیستید.');
        }

        if ($order->status !== OrderStatus::Completed) {
            return back()->with('error', 'این پیامک فقط برای سفارش‌های تکمیل‌شده ارسال می‌شود.');
        }

        $this->smsNotifier->notify($order, SmsTrigger::OrderDelivered, $tech->user_id);

        return redirect()
            ->route('tech.orders.show', $order)
            ->with('success', 'پیامک آماده تحویل برای مشتری ارسال شد.');
    }

    public function addOrderNote(Request $request, Order $order)
    {
        $tech = Auth::guard('tech')->user();
        $this->ensureOwnership($order, $tech);

        // محافظت در برابر دور زدن قفل از طریق UI: ثبت یادداشت روی سفارش‌های
        // نهایی (Completed/Cancelled/Transit/Returned/Declined) غیرفعال است
        // تا از دسترسی پس از تسویه به اطلاعات تماس مشتری جلوگیری شود.
        if ($order->status->isFinal()) {
            return back()->with('error', 'ثبت یادداشت روی سفارش‌های نهایی مجاز نیست.');
        }

        $validated = $request->validate([
            'note' => 'required|string|max:2000',
        ]);

        // یادداشت‌ها در فیلد order_note_content (هم‌فرمت WP) ذخیره می‌شوند تا
        // accessor wp_notes آن‌ها را برگرداند. ساختار هر آیتم:
        // ['subject', 'content', 'author', 'date'].
        $existing = $order->wp_notes;
        $existing[] = [
            'subject' => 'یادداشت تکنسین',
            'content' => trim($validated['note']),
            'author' => (int) $tech->id,
            'date' => now()->toDateTimeString(),
        ];

        $order->update([
            'order_note_content' => json_encode($existing, JSON_UNESCAPED_UNICODE),
        ]);

        return redirect()
            ->route('tech.orders.show', $order)
            ->with('success', 'یادداشت ثبت شد.');
    }

    /**
     * ثبت/به‌روزرسانی زمان مراجعه توسط تکنسین — وقتی با مشتری تماس
     * می‌گیرد و روز/بازه را هماهنگ می‌کند. نتیجه در visit_scheduled_at
     * ذخیره و در پنل اپراتور هم نمایش داده می‌شود.
     */
    public function scheduleVisit(Request $request, Order $order)
    {
        $tech = Auth::guard('tech')->user();
        $this->ensureOwnership($order, $tech);

        if ($order->status->isFinal()) {
            return back()->with('error', 'تنظیم زمان مراجعه روی سفارش‌های نهایی مجاز نیست.');
        }

        // پاک کردن
        if ($request->filled('clear')) {
            $previous = $order->visit_scheduled_at?->format('Y-m-d H:i');
            $order->update(['visit_scheduled_at' => null]);
            \Modules\CRM\Models\OrderStatusLog::create([
                'order_id' => $order->id,
                'from_status' => $order->status->value,
                'to_status' => $order->status->value,
                'note' => 'پاک کردن زمان مراجعه' . ($previous ? ' (قبلاً: ' . $previous . ')' : ''),
                'changed_by' => $tech->user_id,
                'created_at' => now(),
            ]);
            return back()->with('success', 'زمان مراجعه پاک شد.');
        }

        $validated = $request->validate([
            'visit_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'visit_slot' => ['required', 'integer', 'in:1,2,3,4'],
        ], [
            'visit_date.required' => 'روز مراجعه را انتخاب کنید.',
            'visit_date.after_or_equal' => 'روز مراجعه نمی‌تواند گذشته باشد.',
            'visit_slot.required' => 'بازهٔ ساعت را انتخاب کنید.',
            'visit_slot.in' => 'بازهٔ ساعت معتبر نیست.',
        ]);

        $slot = \Modules\CRM\Livewire\OrderWizard::VISIT_SLOTS[$validated['visit_slot']];
        $datetime = $validated['visit_date'] . ' ' . $slot['start'];

        // وضعیت قبلی را از DB تازه می‌خوانیم — جلوگیری از خواندن stale.
        $order->refresh();
        $previousStatus = $order->status;
        $autoCoordinated = $previousStatus === OrderStatus::New;

        $updates = ['visit_scheduled_at' => $datetime];
        if ($autoCoordinated) {
            $updates['status'] = OrderStatus::Coordinated->value;
        }

        $order->update($updates);

        $jalaliDate = \Morilog\Jalali\Jalalian::fromDateTime($datetime)->format('Y/m/d');
        \Modules\CRM\Models\OrderStatusLog::create([
            'order_id' => $order->id,
            'from_status' => $previousStatus->value,
            'to_status' => ($autoCoordinated ? OrderStatus::Coordinated : $previousStatus)->value,
            'note' => $autoCoordinated
                ? 'هماهنگی با مشتری: ' . $jalaliDate . ' — ' . $slot['label']
                : 'به‌روزرسانی زمان مراجعه: ' . $jalaliDate . ' — ' . $slot['label'],
            'changed_by' => $tech->user_id,
            'created_at' => now(),
        ]);

        // اگر گذار به Coordinated رخ داد، پیامک «هماهنگی» را به مشتری
        // بفرست — هم‌ارز مسیر updateOrderStatus وقتی تکنسین دستی به
        // هماهنگ‌شده تغییر می‌دهد.
        if ($autoCoordinated) {
            if ($trigger = SmsTrigger::fromOrderStatus(OrderStatus::Coordinated)) {
                try {
                    $this->smsNotifier->notify($order->refresh(), $trigger, $tech->user_id);
                } catch (\Throwable $e) {
                    // خرابی SMS نباید ثبت زمان را شکست‌بدهد.
                }
            }
        }

        $message = $autoCoordinated
            ? 'زمان مراجعه ثبت شد و سفارش به وضعیت «هماهنگ شده» تغییر کرد.'
            : 'زمان مراجعه به‌روزرسانی شد.';

        return back()->with('success', $message);
    }

    /**
     * گذارهای مجاز برای تکنسین — هم‌ارز tech_show_order.php پنل WP قدیم.
     *
     * - وضعیت‌های نهایی (Cancelled/Completed/Transit/Declined) قفل هستند:
     *   هیچ گذاری مجاز نیست.
     * - return_type=1 → فقط Completed.
     * - return_type=2 → فقط Cancelled و Completed.
     * - در غیر این‌صورت radioهای WP: Coordinated, Open, Suspended, Completed,
     *   Declined, Transit (وضعیت فعلی از لیست حذف می‌شود).
     *
     * @return array<int, OrderStatus>
     */
    protected function allowedStatusesFor(Order $order): array
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
            OrderStatus::Coordinated,
            OrderStatus::Open,
            OrderStatus::Suspended,
            OrderStatus::Completed,
            OrderStatus::Declined,
            OrderStatus::Transit,
        ];

        return array_values(array_filter($base, fn(OrderStatus $s) => $s !== $order->status));
    }

    protected function ensureOwnership(Order $order, $tech): void
    {
        if ((int) $order->technician_id !== (int) $tech->id) {
            abort(403, 'این سفارش به شما تخصیص داده نشده است.');
        }
    }

    public function wallet(Request $request)
    {
        $tech = Auth::guard('tech')->user();

        $typeFilter = $request->query('type');

        $base = WalletTransaction::query()->where('technician_id', $tech->id);

        // مجموع‌های دسته‌بندی‌شده روی کل تاریخچه — مستقل از فیلتر فعلی.
        $stats = [
            'commission_sum' => (int) (clone $base)->where('type', WalletTxType::Commission->value)->sum('amount'),
            'reward_sum'     => (int) (clone $base)->where('type', WalletTxType::Reward->value)->sum('amount'),
            'penalty_sum'    => (int) (clone $base)->where('type', WalletTxType::Penalty->value)->sum('amount'),
            'charge_sum'     => (int) (clone $base)->where('type', WalletTxType::WalletCharge->value)->sum('amount'),
        ];

        $query = (clone $base)->with(['order', 'invoice']);
        if ($typeFilter && WalletTxType::tryFrom($typeFilter)) {
            $query->where('type', $typeFilter);
        }

        $transactions = $query->latest()->paginate(15)->withQueryString();

        // refresh accessor با لود withSum فاکتورها — جلوگیری از N+1.
        $tech->loadSum('invoices', 'company_share');

        return view('crm::tech-panel.wallet', [
            'technician' => $tech,
            'transactions' => $transactions,
            'stats' => $stats,
            'typeFilter' => $typeFilter,
        ]);
    }

    public function invoices(Request $request)
    {
        $tech = Auth::guard('tech')->user();

        $statusFilter = $request->query('status');
        $allowedStatus = ['draft', 'issued', 'paid', 'cancelled'];

        $base = Invoice::query()->where('technician_id', $tech->id);

        // آمار کلی روی همه فاکتورهای تکنسین (مستقل از فیلتر).
        $stats = [
            'count'         => (int) (clone $base)->count(),
            'total_sum'     => (int) (clone $base)->sum('total_amount'),
            'tech_share'    => (int) (clone $base)->sum('tech_share'),
            'company_share' => (int) (clone $base)->sum('company_share'),
        ];

        $query = (clone $base)->with(['order', 'customer']);
        if ($statusFilter && in_array($statusFilter, $allowedStatus, true)) {
            $query->where('status', $statusFilter);
        }

        $invoices = $query->latest('issued_at')->latest('id')->paginate(15)->withQueryString();

        return view('crm::tech-panel.invoices', [
            'technician' => $tech,
            'invoices' => $invoices,
            'stats' => $stats,
            'statusFilter' => $statusFilter,
        ]);
    }

    // ─── شارژ کیف‌پول از درگاه (هم‌ارز Tech_Payment پنل WP) ────────
    public function walletRecharge(ZibalService $zibal)
    {
        $tech = Auth::guard('tech')->user();

        return view('crm::tech-panel.wallet_recharge', [
            'technician' => $tech,
            'gatewayConfigured' => $zibal->isConfigured(),
        ]);
    }

    public function walletRechargeInitiate(Request $request, ZibalService $zibal)
    {
        $tech = Auth::guard('tech')->user();

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:500000', 'max:50000000'],
        ], [
            'amount.required' => 'مبلغ الزامی است.',
            'amount.min' => 'حداقل مبلغ شارژ ۵۰۰٬۰۰۰ تومان است.',
            'amount.max' => 'حداکثر مبلغ شارژ ۵۰٬۰۰۰٬۰۰۰ تومان است.',
        ]);

        if (! $zibal->isConfigured()) {
            return back()->with('error', 'درگاه پرداخت توسط ادمین تنظیم نشده است.');
        }

        $amount = (int) $validated['amount'];
        $callbackUrl = route('crm.payment.callback');
        $orderId = 'TWC-' . $tech->id . '-' . now()->format('YmdHis') . '-' . random_int(1000, 9999);

        $response = $zibal->request(
            amount: $amount,
            callbackUrl: $callbackUrl,
            orderId: $orderId,
            mobile: $tech->mobile,
            description: 'شارژ کیف‌پول تکنسین #' . $tech->id,
        );

        $payment = Payment::create([
            'technician_id' => $tech->id,
            'gateway' => 'zibal',
            'purpose' => 'wallet_charge',
            'amount' => $amount,
            'track_id' => $response['trackId'] ?? null,
            'status' => $response['success'] ? 'pending' : 'failed',
            'result_message' => $response['message'] ?? null,
            'gateway_response' => $response['raw'] ?? null,
            'requested_at' => now(),
        ]);

        if (! $response['success']) {
            return back()->with('error', $response['message'] ?? 'خطا در شروع پرداخت.');
        }

        return redirect()->away($response['paymentUrl']);
    }

    public function profile()
    {
        return view('crm::tech-panel.profile', [
            'technician' => Auth::guard('tech')->user(),
        ]);
    }

    /**
     * صفحهٔ آموزش تکنسین — لیست دسته‌ها با ویدیوهای فعال هرکدام،
     * بعلاوهٔ ویدیوهای بدون دسته در یک گروه مجزا.
     */
    public function training()
    {
        $tech = Auth::guard('tech')->user();

        // فقط دسته‌بندی‌ها — تکنسین اول دسته انتخاب می‌کند، سپس به
        // صفحهٔ ویدیوهای آن دسته می‌رود (UX دو‌مرحله‌ای).
        $categories = TrainingCategory::active()
            ->ordered()
            ->withCount(['videos as videos_count' => fn ($q) => $q->where('is_active', true)])
            ->get()
            ->filter(fn ($c) => $c->videos_count > 0)
            ->values();

        // ویدیوهای بدون دسته به‌عنوان دستهٔ مجازی «سایر» نمایش داده می‌شود
        $uncategorizedCount = TrainingVideo::active()->whereNull('category_id')->count();

        return view('crm::tech-panel.training', [
            'technician'        => $tech,
            'categories'        => $categories,
            'uncategorizedCount' => $uncategorizedCount,
        ]);
    }

    /** صفحهٔ نمایش ویدیوهای یک دسته. */
    public function trainingCategory(TrainingCategory $category)
    {
        $tech = Auth::guard('tech')->user();

        if (! $category->is_active) {
            abort(404);
        }

        $videos = $category->videos()->active()->ordered()->get();

        return view('crm::tech-panel.training-category', [
            'technician' => $tech,
            'category'   => $category,
            'videos'     => $videos,
        ]);
    }

    /** ویدیوهای بدون دسته (دستهٔ مجازی «سایر»). */
    public function trainingUncategorized()
    {
        $tech = Auth::guard('tech')->user();
        $videos = TrainingVideo::active()->whereNull('category_id')->ordered()->get();

        return view('crm::tech-panel.training-category', [
            'technician'  => $tech,
            'category'    => (object) ['name' => 'سایر', 'description' => null],
            'videos'      => $videos,
            'isVirtual'   => true,
        ]);
    }

    /**
     * صفحهٔ مشاهده تک ویدیو با پلیر و توضیحات کامل.
     */
    public function trainingShow(TrainingVideo $video)
    {
        if (! $video->is_active) {
            abort(404);
        }
        $video->load('category');

        return view('crm::tech-panel.training-show', [
            'technician' => Auth::guard('tech')->user(),
            'video'      => $video,
        ]);
    }

    /**
     * این متد قبلاً اطلاعات تماس (phone/phone_force/address/description)
     * تکنسین را به‌روزرسانی می‌کرد. طبق خواست عملیات، ویرایش این اطلاعات
     * از پنل تکنسین به‌طور کامل برداشته شد. این endpoint عمداً حفظ شده تا
     * route قبلی crash نکند، اما هر POST بدون اعمال تغییر برمی‌گردد.
     */
    public function updateProfile(Request $request)
    {
        return redirect()
            ->route('tech.profile')
            ->with('error', 'ویرایش اطلاعات تماس از پنل تکنسین مجاز نیست. برای تغییر، با پشتیبانی تماس بگیرید.');
    }

    /**
     * آپلود آواتار توسط تکنسین — فقط یک‌بار. بعد از اولین آپلود فیلد
     * img_personal مقدار می‌گیرد و این مسیر دیگر فایل جدید نمی‌پذیرد.
     * هدف: جلوگیری از تغییر مکرر عکس پروفایل (یا سواستفاده با
     * گذاشتن تصاویر نامناسب پشت سر هم).
     */
    public function uploadAvatar(Request $request)
    {
        $tech = Auth::guard('tech')->user();

        if (! empty($tech->img_personal)) {
            return back()->with('error', 'عکس پروفایل قبلاً ثبت شده و قابل تغییر نیست. برای تغییر با پشتیبانی تماس بگیرید.');
        }

        $validated = $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:3072',
        ]);

        $path = $request->file('avatar')->store('tech-avatars', 'public');
        $tech->forceFill(['img_personal' => $path])->save();

        return redirect()
            ->route('tech.profile')
            ->with('success', 'عکس پروفایل با موفقیت ثبت شد.');
    }

    public function updatePassword(Request $request)
    {
        $tech = Auth::guard('tech')->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'string', 'confirmed', Password::min(6)],
        ]);

        if (! Hash::check($validated['current_password'], $tech->password)) {
            return back()
                ->withErrors(['current_password' => 'رمز عبور فعلی صحیح نیست.'])
                ->onlyInput();
        }

        $tech->update(['password' => $validated['password']]);

        return redirect()
            ->route('tech.profile')
            ->with('success', 'رمز عبور با موفقیت تغییر کرد.');
    }
}
