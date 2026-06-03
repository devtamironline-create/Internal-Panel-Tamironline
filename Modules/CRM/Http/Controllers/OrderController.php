<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Concerns\ExportsListToFile;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Enums\SmsTrigger;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\OrderStatusLog;
use Modules\CRM\Models\Province;
use Modules\CRM\Models\Technician;
use Modules\CRM\Services\InvoiceService;
use Modules\CRM\Services\LegacyCloseService;
use Modules\CRM\Services\OrderSmsNotifier;

class OrderController extends Controller
{
    use ExportsListToFile;

    public function __construct(
        protected OrderSmsNotifier $smsNotifier,
        protected InvoiceService $invoiceService,
    ) {
    }

    public function index(Request $request)
    {
        $search = $request->string('q')->toString();
        $status = $request->string('status')->toString();
        // technician_id می‌تواند ID عددی یا sentinel «none» برای فیلتر «بدون تکنسین» باشد.
        $techParam = $request->string('technician_id')->toString();
        $technicianId = $techParam === 'none' ? 'none' : ((int) $techParam ?: null);
        $provinceId = $request->integer('province_id');
        $cityId = $request->integer('city_id');
        $brandId = $request->integer('brand_id');
        $deviceId = $request->integer('device_id');
        $orderType = $request->string('order_type')->toString();
        $introduction = $request->string('introduction')->toString();
        $hasInvoice = $request->string('has_invoice')->toString(); // '1' | '0' | ''
        $fromDate = $request->string('from_date')->toString();
        $toDate = $request->string('to_date')->toString();
        $visitFrom = $request->string('visit_from')->toString();
        $visitTo = $request->string('visit_to')->toString();

        // closure برای اعمال همهٔ فیلترهای غیر-status — هم در query اصلی
        // و هم در شمارش تب‌های وضعیت استفاده می‌شود.
        $applyNonStatusFilters = function ($q, bool $includeTech = true) use (
            $search, $technicianId, $provinceId, $cityId, $brandId, $deviceId,
            $orderType, $introduction, $hasInvoice, $fromDate, $toDate, $visitFrom, $visitTo
        ) {
            if ($search !== '') {
                $q->search($search);
            }
            if ($includeTech) {
                if ($technicianId === 'none') $q->whereNull('technician_id');
                elseif ($technicianId)        $q->where('technician_id', $technicianId);
            }
            if ($provinceId)          $q->where('province_id', $provinceId);
            if ($cityId)              $q->where('city_id', $cityId);
            if ($brandId)             $q->where('brand_id', $brandId);
            if ($deviceId)            $q->where('device_id', $deviceId);
            if ($orderType !== '')    $q->where('order_type', $orderType);
            if ($introduction !== '') $q->where('introduction', $introduction);
            if ($hasInvoice === '1')  $q->where('have_invoice', true);
            if ($hasInvoice === '0')  $q->where(function ($qq) {
                $qq->whereNull('have_invoice')->orWhere('have_invoice', false);
            });
            $fromG    = $this->jalaliToGregorian($fromDate);
            $toG      = $this->jalaliToGregorian($toDate);
            $visitFG  = $this->jalaliToGregorian($visitFrom);
            $visitTG  = $this->jalaliToGregorian($visitTo);
            if ($fromG)    $q->whereDate('created_at', '>=', $fromG);
            if ($toG)      $q->whereDate('created_at', '<=', $toG);
            if ($visitFG)  $q->whereDate('visit_scheduled_at', '>=', $visitFG);
            if ($visitTG)  $q->whereDate('visit_scheduled_at', '<=', $visitTG);
        };

        $query = Order::with(['customer', 'technician', 'brand', 'device', 'province', 'city']);
        $applyNonStatusFilters($query);

        // تب «بازگشت‌خورده» مجازی است: در WP سفارش‌های برگشت‌خورده
        // status=0 (New) دارند و فقط return_type ست می‌شود. پس به‌جای
        // فیلتر روی status، روی return_type فیلتر می‌کنیم.
        if ($status === \Modules\CRM\Enums\OrderStatus::Returned->value) {
            $query->whereNotNull('return_type');
        } elseif ($status !== '') {
            $query->where('status', $status);
        }

        // فیلتر لید/سفارش — پیش‌فرض: فقط سفارش‌های واقعی نشان داده
        // می‌شوند. با kind=lead فقط لیدها، با kind=all هر دو.
        $kind = $request->string('kind')->toString();
        if ($kind === 'lead') {
            $query->leads();
        } elseif ($kind !== 'all') {
            $query->realOrders();
        }

        // تعداد در صفحه — قابل تنظیم با ?per_page=. مقادیر مجاز محدود
        // می‌شوند تا کاربر نتواند با عدد خیلی بزرگ سرور را overload کند.
        $perPage = (int) $request->query('per_page', 50);
        if (! in_array($perPage, [25, 50, 100, 200], true)) {
            $perPage = 50;
        }

        $orders = $query->latest()->paginate($perPage)->withQueryString();

        // ─── شمارش تب‌های وضعیت با اعمال بقیهٔ فیلترها ───────────────
        $countQuery = Order::query();
        $applyNonStatusFilters($countQuery);
        $rawCounts = (clone $countQuery)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        $statusCounts = ['all' => array_sum($rawCounts)];
        foreach (\Modules\CRM\Enums\OrderStatus::cases() as $case) {
            $statusCounts[$case->value] = (int) ($rawCounts[$case->value] ?? 0);
        }

        // شمارش تب «بازگشت‌خورده» جدا — return_type IS NOT NULL
        $statusCounts[\Modules\CRM\Enums\OrderStatus::Returned->value] =
            (clone $countQuery)->whereNotNull('return_type')->count();

        // شمارش تب «بدون تکنسین» — همان فیلترها به‌جز technician_id
        $noTechCountQuery = Order::query();
        $applyNonStatusFilters($noTechCountQuery, false);
        $statusCounts['no_tech'] = $noTechCountQuery->whereNull('technician_id')->count();

        // داده‌های کمکی برای dropdown ها
        $technicians = Technician::active()->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'firstname_tech']);
        $provinces = Province::ordered()->get(['id', 'name']);
        // همهٔ شهرها را با province_id می‌فرستیم تا JS بتواند بدون reload
        // فیلتر کند. اگر تعداد شهرها خیلی زیاد شد، می‌توان به AJAX
        // تنزل داد، ولی فعلاً مجموع چند صد شهر ایران مشکلی ندارد.
        $cities = \Modules\CRM\Models\City::ordered()->get(['id', 'name', 'province_id']);
        $brands = \Modules\CRM\Models\Brand::ordered()->get(['id', 'name']);
        $devices = \Modules\CRM\Models\Device::ordered()->get(['id', 'name']);
        $introductionList = \Modules\CRM\Models\CrmSetting::getJson('wp.introductionList', []) ?: [];
        if (! is_array($introductionList)) {
            $introductionList = [];
        }

        // شمارش لیدها و سفارش‌ها برای نمایش روی تب‌های kind
        $leadCount  = (clone $query->getModel()->newQuery())->leads()->count();
        $orderCount = (clone $query->getModel()->newQuery())->realOrders()->count();

        return view('crm::orders.index', compact(
            'orders', 'technicians', 'provinces', 'cities', 'brands', 'devices', 'introductionList',
            'search', 'status', 'technicianId', 'provinceId', 'cityId', 'brandId', 'deviceId',
            'orderType', 'introduction', 'hasInvoice', 'fromDate', 'toDate', 'visitFrom', 'visitTo',
            'statusCounts', 'perPage', 'kind', 'leadCount', 'orderCount'
        ));
    }

    /** خروجی Excel/CSV از لیست سفارش‌ها با همان فیلترهای صفحه. */
    public function export(Request $request, string $format)
    {
        $query = $this->buildIndexQuery($request);

        $headers = [
            'کد سفارش', 'نوع', 'نحوه آشنایی', 'مشتری', 'موبایل', 'دستگاه', 'برند',
            'تکنسین', 'استان', 'شهر', 'وضعیت', 'مبلغ نهایی', 'تاریخ ثبت',
        ];
        $rows = function () use ($query) {
            foreach ($query->with(['customer', 'technician', 'brand', 'device', 'province', 'city'])->lazy(500) as $o) {
                yield [
                    $o->order_code,
                    $o->order_type === 'service' ? 'نصب' : ($o->order_type === 'repair' ? 'تعمیر' : '—'),
                    $o->introduction ?: '—',
                    $o->customer_name ?: $o->customer?->display_name,
                    $o->customer_mobile,
                    $o->device?->name,
                    $o->brand?->name,
                    $o->technician
                        ? trim($o->technician->firstname_tech ?: ($o->technician->first_name . ' ' . $o->technician->last_name))
                        : null,
                    $o->province?->name,
                    $o->city?->name,
                    $o->status instanceof OrderStatus ? $o->status->label() : (string) $o->status,
                    $o->total_invoice ?? $o->final_price,
                    $o->created_at,
                ];
            }
        };

        return $this->streamSpreadsheet('crm-orders-' . date('Ymd-His'), $format, $headers, $rows);
    }

    /** هم در index هم در export استفاده می‌شود — همان فیلترهای صفحه. */
    protected function buildIndexQuery(Request $request)
    {
        $query = Order::query()->latest();

        if ($s = trim((string) $request->string('q'))) $query->search($s);
        $techParam = $request->string('technician_id')->toString();
        if ($techParam === 'none')              $query->whereNull('technician_id');
        elseif ($v = (int) $techParam)          $query->where('technician_id', $v);
        if ($v = $request->integer('province_id'))   $query->where('province_id', $v);
        if ($v = $request->integer('city_id'))       $query->where('city_id', $v);
        if ($v = $request->integer('brand_id'))      $query->where('brand_id', $v);
        if ($v = $request->integer('device_id'))     $query->where('device_id', $v);
        if ($v = trim((string) $request->string('order_type')))   $query->where('order_type', $v);
        if ($v = trim((string) $request->string('introduction'))) $query->where('introduction', $v);
        if ($v = trim((string) $request->string('status')))       $query->where('status', $v);

        $hasInvoice = (string) $request->string('has_invoice');
        if ($hasInvoice === '1') $query->where('have_invoice', true);
        if ($hasInvoice === '0') $query->where(function ($q) {
            $q->whereNull('have_invoice')->orWhere('have_invoice', false);
        });

        foreach ([
            'from_date'  => ['col' => 'created_at',           'op' => '>='],
            'to_date'    => ['col' => 'created_at',           'op' => '<='],
            'visit_from' => ['col' => 'visit_scheduled_at',   'op' => '>='],
            'visit_to'   => ['col' => 'visit_scheduled_at',   'op' => '<='],
        ] as $param => $info) {
            $g = $this->jalaliToGregorian((string) $request->string($param));
            if ($g) $query->whereDate($info['col'], $info['op'], $g);
        }

        return $query;
    }

    public function create(Request $request)
    {
        // اگر customer_id از مسیر جزئیات مشتری اومد، Livewire آن را
        // پیش‌گزینه می‌کند. بقیهٔ لیست‌ها (brands/devices/...) داخل خود
        // OrderWizard component گرفته می‌شوند.
        $customerId = $request->integer('customer_id');
        $customer = $customerId ? Customer::find($customerId) : null;

        return view('crm::orders.create', [
            'customer' => $customer,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateOrder($request);

        $order = DB::transaction(function () use ($validated) {
            $customer = Customer::findOrFail($validated['customer_id']);

            $order = Order::create([
                'order_code' => Order::generateOrderCode(),
                'customer_id' => $customer->id,
                'brand_id' => $validated['brand_id'] ?? null,
                'device_id' => $validated['device_id'] ?? null,
                // snapshot مشتری در زمان ثبت سفارش (مثل meta‌های سفارش در WP)
                'customer_name' => $customer->display_name,
                'customer_mobile' => $customer->mobile,
                'customer_phone' => $customer->phone,
                // آدرس به ازای هر سفارش جداگانه ثبت می‌شود (مثل WP)
                'province_id' => $validated['province_id'] ?? null,
                'city_id' => $validated['city_id'] ?? null,
                'address' => $validated['address'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
                'problem_title' => $validated['problem_title'] ?? null,
                'problem_description' => $validated['problem_description'] ?? null,
                'visit_scheduled_at' => $validated['visit_scheduled_at'] ?? null,
                'estimated_price' => $validated['estimated_price'] ?? null,
                'deposit' => $validated['deposit'] ?? 0,
                'status' => $validated['status'] ?? OrderStatus::New->value,
                'created_by' => auth()->id(),
                'notes' => $validated['notes'] ?? null,
            ]);

            OrderStatusLog::create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => $order->status instanceof OrderStatus ? $order->status->value : $order->status,
                'note' => 'ثبت اولیه سفارش',
                'changed_by' => auth()->id(),
                'created_at' => now(),
            ]);

            return $order;
        });

        $this->smsNotifier->notify($order, SmsTrigger::OrderCreated);

        return redirect()->route('crm.orders.show', $order)
            ->with('success', 'سفارش ثبت شد: ' . $order->order_code);
    }

    /**
     * لیست سفارش‌های تکمیل‌شده در ۲ هفتهٔ گذشته که فاکتور فعال ندارند.
     * برای جبران سفارش‌هایی که به هر دلیل خودکار فاکتور برایشان ساخته
     * نشده — اپراتور می‌تواند با یک کلیک فاکتور را صادر کند.
     */
    public function missingInvoices(Request $request)
    {
        $sinceDays = (int) $request->query('days', 14);
        if ($sinceDays <= 0 || $sinceDays > 90) $sinceDays = 14;
        $since = now()->subDays($sinceDays);

        // ID همهٔ سفارش‌هایی که فاکتور فعال (superseded نباشد) دارند —
        // اینها از لیست خارج می‌شوند.
        $orderIdsWithActiveInvoice = \Modules\CRM\Models\Invoice::query()
            ->whereNotNull('order_id')
            ->pluck('order_id')
            ->unique();

        $orders = Order::query()
            ->with([
                'customer:id,first_name,mobile',
                'technician:id,first_name,last_name,firstname_tech,mobile',
                'brand:id,name', 'device:id,name',
            ])
            ->where('status', OrderStatus::Completed->value)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $since)
            ->where(function ($q) {
                $q->where('is_legacy_closed', false)
                    ->orWhereNull('is_legacy_closed');
            })
            ->whereNotIn('id', $orderIdsWithActiveInvoice)
            ->where(function ($q) {
                $q->where('save_as_draft', false)
                    ->orWhereNull('save_as_draft');
            })
            ->orderByDesc('completed_at')
            ->paginate(50)
            ->withQueryString();

        return view('crm::orders.missing-invoices', [
            'orders' => $orders,
            'sinceDays' => $sinceDays,
            'since' => $since,
        ]);
    }

    public function show(Order $order)
    {
        $order->load([
            'customer', 'brand', 'device', 'technician', 'province', 'city',
            'creator', 'items', 'statusLogs.changer',
        ]);

        $suggestions = collect();
        if (auth()->user()?->can('view-tech-suggestions') && ! $order->technician_id) {
            $suggestions = app(\Modules\CRM\Services\TechnicianSuggestionService::class)
                ->suggestForOrder($order, 5);
        }

        // فاکتور فعال این سفارش (اگر وجود دارد) — برای نمایش دکمه «صدور فاکتور»
        // در حالت‌هایی که سفارش Completed است ولی فاکتور ندارد.
        $activeInvoice = \Modules\CRM\Models\Invoice::where('order_id', $order->id)->first();

        // فاکتورهای قبلی (superseded) برای تاریخچه — این‌ها به‌خاطر برگشت
        // سفارش و تکمیل مجدد به‌وجود آمده‌اند و در DB موجودند.
        $supersededInvoices = \Modules\CRM\Models\Invoice::withoutGlobalScope('active')
            ->where('order_id', $order->id)
            ->whereNotNull('superseded_at')
            ->orderByDesc('id')
            ->get();

        // فاکتورهای آسیب‌دیده (in_wallet=true ولی wallet_tx ندارند) —
        // نتیجهٔ باگ قدیمی که wallet transactions را حذف می‌کرد.
        // اگر چنین مواردی پیدا شد، دکمهٔ بازسازی روی صفحه ظاهر می‌شود.
        $affectedInvoiceIds = collect();
        if ($supersededInvoices->isNotEmpty()) {
            $candidateIds = $supersededInvoices->where('in_wallet', true)->pluck('id');
            $idsWithTxs = \Modules\CRM\Models\WalletTransaction::whereIn('invoice_id', $candidateIds)
                ->pluck('invoice_id')->unique();
            $affectedInvoiceIds = $candidateIds->reject(fn($id) => $idsWithTxs->contains($id))->values();
        }

        // ردیف‌های بازسازی‌شدهٔ قبلی (با مارکر [بازسازی] در note) — برای
        // نمایش دکمهٔ «حذف بازسازی».
        $restoredCount = \Modules\CRM\Models\WalletTransaction::where('order_id', $order->id)
            ->where('note', 'like', '[بازسازی]%')
            ->count();

        // سفارش‌های قبلی همین مشتری — برای دکمهٔ سریع «سوابق مشتری» در صفحهٔ
        // جزئیات. حداکثر ۳۰ سفارش اخیر، خود سفارش جاری حذف می‌شود.
        $customerOrders = collect();
        if ($order->customer_id) {
            $customerOrders = Order::query()
                ->with(['brand:id,name', 'device:id,name', 'technician:id,first_name,last_name,firstname_tech'])
                ->where('customer_id', $order->customer_id)
                ->whereKeyNot($order->id)
                ->latest('created_at')
                ->limit(30)
                ->get(['id', 'order_code', 'status', 'brand_id', 'device_id', 'technician_id', 'created_at', 'price_customer', 'completed_at']);
        }

        return view('crm::orders.show', [
            'order' => $order,
            'technicians' => Technician::active()->ready()->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'firstname_tech', 'mobile']),
            'statuses' => OrderStatus::options(),
            'suggestions' => $suggestions,
            'activeInvoice' => $activeInvoice,
            'customerOrders' => $customerOrders,
            'supersededInvoices' => $supersededInvoices,
            'affectedInvoiceIds' => $affectedInvoiceIds,
            'restoredCount' => $restoredCount,
        ]);
    }

    /**
     * بازسازی wallet transactions برای فاکتورهای superseded که در نسخهٔ
     * قدیمیِ InvoiceService حذف شده بودند.
     *
     * فقط یک ردیف **مثبت** (+company_share) با مارکر `[بازسازی]` در
     * توضیحات ثبت می‌شود تا اپراتور به‌راحتی بتواند بعداً اگر اشتباه
     * بود، با دکمهٔ «حذف بازسازی» همان را پاک کند.
     */
    public function restoreWalletHistory(Order $order)
    {
        return DB::transaction(function () use ($order) {
            $supersededInvoices = \Modules\CRM\Models\Invoice::withoutGlobalScope('active')
                ->where('order_id', $order->id)
                ->whereNotNull('superseded_at')
                ->where('in_wallet', true)
                ->get();

            $restored = 0;
            foreach ($supersededInvoices as $inv) {
                if (\Modules\CRM\Models\WalletTransaction::where('invoice_id', $inv->id)->exists()) {
                    continue;
                }
                $techId = (int) $inv->technician_id;
                $companyShare = (int) $inv->company_share;
                if ($techId <= 0 || $companyShare <= 0) {
                    continue;
                }

                $last = (int) (\Modules\CRM\Models\WalletTransaction::where('technician_id', $techId)
                    ->orderByDesc('id')->value('balance_after') ?? 0);

                \Modules\CRM\Models\WalletTransaction::create([
                    'technician_id' => $techId,
                    'order_id' => $order->id,
                    'invoice_id' => $inv->id,
                    'wp_id' => null,
                    'type' => \Modules\CRM\Enums\WalletTxType::Commission->value,
                    'amount' => $companyShare,
                    'balance_after' => $last + $companyShare,
                    'note' => '[بازسازی] بازگشت سهم شرکت از فاکتور بایگانی‌شده ' . $inv->invoice_code,
                    'created_by' => auth()->id(),
                ]);

                \Modules\CRM\Models\Technician::where('id', $techId)
                    ->update(['wallet_balance' => $last + $companyShare]);

                $restored++;
            }

            if ($restored === 0) {
                return back()->with('error', 'هیچ فاکتور آسیب‌دیده‌ای برای بازسازی پیدا نشد.');
            }

            return back()->with('success', "تاریخچهٔ wallet برای {$restored} فاکتور بایگانی‌شده بازسازی شد (به‌صورت تراکنش مثبت).");
        });
    }

    /**
     * حذف ردیف‌های بازسازی‌شده — همان‌هایی که با `[بازسازی]` در note
     * شناسایی می‌شوند. مانده تکنسین به‌صورت دقیق برمی‌گردد (با کم
     * کردن مقدار تراکنش حذف‌شده).
     */
    public function removeRestoredHistory(Order $order)
    {
        return DB::transaction(function () use ($order) {
            $rows = \Modules\CRM\Models\WalletTransaction::where('order_id', $order->id)
                ->where('note', 'like', '[بازسازی]%')
                ->orderByDesc('id')
                ->get();

            if ($rows->isEmpty()) {
                return back()->with('error', 'هیچ ردیف بازسازی‌شده‌ای برای حذف پیدا نشد.');
            }

            $removed = 0;
            foreach ($rows as $tx) {
                // مانده تکنسین را به‌صورت معکوس مقدار این تراکنش به‌روز کن
                $current = (int) (\Modules\CRM\Models\Technician::where('id', $tx->technician_id)
                    ->value('wallet_balance') ?? 0);
                \Modules\CRM\Models\Technician::where('id', $tx->technician_id)
                    ->update(['wallet_balance' => $current - (int) $tx->amount]);
                $tx->delete();
                $removed++;
            }

            return back()->with('success', "{$removed} ردیف بازسازی حذف شد و مانده تکنسین برگشت داده شد.");
        });
    }

    /**
     * بستن دستی سفارش بر اساس لاگ پنل WP (Legacy Close).
     *
     * این متد دکمهٔ «بستن از روی لاگ قدیمی» را در صفحهٔ سفارش پشتیبانی
     * می‌کند. هیچ Invoice یا WalletTransaction ساخته نمی‌شود — فقط
     * status=Completed + فیلدهای مالی از لاگ.
     *
     * شرایط نمایش دکمه (در view): status != Completed && !is_legacy_closed.
     * controller هم همان شرایط را double-check می‌کند.
     */
    public function retroClose(Order $order, LegacyCloseService $legacy)
    {
        if ($order->status === OrderStatus::Completed && ! $order->is_legacy_closed) {
            return back()->with('error', 'این سفارش از قبل به‌صورت عادی Completed شده — این مسیر فقط برای سفارش‌های بدون فاکتور است.');
        }

        $extracted = $legacy->extractFromOrder($order);
        if (! $extracted) {
            return back()->with('error', 'لاگ «انجام کار» با اعداد مالی در این سفارش پیدا نشد.');
        }

        $legacy->applyToOrder($order, $extracted);

        return back()->with('success',
            'سفارش بسته شد (legacy). سهم تکنسین: ' . number_format($extracted['tech_share']) .
            ' / سهم شرکت: ' . number_format($extracted['company_share']) .
            ' — هیچ فاکتور یا تراکنش کیف‌پولی ساخته نشد.'
        );
    }

    public function edit(Order $order)
    {
        $order->load(['customer']);

        $introductionList = \Modules\CRM\Models\CrmSetting::getJson('wp.introductionList', []);

        return view('crm::orders.edit', [
            'order' => $order,
            'brands' => Brand::active()->ordered()->get(['id', 'name']),
            'devices' => Device::active()->ordered()->get(['id', 'name']),
            'provinces' => Province::ordered()->get(['id', 'name']),
            'cities' => $order->province_id
                ? City::where('province_id', $order->province_id)->ordered()->get(['id', 'name'])
                : collect(),
            'technicians' => Technician::orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'firstname_tech', 'mobile', 'wp_id']),
            'introductionList' => is_array($introductionList) ? array_values(array_filter(array_map('strval', $introductionList))) : [],
        ]);
    }

    public function update(Request $request, Order $order)
    {
        $validated = $this->validateOrder($request, updating: true);

        // ── ویرایش اطلاعات مشتری (روی پروفایل Customer هم اعمال می‌شود).
        // اگر شماره موبایل جدید با مشتری دیگری تداخل دارد، خطا برگردان.
        $newCustomerName   = $validated['customer_name']   ?? null;
        $newCustomerMobile = $validated['customer_mobile'] ?? null;

        $customer = $order->customer;
        if ($customer && $newCustomerMobile && $newCustomerMobile !== $customer->mobile) {
            $existing = \Modules\CRM\Models\Customer::where('mobile', $newCustomerMobile)
                ->where('id', '!=', $customer->id)->first();
            if ($existing) {
                return back()
                    ->withInput()
                    ->withErrors(['customer_mobile' => 'این شماره موبایل قبلاً برای مشتری دیگری ثبت شده. برای ادغام مشتری‌ها به‌صورت دستی اقدام کنید.']);
            }
        }

        $order->update([
            'brand_id' => $validated['brand_id'] ?? null,
            'device_id' => $validated['device_id'] ?? null,
            'province_id' => $validated['province_id'] ?? null,
            'city_id' => $validated['city_id'] ?? null,
            'address' => $validated['address'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'problem_title' => $validated['problem_title'] ?? null,
            'problem_description' => $validated['problem_description'] ?? null,
            'visit_scheduled_at' => $validated['visit_scheduled_at'] ?? null,
            'estimated_price' => $validated['estimated_price'] ?? null,
            'final_price' => $validated['final_price'] ?? null,
            'deposit' => $validated['deposit'] ?? 0,
            'notes' => $validated['notes'] ?? null,
            'customer_name' => $newCustomerName ?? $order->customer_name,
            'customer_mobile' => $newCustomerMobile ?? $order->customer_mobile,
            // فیلدهای اضافه‌شده — هم‌سو با OrderWizard هنگام ثبت
            'introduction' => $validated['introduction'] ?? $order->introduction,
            'order_type' => $validated['order_type'] ?? $order->order_type,
            'technician_id' => array_key_exists('technician_id', $validated)
                ? ($validated['technician_id'] ?: null)
                : $order->technician_id,
            'subscription' => array_key_exists('subscription', $validated)
                ? ($validated['subscription'] ?: null)
                : $order->subscription,
        ]);

        // ── اعمال تغییر روی Customer (observer در Customer::booted خودکار
        //    به WP push می‌کند). فقط اگر مقدار فرق داشت بزن تا event بی‌دلیل
        //    شلیک نشود.
        if ($customer) {
            $dirty = [];
            if ($newCustomerName !== null && $newCustomerName !== $customer->first_name) {
                $dirty['first_name'] = $newCustomerName;
            }
            if ($newCustomerMobile !== null && $newCustomerMobile !== $customer->mobile) {
                $dirty['mobile'] = $newCustomerMobile;
            }
            $newCustomerPhone = $validated['customer_phone'] ?? null;
            if ($newCustomerPhone !== null && $newCustomerPhone !== $customer->phone) {
                $dirty['phone'] = $newCustomerPhone;
            }
            if ($dirty) {
                $customer->update($dirty);
            }
        }

        return redirect()->route('crm.orders.show', $order)->with('success', 'سفارش ویرایش شد.');
    }

    public function destroy(Order $order)
    {
        $order->delete();

        return redirect()->route('crm.orders.index')->with('success', 'سفارش حذف شد.');
    }

    // ───────────── تخصیص تکنسین ─────────────────────────────────
    /**
     * هم‌ارز add_order/assign پنل WP: تخصیص تکنسین وضعیت سفارش را
     * تغییر نمی‌دهد. سفارش روی همان وضعیت قبلی (معمولاً «جدید») می‌ماند
     * تا تکنسین با مشتری تماس بگیرد و خودش وضعیت را به «هماهنگ شده» یا
     * «باز شده» تغییر دهد. در نتیجه پیامک «تخصیص تکنسین» به مشتری اینجا
     * ارسال نمی‌شود — هنگام تغییر به Coordinated توسط تکنسین فایر می‌شود.
     */
    public function assign(Request $request, Order $order)
    {
        $validated = $request->validate([
            'technician_id' => 'required|exists:crm_technicians,id',
        ]);

        $previousStatusEnum = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);
        $previousStatus = $previousStatusEnum?->value ?? (string) $order->status;

        // اگر سفارش در وضعیت نهایی است (مثل Declined که تکنسین قبلی رد
        // کرده، یا Cancelled/Completed/Returned/Transit)، تخصیص مجدد یعنی
        // ادمین می‌خواهد سفارش را با تکنسین جدید از سر بگیرد. وضعیت را
        // به New برمی‌گردانیم تا در پنل تکنسین جدید قابل دیدن باشد.
        $shouldResetStatus = $previousStatusEnum?->isFinal() ?? false;
        $newStatus = $shouldResetStatus ? OrderStatus::New->value : $previousStatus;

        // technician_wp_id را هم همگام‌سازی می‌کنیم تا inbound resolution
        // بعدی روی wp_id تکنسین جدید عمل کند.
        $newTech = \Modules\CRM\Models\Technician::find($validated['technician_id']);
        $newTechWpId = $newTech?->wp_id;

        $order->update([
            'technician_id' => $validated['technician_id'],
            'technician_wp_id' => $newTechWpId,
            'assigned_at' => now(),
            'status' => $newStatus,
        ]);

        OrderStatusLog::create([
            'order_id' => $order->id,
            'from_status' => $previousStatus,
            'to_status' => $newStatus,
            'note' => $shouldResetStatus
                ? 'تخصیص مجدد — وضعیت از ' . ($previousStatusEnum?->label() ?? $previousStatus) . ' به جدید بازگردانده شد'
                : 'تخصیص تکنسین (وضعیت تغییر نکرد — منتظر تماس تکنسین با مشتری)',
            'changed_by' => auth()->id(),
            'created_at' => now(),
        ]);

        // فقط به تکنسین اطلاع بده تا با مشتری تماس بگیرد.
        // پیامک «هماهنگی» به مشتری بعد از تغییر دستی تکنسین به Coordinated
        // ارسال خواهد شد.
        $order->refresh()->load('technician');
        $this->smsNotifier->notify($order, SmsTrigger::OrderAssignedTech);

        return back()->with('success', 'تکنسین تخصیص داده شد. منتظر تماس تکنسین با مشتری بمانید.');
    }

    /**
     * تبدیل یک لید به سفارش واقعی.
     *
     * فلگ is_lead پاک می‌شود، dlیل/یادداشت لید حذف می‌شود، order_code
     * جدیدی صادر می‌شود تا با سفارش‌های موجود قابل تفکیک باشد، و در
     * تاریخچهٔ سفارش یک لاگ ثبت می‌شود. وضعیت به «جدید» می‌رود تا
     * مثل سفارش تازه‌ای رفتار شود (تخصیص تکنسین بعداً انجام می‌شود).
     */
    public function convertFromLead(Order $order)
    {
        if (! $order->is_lead) {
            return back()->with('error', 'این سفارش از قبل لید نیست.');
        }

        $oldCode = $order->order_code;
        $newCode = Order::generateOrderCode();

        DB::transaction(function () use ($order, $newCode) {
            $order->update([
                'is_lead'        => false,
                'lead_reason_id' => null,
                'lead_notes'     => null,
                'order_code'     => $newCode,
                'status'         => \Modules\CRM\Enums\OrderStatus::New->value,
            ]);

            \Modules\CRM\Models\OrderStatusLog::create([
                'order_id'    => $order->id,
                'from_status' => null,
                'to_status'   => \Modules\CRM\Enums\OrderStatus::New->value,
                'note'        => 'تبدیل لید به سفارش — کد قبلی: ' . $order->getOriginal('order_code'),
                'changed_by'  => auth()->id(),
                'created_at'  => now(),
            ]);
        });

        // SMS «سفارش ایجاد شد» — حالا که از لید به سفارش تبدیل شده،
        // مشتری اطلاع پیامکی می‌گیرد.
        try {
            app(\Modules\CRM\Services\OrderSmsNotifier::class)
                ->notify($order->refresh(), \Modules\CRM\Enums\SmsTrigger::OrderCreated);
        } catch (\Throwable $e) {
            Log::warning('convertFromLead SMS failed', ['order' => $order->id, 'err' => $e->getMessage()]);
        }

        return back()->with('success', "لید با موفقیت به سفارش تبدیل شد. کد جدید: {$newCode} (قبلی: {$oldCode})");
    }

    public function unassign(Order $order)
    {
        if (! $order->technician_id) {
            return back();
        }

        $previousStatus = $order->status instanceof OrderStatus ? $order->status->value : $order->status;

        $order->update([
            'technician_id' => null,
            'status' => OrderStatus::New->value,
            'assigned_at' => null,
        ]);

        OrderStatusLog::create([
            'order_id' => $order->id,
            'from_status' => $previousStatus,
            'to_status' => OrderStatus::New->value,
            'note' => 'لغو تخصیص تکنسین',
            'changed_by' => auth()->id(),
            'created_at' => now(),
        ]);

        return back()->with('success', 'تکنسین از این سفارش برداشته شد.');
    }

    // ───────────── منبع داده سفارش (per-order source of truth) ──
    public function updateSourceOfTruth(Request $request, Order $order)
    {
        $validated = $request->validate([
            'source_of_truth' => ['required', 'in:auto,panel,crm'],
        ]);

        $order->update(['source_of_truth' => $validated['source_of_truth']]);

        $label = Order::SOURCE_OF_TRUTH_OPTIONS[$validated['source_of_truth']] ?? $validated['source_of_truth'];
        return back()->with('success', 'منبع داده سفارش تغییر کرد: ' . $label);
    }

    // ───────────── یادداشت‌های اپراتور ──────────────────────────
    public function storeNote(Request $request, Order $order)
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'min:3', 'max:2000'],
        ], [
            'content.required' => 'متن یادداشت الزامی است.',
            'content.min' => 'یادداشت باید حداقل ۳ کاراکتر باشد.',
            'content.max' => 'یادداشت حداکثر ۲۰۰۰ کاراکتر.',
        ]);

        $order->adminNotes()->create([
            'user_id' => auth()->id(),
            'content' => trim($validated['content']),
        ]);

        return back()->with('success', 'یادداشت ثبت شد.');
    }

    public function destroyNote(Order $order, int $note)
    {
        $note = \Modules\CRM\Models\OrderAdminNote::where('order_id', $order->id)
            ->where('id', $note)
            ->firstOrFail();

        // فقط نویسنده یا ادمین کامل می‌تواند حذف کند
        if ($note->user_id !== auth()->id() && ! auth()->user()->can('manage-permissions')) {
            abort(403, 'فقط نویسنده یا ادمین می‌تواند یادداشت را حذف کند.');
        }

        $note->delete();
        return back()->with('success', 'یادداشت حذف شد.');
    }

    // ───────────── تغییر وضعیت ─────────────────────────────────
    public function changeStatus(Request $request, Order $order)
    {
        $rules = [
            'status' => ['required', 'string'],
            'note' => 'nullable|string|max:2000',
        ];

        $statusValue = (string) $request->input('status');
        $isCompleting = $statusValue === OrderStatus::Completed->value;
        $isCancelling = in_array($statusValue, [OrderStatus::Cancelled->value, OrderStatus::Declined->value], true);

        // برای کنسل/رد، دلیل اجباری
        if ($isCancelling) {
            $rules['note'] = 'required|string|min:5|max:2000';
        }

        // برای تکمیل، فیلدهای فاکتور — هم‌سو با Tech/DashboardController
        if ($isCompleting) {
            $rules += [
                'price_customer' => 'nullable|integer|min:0',
                'cost_price' => 'nullable|integer|min:0',
                'hire' => 'nullable|integer|min:0',
                'transportation' => 'nullable|integer|min:0',
                'discount' => 'nullable|integer|min:0',
                'invoice_descripotion' => 'required|string|min:5|max:2000',
                'save_as_draft' => 'nullable|boolean',
                'device_img1' => 'nullable|image|max:10240',
            ];
        }

        $validated = $request->validate($rules, [
            'note.required' => 'برای کنسل/رد سفارش، دلیل الزامی است.',
            'note.min' => 'دلیل باید حداقل ۵ کاراکتر باشد.',
            'invoice_descripotion.required' => 'توضیحات فاکتور (متن قابل ارسال به مشتری) اجباری است.',
            'invoice_descripotion.min' => 'توضیحات فاکتور حداقل ۵ کاراکتر.',
        ]);

        $newStatus = OrderStatus::tryFrom($validated['status']);
        if (! $newStatus) {
            return back()->with('error', 'وضعیت نامعتبر.');
        }

        $previousStatus = $order->status instanceof OrderStatus ? $order->status->value : $order->status;
        if ($previousStatus === $newStatus->value) {
            return back()->with('error', 'وضعیت قبلاً همین بوده.');
        }

        // اعمال قوانین گذار وضعیت — هم‌ارز show_order.php در WP. وضعیت‌های
        // نهایی (Cancelled/Completed/Transit/Declined) قفل هستند و فقط با
        // «بازگشت سفارش» می‌توان از آن‌ها خارج شد.
        $currentEnum = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom($previousStatus);
        $allowed = $currentEnum?->allowedTransitions() ?? [];
        if (! in_array($newStatus, $allowed, true)) {
            $msg = $currentEnum?->isFinal()
                ? 'این سفارش در وضعیت نهایی است. برای تغییر از «بازگشت سفارش» استفاده کنید.'
                : 'گذار از «' . ($currentEnum?->label() ?? $previousStatus) . '» به «' . $newStatus->label() . '» مجاز نیست.';
            return back()->with('error', $msg);
        }

        $updates = ['status' => $newStatus->value];

        if ($newStatus === OrderStatus::Completed) {
            $updates['completed_at'] = now();

            // فیلدهای فاکتور — مثل تکنسین
            foreach (['price_customer', 'cost_price', 'hire', 'transportation', 'discount'] as $field) {
                if (array_key_exists($field, $validated) && $validated[$field] !== null) {
                    $updates[$field] = (int) $validated[$field];
                }
            }
            if (filled($validated['invoice_descripotion'] ?? null)) {
                $updates['invoice_descripotion'] = $validated['invoice_descripotion'];
            }
            // total_invoice = max(0, price_customer - cost_price) — هم‌سو با tech
            $effPC = (int) ($updates['price_customer'] ?? $order->price_customer ?? 0);
            $effCP = (int) ($updates['cost_price'] ?? $order->cost_price ?? 0);
            $updates['total_invoice'] = max(0, $effPC - $effCP);
            $updates['save_as_draft'] = (bool) ($validated['save_as_draft'] ?? false);

            if ($request->hasFile('device_img1')) {
                $path = $request->file('device_img1')->store("crm/orders/{$order->id}", 'public');
                $updates['device_img1'] = $path;
            }
        }
        if ($isCancelling) {
            $updates['cancel_reason'] = $validated['note'] ?? null;
        }

        $order->update($updates);

        OrderStatusLog::create([
            'order_id' => $order->id,
            'from_status' => $previousStatus,
            'to_status' => $newStatus->value,
            'note' => $validated['note'] ?? null,
            'changed_by' => auth()->id(),
            'created_at' => now(),
        ]);

        // تولید خودکار فاکتور در تکمیل سفارش (idempotent) — مگر اینکه پیش‌نویس باشد
        if ($newStatus === OrderStatus::Completed && empty($updates['save_as_draft'])) {
            $this->invoiceService->generateForOrder($order->refresh(), auth()->id(), true);
        }

        // اگر این وضعیت جدید قالب SMS دارد، خودکار ارسال کن — مگر
        // تکمیل مجدد سفارش بازگشتی (return_type != null)؛ در این حالت
        // مشتری قبلاً اطلاع داشته و نباید دوباره پیامک فاکتور بگیرد.
        $skipForReturned = $newStatus === OrderStatus::Completed && ! is_null($order->return_type);
        if (! $skipForReturned && $trigger = SmsTrigger::fromOrderStatus($newStatus)) {
            $this->smsNotifier->notify($order->refresh(), $trigger);
        }

        return back()->with('success', 'وضعیت به "' . $newStatus->label() . '" تغییر کرد.');
    }

    /**
     * بازگشت سفارش — هم‌ارز return_order در libs/order.php (WP CRM).
     *
     * - return_type ∈ {1=برگشت انجام شده, 2=برگشت کنسل شده}
     * - status به New (WP 0) برمی‌گردد
     * - status_internal_order و qc_status پاک می‌شوند
     * - یک snapshot از وضعیت مالی فعلی به آرایهٔ log_return پوش می‌شود
     * - یک رویداد به آرایهٔ order_description_content (لاگ پنل قدیمی) اضافه
     *   می‌شود تا با جریان WP سازگار بماند
     * - یک ردیف هم در crm_order_status_logs ثبت می‌شود تا در «تاریخچهٔ
     *   پنل جدید» دیده شود
     */
    public function returnOrder(Request $request, Order $order)
    {
        // گارد: بازگشت سفارش فقط روی وضعیت‌های نهایی مجاز است (هم‌ارز
        // returnOrderStatus در WP CRM که فقط بعد از تکمیل/کنسل قابل
        // اجراست). برای سفارش جریانی، باید ابتدا با تغییر وضعیت اقدام
        // شود نه «بازگشت».
        $currentStatus = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        if (! $currentStatus || ! $currentStatus->isFinal()) {
            return back()->with('error', 'بازگشت سفارش فقط روی سفارش‌های نهایی (انجام کار/کنسل/رد/ایاب و ذهاب) مجاز است.');
        }

        $validated = $request->validate([
            'return_type' => ['required', 'in:1,2'],
            'return_description' => ['required', 'string', 'max:2000'],
        ], [
            'return_type.required' => 'لطفاً نوع بازگشت را انتخاب کنید.',
            'return_type.in' => 'نوع بازگشت معتبر نیست.',
            'return_description.required' => 'لطفاً دلیل/توضیح بازگشت را وارد کنید.',
        ]);

        $previousStatus = $currentStatus->value;

        $returnType = (string) $validated['return_type'];
        $returnDesc = $validated['return_description'];
        $returnTypeLabel = $returnType === '1' ? 'برگشت انجام شده' : 'برگشت کنسل شده';
        $jalaliNow = \Morilog\Jalali\Jalalian::now()->format('Y/m/d H:i');

        $order->update([
            'return_type' => $returnType,
            'return_description' => $returnDesc,
            'status' => OrderStatus::New->value,
            'status_internal_order' => null,
            'qc_status' => null,
            // ── پاک کردن قیمت‌ها و توضیحات فاکتور قبلی ──
            // مقادیر قبلی در `wp_return_logs` ذخیره شده و قابل بازیابی است.
            // با خالی شدن این فیلدها، تکنسین در «تکمیل مجدد» مجبور می‌شود
            // عددهای واقعی این مرحله را وارد کند، نه عددهای قبلی را.
            'price_customer' => 0,
            'cost_price' => 0,
            'hire' => 0,
            'transportation' => 0,
            'discount' => 0,
            'total_invoice' => 0,
            'invoice_descripotion' => null,
            'piece_list' => null,
            'customer_price_list' => null,
            'buy_price_list' => null,
            'negative_invoice' => 0,
        ]);

        // ── snapshot به log_return (هم‌ارز CreateLogReturnOrder در WP)
        $existingLogReturn = $order->wp_return_logs;
        $existingLogReturn[] = [
            'return_type' => $returnType,
            'return_type_message' => $returnTypeLabel,
            'return_description' => $returnDesc,
            'invoice_descripotion' => $order->invoice_descripotion,
            'cancel_desc' => $order->getRawOriginal('cancel_reason'),
            'cancel_desc_other' => null,
            'customer_price' => $order->customer_price,
            'buy_price' => $order->buy_price,
            'piece_list' => $order->piece_list,
            'customer_price_list' => $order->customer_price_list,
            'buy_price_list' => $order->buy_price_list,
            'hire' => $order->hire,
            'transportation' => $order->transportation,
            'discount' => $order->discount,
            'price_customer' => $order->price_customer,
            'cost_price' => $order->cost_price,
            'total_invoice' => $order->total_invoice,
            'negative_invoice' => $order->negative_invoice,
            'device_image_input' => $order->device_img1 ?: $order->device_image_input,
            'date' => $jalaliNow,
            'author' => auth()->id(),
        ];

        // ── append به order_description_content (هم‌ارز addStatusDescription)
        $existingEvents = $order->wp_events;
        $existingEvents[] = [
            'subject' => 'بازگشت سفارش',
            'content' => $returnDesc,
            'author' => auth()->id(),
            'date' => $jalaliNow,
            'status' => 'بازگشت سفارش',
        ];

        $order->update([
            'log_return' => json_encode($existingLogReturn, JSON_UNESCAPED_UNICODE),
            'order_description_content' => json_encode($existingEvents, JSON_UNESCAPED_UNICODE),
        ]);

        // ── crm_order_status_logs (تاریخچهٔ پنل جدید)
        OrderStatusLog::create([
            'order_id' => $order->id,
            'from_status' => $previousStatus,
            'to_status' => OrderStatus::New->value,
            'note' => 'بازگشت سفارش (' . $returnTypeLabel . ') — ' . $returnDesc,
            'changed_by' => auth()->id(),
            'created_at' => now(),
        ]);

        return back()->with('success', 'سفارش بازگشت داده شد و وضعیت به «جدید» تغییر کرد.');
    }

    // ───────────── داشبورد تکنسین ─────────────────────────────
    public function myOrders(Request $request)
    {
        $technician = Technician::where('user_id', auth()->id())->first();
        if (! $technician) {
            abort(403, 'شما به عنوان تکنسین فعال ثبت نشده‌اید.');
        }

        $status = $request->string('status')->toString();

        // وضعیت‌های فعال = همه‌ای که برای تکنسین اقدام لازم دارند. سفارش‌های
        // نهایی‌شده (انجام کار/کنسل/رد/ایاب و ذهاب) به‌صورت پیش‌فرض از پنل
        // تکنسین خارج می‌شوند تا فقط روی کار باز تمرکز کنند.
        $activeStatuses = [
            OrderStatus::New->value,
            OrderStatus::Coordinated->value,
            OrderStatus::Open->value,
            OrderStatus::Suspended->value,
        ];
        $finalStatuses = [
            OrderStatus::Completed->value,
            OrderStatus::Cancelled->value,
            OrderStatus::Transit->value,
            OrderStatus::Declined->value,
        ];

        $baseQuery = Order::with(['customer', 'brand', 'device', 'province', 'city'])
            ->forTechnician($technician->id);

        $listQuery = (clone $baseQuery);
        if ($status === '') {
            // پیش‌فرض: فقط فعال‌ها
            $listQuery->whereIn('status', $activeStatuses);
        } elseif ($status === 'archive') {
            // تب آرشیو: کنسل/تکمیل/...
            $listQuery->whereIn('status', $finalStatuses);
        } else {
            $listQuery->where('status', $status);
        }

        $orders = $listQuery->latest()->paginate(25)->withQueryString();

        // شمارش هر تب
        $rawCounts = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        $tabs = [];
        foreach ($activeStatuses as $key) {
            $tabs[$key] = [
                'label' => OrderStatus::from($key)->label(),
                'count' => (int) ($rawCounts[$key] ?? 0),
            ];
        }
        $activeCount = array_sum(array_map(fn ($k) => (int) ($rawCounts[$k] ?? 0), $activeStatuses));
        $archiveCount = array_sum(array_map(fn ($k) => (int) ($rawCounts[$k] ?? 0), $finalStatuses));

        return view('crm::orders.my', [
            'orders' => $orders,
            'technician' => $technician,
            'status' => $status,
            'tabs' => $tabs,
            'activeCount' => $activeCount,
            'archiveCount' => $archiveCount,
        ]);
    }

    // ───────────── Validation ───────────────────────────────────
    /**
     * تبدیل تاریخ شمسی Y/m/d (با ارقام فارسی یا لاتین) به Y-m-d میلادی.
     * در صورت خالی یا نامعتبر بودن، null برمی‌گرداند.
     */
    protected function jalaliToGregorian(?string $jalaliDate): ?string
    {
        if (! $jalaliDate || trim($jalaliDate) === '') {
            return null;
        }

        // ارقام فارسی/عربی → لاتین
        $latin = strtr($jalaliDate, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        $latin = str_replace('-', '/', trim($latin));

        try {
            return \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $latin)
                ->toCarbon()
                ->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function validateOrder(Request $request, bool $updating = false): array
    {
        $rules = [
            'brand_id' => 'nullable|exists:crm_brands,id',
            'device_id' => 'nullable|exists:crm_devices,id',
            'province_id' => 'nullable|exists:crm_provinces,id',
            'city_id' => 'nullable|exists:crm_cities,id',
            'address' => 'nullable|string|max:2000',
            'postal_code' => 'nullable|string|max:20',
            'problem_title' => 'nullable|string|max:255',
            'problem_description' => 'nullable|string|max:5000',
            'visit_scheduled_at' => 'nullable|date',
            'estimated_price' => 'nullable|integer|min:0',
            'deposit' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:5000',
        ];

        if ($updating) {
            $rules['final_price'] = 'nullable|integer|min:0';
            $rules['customer_name'] = 'nullable|string|max:255';
            $rules['customer_mobile'] = 'nullable|string|max:20|regex:/^09\d{9}$/';
            $rules['customer_phone'] = 'nullable|string|max:20';
        } else {
            $rules['customer_id'] = 'required|exists:crm_customers,id';
            $rules['status'] = 'nullable|string|in:' . implode(',', array_keys(OrderStatus::options()));
        }

        // فیلدهای مشترک edit + create که از فرم می‌آیند
        $rules['introduction'] = 'nullable|string|max:255';
        $rules['order_type'] = 'nullable|string|in:repair,service,install';
        $rules['technician_id'] = 'nullable|integer|exists:crm_technicians,id';
        $rules['subscription'] = 'nullable|integer|min:0';

        return $request->validate($rules);
    }
}
