<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\WalletTransaction;

/**
 * تولید فاکتور از سفارش — هم‌سو با مدل مالی WP CRM.
 *
 * بر خلاف نسخه قبلی، این سرویس هیچ تراکنش کیف‌پول از نوع Commission
 * ثبت نمی‌کند. در پنل WP، سهم تکنسین (tech_share) به‌عنوان «اعتبار»
 * در کیف‌پول وارد نمی‌شود — تکنسین آن را به‌صورت نقدی از مشتری
 * می‌گیرد. کیف‌پول فقط شامل: شارژ شرکت → تکنسین، پاداش، جریمه،
 * پرداخت‌ها/برداشت‌ها است. مانده نهایی = wallet_balance − sum(company_share).
 *
 * اگر در نسخه قبلی Commission تراکنش‌هایی ساخته شده‌اند، باید با
 * crm:invoices:recompute پاک‌سازی شوند تا مانده با WP همخوان شود.
 *
 * idempotent: اگر سفارش از قبل فاکتور دارد، همان برمی‌گردد.
 */
class InvoiceService
{
    public function __construct(protected CommissionCalculator $calc) {}

    /**
     * حالتِ صدورِ فاکتور برای تکمیلِ این سفارش — تنها مرجعِ تصمیم
     * (API اپ، پنلِ تکنسین و پنلِ ادمین همه از همین می‌خوانند).
     * حکمِ نهاییِ ۱۴۰۵/۰۵/۲۹: «در تکمیلِ سفارش هیچ فاکتوری باطل نمی‌شود
     * و هیچ تعدیلی (برگشتِ کمیسیون) ثبت نمی‌شود — هرگز.»
     *
     *   - دورِ بازگشتی (بررسیِ تکنسین ثبت شده — چه گارانتی=۰ چه ایرادِ
     *     جدیدِ با هزینه) → «additive»: فاکتورِ جدید در کنارِ قبلی‌ها؛
     *     بدهیِ تکنسین جمعِ سهمِ شرکتِ همه است.
     *   - تکمیلِ مجددِ خارج از چرخهٔ بازگشتی → «idempotent»: فاکتورِ
     *     قبلی دست‌نخورده برمی‌گردد؛ اگر مبلغ عوض شده، بنرِ مغایرت هشدار
     *     می‌دهد و مسیرِ درست دکمهٔ «اصلاح مبلغ فاکتور» است.
     *
     * باطل‌کردن + برگشتِ خودکارِ کمیسیون فقط در دو جا مجاز است:
     * correctInvoice (اصلاحِ ادمین) و لغوِ فاکتور.
     *
     * ملاک، return_reviewed_at است نه return_type — چون «ردِ کارشناسی»
     * (ایرادِ جدید) return_type را خالی می‌کند ولی همچنان دورِ بازگشتی
     * است و باید جمع‌شونده بماند (گزارشِ ORD-2607-02619).
     */
    public function completionInvoiceMode(Order $order): string
    {
        return $order->return_reviewed_at !== null ? 'additive' : 'idempotent';
    }

    /**
     * صدورِ فاکتورِ سفارش — دو حالت (خروجیِ completionInvoiceMode):
     *
     *   false / 'idempotent' : اگر فاکتوری هست، همان برمی‌گردد — بدونِ
     *                          هیچ تغییری (ضدِ double-click؛ «فاکتورهای
     *                          جامانده»؛ تکمیلِ مجددِ غیربازگشتی)
     *   'additive'           : فاکتورِ جدید در کنارِ قبلی‌ها — دورِ
     *                          بازگشتی؛ هیچ‌چیز باطل نمی‌شود
     *
     * حالتِ «supersede» عمداً حذف شده (حکمِ ۱۴۰۵/۰۵/۲۹): تکمیلِ سفارش
     * هرگز فاکتورِ قبلی را باطل نمی‌کند و تعدیلی نمی‌زند — باطل‌کردن +
     * برگشتِ کمیسیون فقط در correctInvoice و لغوِ فاکتور.
     *
     * @param  bool|string  $mode  خروجیِ completionInvoiceMode یا bool قدیمی
     * @param  string|null  $collectionMethod  «روش دریافت» انتخابِ تکنسین
     *                                         (cash|online) — null = قدیمی
     */
    /**
     * snapshotِ شرح/اقلامِ فاکتور در لحظهٔ صدور — تا هر فاکتور (به‌ویژه در
     * سفارشِ بازگشتیِ جمع‌شونده) شرحِ مستقلِ خودش را داشته باشد و به فیلدِ
     * زندهٔ سفارش (که با تکمیلِ بعدی بازنویسی می‌شود) وابسته نباشد.
     *
     * اگر ستون‌های snapshot هنوز روی DB نباشند (پنجرهٔ دیپلوی/مهاجرتِ
     * ناتمام)، آرایهٔ خالی برمی‌گرداند تا Invoice::create روی ستونِ ناموجود
     * ننویسد و صدورِ فاکتور ۵۰۰ ندهد.
     *
     * @return array{description?: ?string, items_snapshot?: ?array}
     */
    private function snapshotFor(Order $order): array
    {
        if (! Invoice::supportsSnapshot()) {
            return [];
        }

        $desc = trim((string) ($order->invoice_descripotion ?? ''));

        $rows = [];
        $titles = is_array($order->piece_list) ? $order->piece_list : [];
        $sells = is_array($order->customer_price_list) ? $order->customer_price_list : [];
        foreach ($titles as $i => $title) {
            $t = is_string($title) ? $title : (string) ($title['title'] ?? '');
            if (trim($t) === '') {
                continue;
            }
            $rows[] = ['title' => $t, 'total' => (int) ($sells[$i] ?? 0)];
        }

        return [
            'description' => $desc !== '' ? $desc : null,
            'items_snapshot' => $rows !== [] ? $rows : null,
        ];
    }

    public function generateForOrder(Order $order, ?int $createdBy = null, bool|string $mode = false, ?string $collectionMethod = null): ?Invoice
    {
        // هر مقدارِ ناشناخته (از جمله true و 'supersede' قدیمی) به حالتِ
        // امنِ idempotent می‌افتد — هیچ مسیری نباید ناخواسته باطل/برگشت بزند.
        $mode = $mode === 'additive' ? 'additive' : 'idempotent';

        // گاردِ سفت: سفارش‌های legacy-closed (از لاگ قدیمیِ WP بسته شده‌اند و
        // حسابداری‌شان قطعی‌ست) هرگز نباید فاکتور/wallet-tx بگیرند. اگر فاکتوری
        // از قبل دارند همان برمی‌گردد؛ در غیر این صورت null — بدون ساختِ چیزی.
        if ($order->is_legacy_closed) {
            return Invoice::where('order_id', $order->id)->first();
        }

        $existing = Invoice::where('order_id', $order->id)->latest('id')->first();

        if ($existing && $mode === 'idempotent') {
            return $existing;
        }

        // additive هم idempotent است — اگر برای همین دورِ بازگشتی قبلاً
        // فاکتور صادر شده (double-submit / race)، دوباره نمی‌سازد.
        if ($existing && $mode === 'additive') {
            $roundStart = $order->return_reviewed_at ?? $order->status_changed_at;
            if ($roundStart && $existing->created_at && $existing->created_at->gte($roundStart)) {
                return $existing;
            }
        }

        $collectionMethod = in_array($collectionMethod, ['cash', 'online'], true) ? $collectionMethod : null;

        // دفاع در عمق: وقتی بستانکاریِ تکنسین از شرکت به سقف رسیده،
        // فاکتور صرف‌نظر از ورودی (online یا null قدیمی) نقدی ثبت می‌شود.
        // isPayableOnline از همین ستون می‌خواند، پس درگاه در همهٔ مسیرها
        // (لینک پرداخت، رسید عمومی، اپ مشتری) خودکار خاموش می‌ماند.
        if ($collectionMethod !== 'cash'
            && $order->technician
            && $order->technician->isOnlineCollectionBlocked()) {
            $collectionMethod = 'cash';
        }

        $invoice = DB::transaction(function () use ($order, $createdBy, $collectionMethod) {
            $technician = $order->technician;

            $totals = $technician
                ? $this->calc->calculate($order, $technician)
                : ['total' => (int) ($order->final_price ?? $order->items_subtotal ?? 0),
                    'tech_share' => 0, 'company_share' => (int) ($order->final_price ?? $order->items_subtotal ?? 0),
                    'percent' => 0, 'calc_type' => null];

            $snapshot = $this->snapshotFor($order);

            $invoice = Invoice::create([
                'invoice_code' => Invoice::generateInvoiceCode(),
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'technician_id' => $order->technician_id,
                'total_amount' => $totals['total'],
                ...$snapshot,
                'tech_share' => $totals['tech_share'],
                'company_share' => $totals['company_share'],
                'calc_type' => $totals['calc_type'],
                'commission_percent' => $totals['percent'],
                'status' => 'issued',
                'collection_method' => $collectionMethod,
                'issued_at' => now(),
                'created_by' => $createdBy,
                'in_wallet' => false, // ابتدا false، بعد از ساختن wallet tx → true
            ]);

            $this->postCommission($invoice, $order, $createdBy);

            // در تکمیلِ سفارش به فاکتورهای قبلی هرگز دست نمی‌زنیم — نه
            // باطل‌کردن، نه برگشتِ کمیسیون (حکمِ ۱۴۰۵/۰۵/۲۹). در حالتِ
            // additive هر دو فاکتور معتبرند و بدهیِ تکنسین جمعِ سهمِ
            // شرکتِ همه است.

            return $invoice;
        });

        // ─── بستنِ چرخهٔ پیش‌فاکتور ──────────────────────────────────
        // با صدورِ فاکتورِ نهاییِ سفارش، پیش‌فاکتورهای بازِ همان سفارش
        // «تبدیل‌شده» علامت می‌خورند و به فاکتور لینک می‌شوند (چه از پلِ
        // «نهایی کردن»ِ تکنسین آمده باشند چه دستی). idempotent.
        if ($invoice) {
            try {
                \Modules\CRM\Models\Proforma::where('order_id', $order->id)
                    ->whereIn('status', ['draft', 'sent', 'accepted'])
                    ->update(['status' => 'converted', 'invoice_id' => $invoice->id]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('crm.proforma_convert_failed', [
                    'order_id' => $order->id, 'error' => $e->getMessage(),
                ]);
            }
        }

        // ─── ارسال خودکار پیامک «صدور فاکتور» به مشتری ──────────────
        // فقط بعد از موفقیت transaction، و فقط برای فاکتورهای اولِ یک
        // سفارش (یعنی existing=null) — برای تکمیل مجدد سفارش بازگشتی
        // پیامک دوباره ارسال نمی‌شود (مشتری قبلاً اطلاع داشته).
        if ($invoice && ! $existing) {
            $this->fireInvoiceSms($invoice);
        }

        return $invoice;
    }

    /**
     * اصلاحِ مبلغِ یک فاکتورِ اشتباه توسط ادمین (permission: correct-invoices).
     *
     * دقیقاً همان فاکتور باطل می‌شود (نه همهٔ فاکتورهای فعالِ سفارش — روی
     * سفارشِ بازگشتی ممکن است فاکتورِ فعالِ دیگری باشد که کارِ جداست)،
     * کمیسیونش خودکار برمی‌گردد و فاکتورِ جدید با محاسبه‌گرِ استاندارد
     * صادر می‌شود. لینکِ عمومیِ قدیمی به فاکتورِ جدید ریدایرکت می‌شود
     * (superseded_by_id). پیش‌فرضِ مصوب: مشتری هنوز پرداختی نکرده —
     * فاکتورِ پرداخت‌شده اصلاح‌پذیر نیست.
     *
     * پیامکی ارسال نمی‌شود؛ در تاریخچهٔ سفارش یک لاگِ بدونِ تغییرِ وضعیت
     * ثبت می‌شود (این لاگ‌ها به تکنسین/مشتری نمایش داده نمی‌شوند).
     *
     * @throws \InvalidArgumentException وقتی فاکتور اصلاح‌پذیر نیست
     */
    public function correctInvoice(Invoice $old, int $newTotal, string $reason, ?int $adminId = null): Invoice
    {
        if ($old->superseded_at !== null) {
            throw new \InvalidArgumentException('این فاکتور قبلاً با نسخهٔ جدیدتری جایگزین شده و قابل اصلاح نیست.');
        }
        if ($old->status === 'paid') {
            throw new \InvalidArgumentException('فاکتور پرداخت‌شده قابل اصلاح نیست — ابتدا وضعیت پرداخت را بررسی کنید.');
        }
        if ($old->status === 'cancelled') {
            throw new \InvalidArgumentException('فاکتور لغوشده قابل اصلاح نیست؛ در صورت نیاز از سفارش فاکتور جدید صادر کنید.');
        }
        if ($newTotal < 0) {
            throw new \InvalidArgumentException('مبلغ جدید نامعتبر است.');
        }
        if ($newTotal === (int) $old->total_amount) {
            throw new \InvalidArgumentException('مبلغ جدید با مبلغ فعلی فاکتور یکی است — چیزی برای اصلاح نیست.');
        }

        $order = $old->order;
        if (! $order) {
            throw new \InvalidArgumentException('سفارش مرتبط با این فاکتور یافت نشد.');
        }

        return DB::transaction(function () use ($old, $order, $newTotal, $reason, $adminId) {
            $technician = $order->technician;

            $totals = $technician
                ? $this->calc->calculate($order, $technician, $newTotal)
                : ['total' => $newTotal, 'tech_share' => 0, 'company_share' => $newTotal,
                    'percent' => 0, 'calc_type' => null];

            // روشِ دریافت از فاکتورِ قبلی به ارث می‌رسد؛ دفاعِ سقفِ بستانکاری
            // همین‌جا هم فعال است (مثل generateForOrder).
            $collectionMethod = in_array($old->collection_method, ['cash', 'online'], true)
                ? $old->collection_method : null;
            if ($collectionMethod !== 'cash' && $technician && $technician->isOnlineCollectionBlocked()) {
                $collectionMethod = 'cash';
            }

            // اصلاحِ مبلغ = همان کار با عددِ درست؛ شرح/اقلام از فاکتورِ قبلی
            // به ارث می‌رسد (اگر snapshot داشت)، وگرنه از سفارش. اگر ستون‌ها
            // هنوز نباشند، snap خالی است و چیزی نوشته نمی‌شود.
            $fallback = $this->snapshotFor($order);
            $snap = Invoice::supportsSnapshot() ? [
                'description' => $old->description ?? ($fallback['description'] ?? null),
                'items_snapshot' => $old->items_snapshot ?? ($fallback['items_snapshot'] ?? null),
            ] : [];

            $invoice = Invoice::create([
                'invoice_code' => Invoice::generateInvoiceCode(),
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'technician_id' => $order->technician_id,
                'total_amount' => $totals['total'],
                ...$snap,
                'tech_share' => $totals['tech_share'],
                'company_share' => $totals['company_share'],
                'calc_type' => $totals['calc_type'],
                'commission_percent' => $totals['percent'],
                'status' => 'issued',
                'collection_method' => $collectionMethod,
                'issued_at' => now(),
                'created_by' => $adminId,
                'in_wallet' => false,
            ]);

            $this->postCommission($invoice, $order, $adminId);

            // فقط همین فاکتور باطل می‌شود — با اشاره‌گر برای ریدایرکتِ لینکِ قدیمی.
            Invoice::withoutGlobalScope('active')->whereKey($old->id)
                ->update(['superseded_at' => now(), 'superseded_by_id' => $invoice->id]);

            $this->reverseCommission($old, $adminId, 'اصلاح مبلغ فاکتور');

            // اگر فاکتورِ اصلاح‌شده آخرین فاکتورِ سفارش بود، مبلغِ سفارش هم
            // همگام می‌شود تا بنرِ مغایرت بی‌جهت روشن نماند. total_invoice با
            // همان فرمولِ تکمیل (price_customer − cost_price) به‌روز می‌شود.
            $newerExists = Invoice::withoutGlobalScope('active')
                ->where('order_id', $order->id)
                ->where('id', '>', $old->id)
                ->where('id', '!=', $invoice->id)
                ->exists();
            if (! $newerExists) {
                $order->update([
                    'price_customer' => $newTotal,
                    'total_invoice' => max(0, $newTotal - (int) ($order->cost_price ?? 0)),
                ]);
            }

            $statusValue = $order->status instanceof \Modules\CRM\Enums\OrderStatus
                ? $order->status->value : (string) $order->status;

            \Modules\CRM\Models\OrderStatusLog::create([
                'order_id' => $order->id,
                'from_status' => $statusValue,
                'to_status' => $statusValue,
                'note' => 'اصلاح مبلغ فاکتور '.$old->invoice_code.' ← '.$invoice->invoice_code
                    .' — مبلغ از '.number_format((int) $old->total_amount)
                    .' به '.number_format($newTotal).' تومان تغییر کرد. دلیل: '.$reason,
                'changed_by' => $adminId,
                'created_at' => now(),
            ]);

            return $invoice;
        });
    }

    /**
     * ثبتِ تراکنشِ کیف‌پول «سهم شرکت» برای فاکتورِ تازه‌ساخته‌شده.
     *
     * فقط وقتی تکنسین دارد و company_share مثبت است — فاکتورِ صفر تومانی
     * (مثلاً گارانتیِ بازگشتی) از نظرِ مالی خنثی است و tx نمی‌گیرد.
     * invoice_debt این فاکتورها صفر است (در getInvoiceDebt فیلتر می‌شود)
     * تا double-count نشود.
     */
    protected function postCommission(Invoice $invoice, Order $order, ?int $createdBy): void
    {
        if (! $invoice->technician_id || (int) $invoice->company_share <= 0) {
            return;
        }

        $last = (int) (WalletTransaction::where('technician_id', $invoice->technician_id)
            ->orderByDesc('id')->value('balance_after') ?? 0);
        $amount = -1 * (int) $invoice->company_share;

        WalletTransaction::create([
            'technician_id' => $invoice->technician_id,
            'order_id' => $order->id,
            'invoice_id' => $invoice->id,
            'wp_id' => null,
            'type' => WalletTxType::Commission->value,
            'amount' => $amount,
            'balance_after' => $last + $amount,
            'note' => 'سهم شرکت از فاکتور '.$invoice->invoice_code,
            'created_by' => $createdBy,
        ]);

        // wallet_balance تکنسین را به‌روز کن
        \Modules\CRM\Models\Technician::where('id', $invoice->technician_id)
            ->update(['wallet_balance' => $last + $amount]);

        $invoice->update(['in_wallet' => true]);
    }

    /**
     * برگشتِ سهمِ شرکتِ یک فاکتور — فقط از دو مسیرِ مجاز صدا زده می‌شود:
     * اصلاحِ مبلغ (correctInvoice) و لغوِ فاکتور. تکمیلِ سفارش هرگز از
     * این متد استفاده نمی‌کند (حکمِ ۱۴۰۵/۰۵/۲۹).
     *
     * تراکنشِ اصلی حذف نمی‌شود — یک تراکنشِ معکوس (+company_share) ثبت
     * می‌شود تا هم تاریخچه کامل بماند و هم برآیندِ کیف‌پول فقط بابتِ
     * فاکتورِ فعال باشد. idempotent: اگر برگشتِ همین فاکتور قبلاً ثبت شده
     * باشد، دوباره ثبت نمی‌شود.
     *
     * ⚠ بعد از این برگشتِ خودکار، ادمین نباید تعدیلِ دستی هم بزند —
     * جبرانِ دوباره می‌شود (crm:wallet:audit این را کشف می‌کند).
     *
     * @param  string  $context  علتِ برگشت در متنِ تراکنش (بازصدور/اصلاح/لغو)
     * @return bool آیا تراکنشِ برگشت همین حالا ثبت شد؟
     */
    public function reverseCommission(Invoice $old, ?int $createdBy = null, string $context = 'بازصدور فاکتور'): bool
    {
        $companyShare = (int) $old->company_share;
        if (! $old->technician_id || $companyShare <= 0 || ! $old->in_wallet) {
            return false;
        }

        $marker = '[reversal#'.$old->id.']';
        $already = WalletTransaction::where('technician_id', $old->technician_id)
            ->where('invoice_id', $old->id)
            ->where('note', 'like', '%'.$marker.'%')
            ->exists();

        if ($already) {
            return false;
        }

        $last = (int) (WalletTransaction::where('technician_id', $old->technician_id)
            ->orderByDesc('id')->value('balance_after') ?? 0);

        WalletTransaction::create([
            'technician_id' => $old->technician_id,
            'order_id' => $old->order_id,
            'invoice_id' => $old->id,
            'wp_id' => null,
            'type' => WalletTxType::Adjustment->value,
            'amount' => $companyShare, // مثبت — بدهیِ قبلی برداشته می‌شود
            'balance_after' => $last + $companyShare,
            'note' => 'برگشت سهم شرکت فاکتور '.$old->invoice_code.' ('.$context.') '.$marker,
            'created_by' => $createdBy,
        ]);

        \Modules\CRM\Models\Technician::where('id', $old->technician_id)
            ->update(['wallet_balance' => $last + $companyShare]);

        return true;
    }

    /**
     * ارسال پیامک «customer_invoice_issued» با لینک عمومی فاکتور.
     * در صورت غیرفعال بودن تمپلیت یا خطای کاوه‌نگار، سایلنت رد می‌شود
     * (لاگ ولی فاکتور بدون مشکل ساخته شده).
     */
    protected function fireInvoiceSms(Invoice $invoice): void
    {
        $log = \Illuminate\Support\Facades\Log::channel(config('logging.default'));
        try {
            $order = $invoice->order;
            if (! $order) {
                $log->warning('Auto invoice SMS skipped: order not found', ['invoice_id' => $invoice->id]);

                return;
            }

            $mobile = $order->customer_mobile ?: $order->customer?->mobile;
            if (! $mobile) {
                $log->warning('Auto invoice SMS skipped: customer has no mobile', ['order_id' => $order->id, 'invoice_id' => $invoice->id]);

                return;
            }

            $trigger = \Modules\CRM\Enums\SmsTrigger::CustomerInvoiceIssued;
            $template = \Modules\CRM\Models\SmsTemplate::where('trigger_key', $trigger->value)->first();
            if (! $template) {
                $log->warning('Auto invoice SMS skipped: template row missing', ['trigger' => $trigger->value]);

                return;
            }
            if (! $template->is_active) {
                $log->warning('Auto invoice SMS skipped: template is inactive', ['trigger' => $trigger->value]);

                return;
            }
            if (empty($template->kavenegar_template)) {
                $log->warning('Auto invoice SMS skipped: kavenegar_template empty', ['trigger' => $trigger->value]);

                return;
            }

            $order->loadMissing('customer');
            $vars = [
                'customer_name' => $order->customer_name ?: $order->customer?->display_name ?: '',
                'order_code' => (string) ($order->order_code ?? ''),
                'amount' => (string) (int) $invoice->total_amount,
                'invoice_code' => (string) $invoice->invoice_code,
                'receipt_url' => $invoice->publicUrl(),
                // توکنِ امنِ غیرقابل‌حدس — فقط همین در لینکِ پیامک می‌نشیند
                // (دامنه/مسیر داخل تمپلیتِ تأییدشدهٔ کاوه‌نگار است). با مَپ‌کردنِ
                // یکی از توکن‌ها به {public_token} لینک امن می‌شود.
                'public_token' => (string) $invoice->public_token,
            ];
            $tokens = $template->renderTokens($vars);

            $sms = app(\Modules\SMS\Services\KavenegarService::class);
            $result = $sms->sendTemplate($mobile, $template->kavenegar_template, $tokens);

            \Modules\CRM\Models\SmsLog::create([
                'order_id' => $order->id,
                'trigger_key' => $trigger->value,
                'recipient_mobile' => $mobile,
                'recipient_role' => 'customer',
                'body' => $template->kavenegar_template.' | '.json_encode($tokens, JSON_UNESCAPED_UNICODE),
                'status' => $result['success'] ? 'success' : 'failed',
                'response' => $result['success'] ? null : ($result['message'] ?? null),
                'sent_by' => null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Auto invoice SMS failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
